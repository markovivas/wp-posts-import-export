<?php
namespace WPPostsImportExport;

defined('ABSPATH') || exit;

class Exporter {

    private $temp_dir;
    private $date_image_counts = [];

    public function export($category = 0, $date_start = '', $date_end = '') {
        $this->temp_dir = $this->create_temp_dir();

        if (is_wp_error($this->temp_dir)) {
            return $this->temp_dir;
        }

        $posts_data = $this->collect_posts($category, $date_start, $date_end);
        $data = [
            'version'     => WP_PIE_VERSION,
            'export_date' => current_time('mysql'),
            'site_url'    => home_url(),
            'posts'       => $posts_data,
        ];

        $json_file = $this->temp_dir . 'posts.json';
        $written = file_put_contents($json_file, wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (false === $written) {
            $this->cleanup_temp_dir();
            return new \WP_Error('json_write_error', __('Falha ao escrever o arquivo posts.json.', 'wp-posts-import-export'));
        }

        $zip_file = $this->create_zip();

        if (is_wp_error($zip_file)) {
            $this->cleanup_temp_dir();
            return $zip_file;
        }

        $this->cleanup_temp_dir();

        $upload_dir = wp_upload_dir();
        $file_url = $upload_dir['baseurl'] . '/' . basename($zip_file);

        return [
            'file_path'  => $zip_file,
            'file_url'   => $file_url,
            'filename'   => basename($zip_file),
            'post_count' => count($posts_data),
        ];
    }

    private function create_temp_dir() {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return new \WP_Error('upload_dir_error', __('O diretorio de upload nao tem permissao de escrita.', 'wp-posts-import-export'));
        }

        $temp_dir = $upload_dir['basedir'] . '/wp-pie-temp-' . uniqid() . '/';

        if (!wp_mkdir_p($temp_dir)) {
            return new \WP_Error('temp_dir_error', __('Falha ao criar diretorio temporario.', 'wp-posts-import-export'));
        }

        $images_dir = $temp_dir . 'images/';
        wp_mkdir_p($images_dir);

        return $temp_dir;
    }

    private function collect_posts($category, $date_start, $date_end) {
        $args = [
            'post_type'      => 'post',
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby'        => 'post_date',
            'order'          => 'ASC',
        ];

        if (!empty($category)) {
            $args['cat'] = absint($category);
        }

        if (!empty($date_start)) {
            $args['date_query'][] = [
                'after'     => sanitize_text_field($date_start),
                'inclusive' => true,
            ];
        }

        if (!empty($date_end)) {
            $args['date_query'][] = [
                'before'    => sanitize_text_field($date_end),
                'inclusive' => true,
            ];
        }

        $posts = get_posts($args);
        $posts_data = [];

        foreach ($posts as $post) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $image_filename = $this->process_featured_image($thumbnail_id, $post);

            $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
            $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);

            $user = get_user_by('id', $post->post_author);

            $posts_data[] = [
                'title'          => $post->post_title,
                'content'        => $post->post_content,
                'excerpt'        => $post->post_excerpt,
                'slug'           => $post->post_name,
                'post_date'      => $post->post_date,
                'post_date_gmt'  => $post->post_date_gmt,
                'status'         => $post->post_status,
                'author'         => $user ? $user->user_login : '',
                'author_id'      => $post->post_author,
                'categories'     => $categories,
                'tags'           => $tags,
                'featured_image' => $image_filename,
            ];
        }

        return $posts_data;
    }

    private function process_featured_image($thumbnail_id, $post) {
        if (empty($thumbnail_id)) {
            return '';
        }

        $image_path = get_attached_file($thumbnail_id);

        if (empty($image_path) || !file_exists($image_path)) {
            return '';
        }

        $ext = pathinfo($image_path, PATHINFO_EXTENSION);
        $date = mysql2date('d-m-Y', $post->post_date);
        $base_filename = $date . '.' . $ext;

        if (!isset($this->date_image_counts[$base_filename])) {
            $this->date_image_counts[$base_filename] = 0;
            $final_filename = $base_filename;
        } else {
            $this->date_image_counts[$base_filename]++;
            $final_filename = $date . '-' . $this->date_image_counts[$base_filename] . '.' . $ext;
        }

        $dest_path = $this->temp_dir . 'images/' . $final_filename;
        copy($image_path, $dest_path);

        return $final_filename;
    }

    private function create_zip() {
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('zip_missing', __('A extensao ZipArchive e necessaria.', 'wp-posts-import-export'));
        }

        $upload_dir = wp_upload_dir();
        $zip_file = $upload_dir['basedir'] . '/wp-pie-export-' . uniqid() . '.zip';

        $zip = new \ZipArchive();

        if (true !== $zip->open($zip_file, \ZipArchive::CREATE)) {
            return new \WP_Error('zip_create_error', __('Falha ao criar arquivo ZIP.', 'wp-posts-import-export'));
        }

        $zip->addFile($this->temp_dir . 'posts.json', 'posts.json');

        $images_dir = $this->temp_dir . 'images/';
        if (is_dir($images_dir)) {
            $zip->addEmptyDir('images');
            $iterator = new \FilesystemIterator($images_dir);

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), 'images/' . $file->getFilename());
                }
            }
        }

        $zip->close();

        return $zip_file;
    }

    private function cleanup_temp_dir() {
        if (empty($this->temp_dir) || !is_dir($this->temp_dir)) {
            return;
        }

        $this->delete_directory($this->temp_dir);
    }

    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                @unlink($file->getPathname());
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }

        @rmdir($dir);
    }

    public static function cleanup_old_exports() {
        $upload_dir = wp_upload_dir();
        $pattern = $upload_dir['basedir'] . '/wp-pie-export-*.zip';
        $files = glob($pattern);

        if (empty($files)) {
            return;
        }

        $max_age = DAY_IN_SECONDS;

        foreach ($files as $file) {
            if (time() - filemtime($file) > $max_age) {
                @unlink($file);
            }
        }
    }
}
