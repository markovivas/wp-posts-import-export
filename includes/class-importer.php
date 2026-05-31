<?php
namespace WPPostsImportExport;

defined('ABSPATH') || exit;

class Importer {

    private $batch_size = 5;

    public function start_import($file) {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return new \WP_Error('upload_dir_error', __('Diretorio de upload nao acessivel.', 'wp-posts-import-export'));
        }

        $zip_filename = 'wp-pie-upload-' . uniqid() . '.zip';
        $zip_path = $upload_dir['path'] . '/' . $zip_filename;

        if (!move_uploaded_file($file['tmp_name'], $zip_path)) {
            return new \WP_Error('move_error', __('Falha ao mover o arquivo enviado.', 'wp-posts-import-export'));
        }

        if (!class_exists('ZipArchive')) {
            @unlink($zip_path);
            return new \WP_Error('zip_missing', __('A extensao ZipArchive e necessaria.', 'wp-posts-import-export'));
        }

        $test_zip = new \ZipArchive();
        if (true !== $test_zip->open($zip_path)) {
            @unlink($zip_path);
            return new \WP_Error('invalid_zip', __('O arquivo enviado nao e um ZIP valido.', 'wp-posts-import-export'));
        }
        $test_zip->close();

        // Geramos um ID alfanumérico limpo para evitar problemas com sanitize_key() no processamento AJAX.
        // Usamos bin2hex(random_bytes) para máxima compatibilidade e segurança em PHP 8.1.
        $import_id = 'wp_pie_' . bin2hex(random_bytes(8));

        $extract_dir = $this->extract_zip($zip_path);

        if (is_wp_error($extract_dir)) {
            @unlink($zip_path);
            return $extract_dir;
        }

        $json_path = $extract_dir . 'posts.json';

        if (!file_exists($json_path)) {
            $this->delete_directory($extract_dir);
            @unlink($zip_path);
            return new \WP_Error('missing_json', __('posts.json nao encontrado no arquivo ZIP.', 'wp-posts-import-export'));
        }

        $json_content = file_get_contents($json_path);
        $data = json_decode($json_content, true);

        if (null === $data || !isset($data['posts']) || !is_array($data['posts'])) {
            $this->delete_directory($extract_dir);
            @unlink($zip_path);
            return new \WP_Error('invalid_json', __('Formato invalido do posts.json.', 'wp-posts-import-export'));
        }

        $import_data = [
            'extract_dir' => $extract_dir,
            'zip_path'    => $zip_path,
            'posts'       => $data['posts'],
            'total'       => count($data['posts']),
            'processed'   => 0,
            'results'     => [
                'posts'               => 0,
                'categories_created'  => 0,
                'tags_created'        => 0,
                'images'              => 0,
                'errors'              => [],
            ],
        ];

        set_transient('wp_pie_import_' . $import_id, $import_data, DAY_IN_SECONDS); // Aumenta o tempo de vida inicial do transient para 24 horas

        return [
            'total'      => count($data['posts']),
            'batch_size' => $this->batch_size,
            'import_id'  => $import_id,
        ];
    }

    public function process_batch($import_id, $offset) {
        $import_data = get_transient('wp_pie_import_' . $import_id);

        if (false === $import_data) {
            return new \WP_Error('import_expired', __('Sessao de importacao expirada. Tente novamente.', 'wp-posts-import-export'));
        }

        $posts = $import_data['posts'];
        $total = $import_data['total'];
        $extract_dir = $import_data['extract_dir'];

        $batch = array_slice($posts, $offset, $this->batch_size);
        $batch_results = [
            'processed'           => count($batch),
            'posts'               => 0,
            'categories_created'  => 0,
            'tags_created'        => 0,
            'images'              => 0,
            'errors'              => [],
        ];

        foreach ($batch as $post_data) {
            $result = $this->import_single_post($post_data, $extract_dir);

            if (is_wp_error($result)) {
                $batch_results['errors'][] = sprintf(
                    __('Erro ao importar "%s": %s', 'wp-posts-import-export'),
                    isset($post_data['title']) ? $post_data['title'] : __('Desconhecido', 'wp-posts-import-export'),
                    $result->get_error_message()
                );
            } else {
                $batch_results['posts']++;
                $batch_results['categories_created'] += $result['categories_created'];
                $batch_results['tags_created'] += $result['tags_created'];
                $batch_results['images'] += $result['images'];
            }
        }

        $import_data['processed'] = $offset + count($batch);
        $import_data['results']['posts'] += $batch_results['posts'];
        $import_data['results']['categories_created'] += $batch_results['categories_created'];
        $import_data['results']['tags_created'] += $batch_results['tags_created'];
        $import_data['results']['images'] += $batch_results['images'];

        foreach ($batch_results['errors'] as $error) {
            $import_data['results']['errors'][] = $error;
        }

        set_transient('wp_pie_import_' . $import_id, $import_data, DAY_IN_SECONDS); // Atualiza o tempo de vida do transient a cada lote processado

        if ($import_data['processed'] >= $total) {
            $this->cleanup_import($import_id);
        }

        return $batch_results;
    }

    private function import_single_post($post_data, $extract_dir) {
        $result = [
            'categories_created' => 0,
            'tags_created'       => 0,
            'images'             => 0,
        ];

        $post_title = isset($post_data['title']) ? $post_data['title'] : '';
        $post_content = isset($post_data['content']) ? $post_data['content'] : '';
        $post_excerpt = isset($post_data['excerpt']) ? $post_data['excerpt'] : '';
        $post_slug = isset($post_data['slug']) ? $post_data['slug'] : '';
        $post_status = isset($post_data['status']) ? $post_data['status'] : 'draft';
        $post_date = isset($post_data['post_date']) ? $post_data['post_date'] : current_time('mysql');
        $post_date_gmt = isset($post_data['post_date_gmt']) ? $post_data['post_date_gmt'] : '';

        if (empty($post_title)) {
            return new \WP_Error('empty_title', __('O titulo do post esta vazio.', 'wp-posts-import-export'));
        }

        $author_id = $this->resolve_author($post_data);

        $category_ids = $this->resolve_categories($post_data, $result);
        $tag_names = $this->resolve_tags($post_data, $result);

        $post_id = wp_insert_post([
            'post_title'     => $post_title,
            'post_content'   => $post_content,
            'post_excerpt'   => $post_excerpt,
            'post_name'      => $post_slug,
            'post_status'    => $post_status,
            'post_author'    => $author_id,
            'post_date'      => $post_date,
            'post_date_gmt'  => $post_date_gmt,
            'post_category'  => $category_ids,
            'tags_input'     => $tag_names,
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty($post_data['featured_image'])) {
            $image_id = $this->import_featured_image($post_data['featured_image'], $extract_dir, $post_id);

            if (!is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
                $result['images']++;
            }
        }

        return $result;
    }

    private function resolve_author($post_data) {
        $author_login = isset($post_data['author']) ? $post_data['author'] : '';
        $author_id = isset($post_data['author_id']) ? intval($post_data['author_id']) : 0;

        if (!empty($author_login)) {
            $user = get_user_by('login', $author_login);

            if ($user) {
                return $user->ID;
            }
        }

        if (!empty($author_id)) {
            $user = get_user_by('id', $author_id);

            if ($user) {
                return $user->ID;
            }
        }

        $current_user = wp_get_current_user();

        if ($current_user && $current_user->exists()) {
            return $current_user->ID;
        }

        $admins = get_users(['role' => 'administrator', 'number' => 1]);

        if (!empty($admins)) {
            return $admins[0]->ID;
        }

        return 1;
    }

    private function resolve_categories($post_data, &$result) {
        $input_categories = isset($post_data['categories']) ? $post_data['categories'] : [];
        $category_ids = [];

        foreach ($input_categories as $cat_name) {
            if (empty($cat_name)) {
                continue;
            }

            $term = term_exists($cat_name, 'category');

            if (0 !== $term && null !== $term) {
                $category_ids[] = (int) $term['term_id'];
            } else {
                $new_term = wp_insert_term($cat_name, 'category');

                if (!is_wp_error($new_term)) {
                    $category_ids[] = (int) $new_term['term_id'];
                    $result['categories_created']++;
                }
            }
        }

        return $category_ids;
    }

    private function resolve_tags($post_data, &$result) {
        $input_tags = isset($post_data['tags']) ? $post_data['tags'] : [];
        $tag_names = [];

        foreach ($input_tags as $tag_name) {
            if (empty($tag_name)) {
                continue;
            }

            $term = term_exists($tag_name, 'post_tag');

            if (0 === $term || null === $term) {
                wp_insert_term($tag_name, 'post_tag');
                $result['tags_created']++;
            }

            $tag_names[] = $tag_name;
        }

        return $tag_names;
    }

    private function import_featured_image($filename, $extract_dir, $post_id) {
        $images_dir = $extract_dir . 'images/';
        $file_path = $images_dir . $filename;

        if (!file_exists($file_path)) {
            return new \WP_Error('image_not_found', sprintf(__('Arquivo de imagem nao encontrado: %s', 'wp-posts-import-export'), $filename));
        }

        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . $filename;

        $target_path = $this->unique_filename($target_path);

        if (!copy($file_path, $target_path)) {
            return new \WP_Error('image_copy_error', sprintf(__('Falha ao copiar imagem: %s', 'wp-posts-import-export'), $filename));
        }

        $file_name = basename($target_path);
        $wp_filetype = wp_check_filetype($file_name);

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $upload_dir['url'] . '/' . $file_name,
        ];

        $attach_id = wp_insert_attachment($attachment, $target_path, $post_id);

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        // Garante que todas as dependencias de media do admin estejam carregadas
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $target_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    private function unique_filename($path) {
        if (!file_exists($path)) {
            return $path;
        }

        $dir = dirname($path);
        $filename = basename($path);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $counter = 1;

        while (file_exists($dir . '/' . $name . '-' . $counter . '.' . $ext)) {
            $counter++;
        }

        return $dir . '/' . $name . '-' . $counter . '.' . $ext;
    }

    private function extract_zip($zip_path) {
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('zip_missing', __('A extensao ZipArchive e necessaria.', 'wp-posts-import-export'));
        }

        $upload_dir = wp_upload_dir();
        $extract_dir = $upload_dir['basedir'] . '/wp-pie-extract-' . uniqid() . '/';

        if (!wp_mkdir_p($extract_dir)) {
            return new \WP_Error('extract_dir_error', __('Falha ao criar diretorio de extracao.', 'wp-posts-import-export'));
        }

        $zip = new \ZipArchive();

        if (true !== $zip->open($zip_path)) {
            $this->delete_directory($extract_dir);
            return new \WP_Error('zip_open_error', __('Falha ao abrir arquivo ZIP.', 'wp-posts-import-export'));
        }

        if (!$zip->extractTo($extract_dir)) {
            $zip->close();
            $this->delete_directory($extract_dir);
            return new \WP_Error('zip_extract_error', __('Falha ao extrair arquivo ZIP.', 'wp-posts-import-export'));
        }

        $zip->close();
        @unlink($zip_path);

        return $extract_dir;
    }

    private function cleanup_import($import_id) {
        $import_data = get_transient('wp_pie_import_' . $import_id);

        if (false !== $import_data && isset($import_data['extract_dir'])) {
            $this->delete_directory($import_data['extract_dir']);
        }

        delete_transient('wp_pie_import_' . $import_id);
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
}
