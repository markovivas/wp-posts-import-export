<?php
namespace WPPostsImportExport;

defined('ABSPATH') || exit;

class Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('load-tools_page_wp-posts-import-export', [$this, 'handle_post_actions']);
        add_action('wp_ajax_wp_pie_import_start', [$this, 'ajax_import_start']);
        add_action('wp_ajax_wp_pie_import_process', [$this, 'ajax_import_process']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            __('Importar/Exportar Posts', 'wp-posts-import-export'),
            __('Importar/Exportar Posts', 'wp-posts-import-export'),
            'manage_options',
            'wp-posts-import-export',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook) {
        if ('tools_page_wp-posts-import-export' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'wp-pie-admin',
            WP_PIE_PLUGIN_URL . 'assets/admin.css',
            [],
            WP_PIE_VERSION
        );
    }

    public function handle_post_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['wp_pie_export_action'])) {
            check_admin_referer('wp_pie_export', 'wp_pie_export_nonce');
            $this->handle_export();
        }

        Exporter::cleanup_old_exports();
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Voce nao tem permissoes suficientes.', 'wp-posts-import-export'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'export';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Importar/Exportar Posts', 'wp-posts-import-export'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-posts-import-export&tab=export" class="nav-tab <?php echo 'export' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Exportar', 'wp-posts-import-export'); ?>
                </a>
                <a href="?page=wp-posts-import-export&tab=import" class="nav-tab <?php echo 'import' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Importar', 'wp-posts-import-export'); ?>
                </a>
            </h2>

            <div class="wp-pie-tab-content">
                <?php if ('export' === $active_tab) : ?>
                    <?php $this->render_export_tab(); ?>
                <?php else : ?>
                    <?php $this->render_import_tab(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_export_tab() {
        $categories = get_categories(['hide_empty' => false]);
        ?>
        <div class="wp-pie-export-section">
            <h2><?php echo esc_html__('Exportar Posts', 'wp-posts-import-export'); ?></h2>
            <p><?php echo esc_html__('Exporte todos os posts ou filtre por categoria e periodo. A exportacao gerara um arquivo ZIP contendo um arquivo JSON com todos os dados dos posts e uma pasta com as imagens destacadas.', 'wp-posts-import-export'); ?></p>

            <form id="wp-pie-export-form" method="post">
                <?php wp_nonce_field('wp_pie_export', 'wp_pie_export_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp-pie-category"><?php echo esc_html__('Filtrar por Categoria', 'wp-posts-import-export'); ?></label>
                        </th>
                        <td>
                            <select name="category" id="wp-pie-category">
                                <option value=""><?php echo esc_html__('Todas as Categorias', 'wp-posts-import-export'); ?></option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp-pie-date-start"><?php echo esc_html__('Data Inicial', 'wp-posts-import-export'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="date_start" id="wp-pie-date-start" value="">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp-pie-date-end"><?php echo esc_html__('Data Final', 'wp-posts-import-export'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="date_end" id="wp-pie-date-end" value="">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="wp_pie_export_action" class="button button-primary">
                        <?php echo esc_html__('Exportar Posts', 'wp-posts-import-export'); ?>
                    </button>
                </p>
            </form>

            <div id="wp-pie-export-progress" style="display:none;">
                <div class="wp-pie-progress-bar">
                    <div class="wp-pie-progress-bar-inner"></div>
                </div>
                <p class="wp-pie-progress-text"><?php echo esc_html__('Gerando exportacao...', 'wp-posts-import-export'); ?></p>
            </div>
        </div>

        <script>
        (function() {
            var form = document.getElementById('wp-pie-export-form');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                if (form.getAttribute('data-submitting') === '1') {
                    e.preventDefault();
                    return;
                }
                form.setAttribute('data-submitting', '1');

                var btn = form.querySelector('button[type="submit"]');
                btn.textContent = '<?php echo esc_js(__('Exportando...', 'wp-posts-import-export')); ?>';

                var progress = document.getElementById('wp-pie-export-progress');
                progress.style.display = 'block';
                progress.querySelector('.wp-pie-progress-bar-inner').style.width = '50%';
                progress.querySelector('.wp-pie-progress-text').textContent = '<?php echo esc_js(__('Gerando exportacao...', 'wp-posts-import-export')); ?>';
            });
        })();
        </script>
        <?php
    }

    private function render_import_tab() {
        ?>
        <div class="wp-pie-import-section">
            <h2><?php echo esc_html__('Importar Posts', 'wp-posts-import-export'); ?></h2>
            <p><?php echo esc_html__('Envie um arquivo ZIP exportado anteriormente por este plugin para importar os posts.', 'wp-posts-import-export'); ?></p>

            <form id="wp-pie-import-form" method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp-pie-zip-file"><?php echo esc_html__('Arquivo ZIP', 'wp-posts-import-export'); ?></label>
                        </th>
                        <td>
                            <input type="file" name="wp_pie_zip" id="wp-pie-zip-file" accept=".zip" required>
                            <p class="description">
                                <?php echo esc_html__('Selecione o arquivo ZIP exportado por este plugin.', 'wp-posts-import-export'); ?><br>
                                <strong><?php printf(esc_html__('Limite maximo de upload do seu servidor: %s', 'wp-posts-import-export'), size_format(wp_max_upload_size())); ?></strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="wp-pie-import-btn" class="button button-primary">
                        <?php echo esc_html__('Importar', 'wp-posts-import-export'); ?>
                    </button>
                </p>
            </form>

            <div id="wp-pie-import-progress" style="display:none;">
                <h3><?php echo esc_html__('Progresso da Importacao', 'wp-posts-import-export'); ?></h3>
                <div class="wp-pie-progress-bar">
                    <div class="wp-pie-progress-bar-inner"></div>
                </div>
                <p class="wp-pie-progress-text"><?php echo esc_html__('Iniciando importacao...', 'wp-posts-import-export'); ?></p>
            </div>

            <div id="wp-pie-import-report" style="display:none;">
                <h3><?php echo esc_html__('Relatorio da Importacao', 'wp-posts-import-export'); ?></h3>
                <div class="wp-pie-report-content"></div>
            </div>
        </div>

        <script>
        (function() {
            var btn = document.getElementById('wp-pie-import-btn');
            if (!btn) return;

            btn.addEventListener('click', function() {
                var fileInput = document.getElementById('wp-pie-zip-file');
                if (!fileInput.files || !fileInput.files[0]) {
                    alert('<?php echo esc_js(__('Selecione um arquivo ZIP.', 'wp-posts-import-export')); ?>');
                    return;
                }

                var maxUploadSize = <?php echo (int) wp_max_upload_size(); ?>;
                if (fileInput.files[0].size > maxUploadSize) {
                    alert('<?php echo esc_js(__('O arquivo selecionado e muito grande.', 'wp-posts-import-export')); ?>\n\n' + 
                          '<?php echo esc_js(__('Tamanho do arquivo:', 'wp-posts-import-export')); ?> ' + (fileInput.files[0].size / 1024 / 1024).toFixed(2) + ' MB\n' +
                          '<?php echo esc_js(__('Limite do servidor:', 'wp-posts-import-export')); ?> ' + (maxUploadSize / 1024 / 1024).toFixed(2) + ' MB\n\n' +
                          '<?php echo esc_js(__('Por favor, aumente os limites do seu PHP (post_max_size e upload_max_filesize) ou exporte menos posts por vez.', 'wp-posts-import-export')); ?>');
                    return;
                }

                var formData = new FormData();
                formData.append('wp_pie_zip', fileInput.files[0]);
                formData.append('action', 'wp_pie_import_start');
                formData.append('wp_pie_import_nonce', '<?php echo esc_js(wp_create_nonce('wp_pie_import')); ?>');

                var progress = document.getElementById('wp-pie-import-progress');
                var report = document.getElementById('wp-pie-import-report');
                report.style.display = 'none';
                progress.style.display = 'block';
                btn.disabled = true;
                btn.textContent = '<?php echo esc_js(__('Importando...', 'wp-posts-import-export')); ?>';

                var barInner = progress.querySelector('.wp-pie-progress-bar-inner');
                var progressText = progress.querySelector('.wp-pie-progress-text');

                barInner.style.width = '10%';
                progressText.textContent = '<?php echo esc_js(__('Enviando e analisando arquivo...', 'wp-posts-import-export')); ?>';

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(function(r) {
                    return r.text().then(function(text) {
                        if (text === '-1') {
                            throw new Error('<?php echo esc_js(__('Falha de autenticacao (nonce invalido). Recarregue a pagina e tente novamente.', 'wp-posts-import-export')); ?>');
                        }
                        if (text === '0') {
                            throw new Error('<?php echo esc_js(__('O servidor retornou "0". Isso geralmente acontece quando o tamanho do arquivo ZIP excede o limite "post_max_size" ou "upload_max_filesize" configurado no seu servidor PHP.', 'wp-posts-import-export')); ?>');
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('<?php echo esc_js(__('Resposta invalida do servidor:', 'wp-posts-import-export')); ?> ' + text.substring(0, 500));
                        }
                    });
                })
                .then(function(response) {
                    if (!response.success) {
                        var errMsg = response.data || '<?php echo esc_js(__('Erro desconhecido.', 'wp-posts-import-export')); ?>';
                        if (!response.data) {
                            errMsg += '\n\n<?php echo esc_js(__('Resposta completa do servidor:', 'wp-posts-import-export')); ?>\n' + JSON.stringify(response, null, 2);
                        } else if (response.data && response.data.server_info) {
                            errMsg += ' (limites do servidor: POST max ' + response.data.server_info.post_max_size + ', upload max ' + response.data.server_info.upload_max + ')';
                        }
                        throw new Error(errMsg);
                    }

                    var total = response.data.total;
                    var batchSize = response.data.batch_size;
                    var importId = response.data.import_id;

                    if (total === 0) {
                        progressText.textContent = '<?php echo esc_js(__('Nenhum post encontrado para importar.', 'wp-posts-import-export')); ?>';
                        btn.disabled = false;
                        btn.textContent = '<?php echo esc_js(__('Importar', 'wp-posts-import-export')); ?>';
                        return;
                    }

                    barInner.style.width = '20%';
                    progressText.textContent = '<?php echo esc_js(__('Importando posts...', 'wp-posts-import-export')); ?>';

                    var processed = 0;
                    var results = { posts: 0, categories: 0, tags: 0, images: 0, errors: [] };

                    function processBatch() {
                        var pData = new FormData();
                        pData.append('action', 'wp_pie_import_process');
                        pData.append('import_id', importId);
                        pData.append('wp_pie_import_nonce', '<?php echo esc_js(wp_create_nonce('wp_pie_import_process')); ?>');
                        pData.append('offset', processed);

                        return fetch(ajaxurl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: pData
                        })
                        .then(function(r) {
                            return r.text().then(function(text) {
                                if (text === '-1') {
                                    throw new Error('<?php echo esc_js(__('Falha de autenticacao (nonce invalido) na etapa de importacao.', 'wp-posts-import-export')); ?>');
                                }
                                if (text === '0') {
                                    throw new Error('<?php echo esc_js(__('O servidor retornou "0". A conexao pode ter sido interrompida ou o limite de memoria foi atingido.', 'wp-posts-import-export')); ?>');
                                }
                                return JSON.parse(text);
                            });
                        })
                        .then(function(batchResponse) {
                            if (!batchResponse.success) {
                                results.errors.push(batchResponse.data || '<?php echo esc_js(__('Erro na importacao.', 'wp-posts-import-export')); ?>');
                                processed = total;
                            } else {
                                var d = batchResponse.data;
                                results.posts += d.posts;
                                results.categories += d.categories_created;
                                results.tags += d.tags_created;
                                results.images += d.images;
                                if (d.errors) {
                                    d.errors.forEach(function(e) { results.errors.push(e); });
                                }
                                processed += d.processed;

                                var pct = 20 + Math.round((processed / total) * 70);
                                barInner.style.width = pct + '%';
                                progressText.textContent = '<?php echo esc_js(__('Importando posts...', 'wp-posts-import-export')); ?> (' + processed + '/' + total + ')';
                            }

                            if (processed < total) {
                                return processBatch();
                            } else {
                                barInner.style.width = '95%';
                                progressText.textContent = '<?php echo esc_js(__('Finalizando...', 'wp-posts-import-export')); ?>';

                                results.total = total;
                                showReport(results);
                            }
                        })
                        .catch(function(err) {
                            results.errors.push(err.message || '<?php echo esc_js(__('Erro AJAX.', 'wp-posts-import-export')); ?>');
                            showReport(results);
                        });
                    }

                    return processBatch();
                })
                .catch(function(err) {
                    progressText.textContent = '<?php echo esc_js(__('Erro:', 'wp-posts-import-export')); ?> ' + err.message;
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('Importar', 'wp-posts-import-export')); ?>';
                });
            });

            function showReport(results) {
                var progress = document.getElementById('wp-pie-import-progress');
                var report = document.getElementById('wp-pie-import-report');
                var barInner = progress.querySelector('.wp-pie-progress-bar-inner');
                var progressText = progress.querySelector('.wp-pie-progress-text');

                barInner.style.width = '100%';
                progressText.textContent = '<?php echo esc_js(__('Importacao concluida.', 'wp-posts-import-export')); ?>';

                var html = '<table class="widefat striped">';
                html += '<tr><td><strong><?php echo esc_js(__('Total de posts processados:', 'wp-posts-import-export')); ?></strong></td><td>' + results.total + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js(__('Posts importados:', 'wp-posts-import-export')); ?></strong></td><td>' + results.posts + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js(__('Categorias criadas:', 'wp-posts-import-export')); ?></strong></td><td>' + results.categories + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js(__('Tags criadas:', 'wp-posts-import-export')); ?></strong></td><td>' + results.tags + '</td></tr>';
                html += '<tr><td><strong><?php echo esc_js(__('Imagens importadas:', 'wp-posts-import-export')); ?></strong></td><td>' + results.images + '</td></tr>';

                if (results.errors && results.errors.length > 0) {
                    html += '<tr><td colspan="2"><strong><?php echo esc_js(__('Erros:', 'wp-posts-import-export')); ?></strong></td></tr>';
                    results.errors.forEach(function(e) {
                        html += '<tr><td colspan="2" style="color:#cc0000;">' + e + '</td></tr>';
                    });
                }

                html += '</table>';
                report.querySelector('.wp-pie-report-content').innerHTML = html;
                report.style.display = 'block';

                var btn = document.getElementById('wp-pie-import-btn');
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Importar', 'wp-posts-import-export')); ?>';
            }
        })();
        </script>
        <?php
    }

    private function handle_export() {
        $category = isset($_POST['category']) ? absint($_POST['category']) : 0;
        $date_start = isset($_POST['date_start']) ? sanitize_text_field($_POST['date_start']) : '';
        $date_end = isset($_POST['date_end']) ? sanitize_text_field($_POST['date_end']) : '';

        $exporter = new Exporter();
        $result = $exporter->export($category, $date_start, $date_end);

        $redirect_url = admin_url('tools.php?page=wp-posts-import-export&tab=export');

        if (is_wp_error($result)) {
            $redirect_url = add_query_arg([
                'wp_pie_error' => $result->get_error_message(),
            ], $redirect_url);
        } else {
            $redirect_url = add_query_arg([
                'wp_pie_success' => '1',
                'wp_pie_file'    => $result['file_url'],
                'wp_pie_count'   => intval($result['post_count']),
            ], $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function admin_notices() {
        if (!isset($_GET['page']) || 'wp-posts-import-export' !== $_GET['page']) {
            return;
        }

        if (isset($_GET['wp_pie_success']) && '1' === $_GET['wp_pie_success']) {
            $file_url = isset($_GET['wp_pie_file']) ? esc_url_raw(wp_unslash($_GET['wp_pie_file'])) : '';
            $count = isset($_GET['wp_pie_count']) ? intval($_GET['wp_pie_count']) : 0;

            if (!empty($file_url)) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    wp_kses_post(sprintf(
                        __('Exportacao concluida com sucesso! %d posts exportados. <a href="%s" class="button button-secondary" style="margin-left: 10px;">Baixar ZIP</a>', 'wp-posts-import-export'),
                        $count,
                        esc_url($file_url)
                    ))
                );
            }
        }

        if (isset($_GET['wp_pie_error'])) {
            $error_msg = sanitize_text_field(wp_unslash($_GET['wp_pie_error']));
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(
                    __('Falha na exportacao: %s', 'wp-posts-import-export'),
                    $error_msg
                ))
            );
        }
    }

    public function ajax_import_start() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permissao negada.', 'wp-posts-import-export'));
            }

            check_ajax_referer('wp_pie_import', 'wp_pie_import_nonce');

            if (!isset($_FILES['wp_pie_zip'])) {
                wp_send_json_error(__('Nenhum arquivo foi enviado.', 'wp-posts-import-export'));
            }

            $file = $_FILES['wp_pie_zip'];

            if (UPLOAD_ERR_OK !== $file['error']) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE   => sprintf(
                        __('O arquivo excede o limite de upload do PHP (%s).', 'wp-posts-import-export'),
                        ini_get('upload_max_filesize')
                    ),
                    UPLOAD_ERR_FORM_SIZE  => __('O arquivo excede o limite definido no formulario.', 'wp-posts-import-export'),
                    UPLOAD_ERR_PARTIAL    => __('O upload foi feito apenas parcialmente.', 'wp-posts-import-export'),
                    UPLOAD_ERR_NO_FILE    => __('Nenhum arquivo foi selecionado.', 'wp-posts-import-export'),
                    UPLOAD_ERR_NO_TMP_DIR => __('Falta o diretorio temporario do PHP.', 'wp-posts-import-export'),
                    UPLOAD_ERR_CANT_WRITE => __('Falha ao escrever o arquivo no disco.', 'wp-posts-import-export'),
                    UPLOAD_ERR_EXTENSION  => __('O upload foi interrompido por uma extensao.', 'wp-posts-import-export'),
                ];

                $error_msg = isset($upload_errors[$file['error']])
                    ? $upload_errors[$file['error']]
                    : __('Erro desconhecido no upload.', 'wp-posts-import-export');

                wp_send_json_error($error_msg);
            }

            $info = [
                'post_max_size' => ini_get('post_max_size'),
                'upload_max'    => ini_get('upload_max_filesize'),
                'file_size'     => size_format($file['size']),
            ];

            $importer = new Importer();
            $result = $importer->start_import($file);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            $result['server_info'] = $info;
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                __('Erro interno do servidor: %s em %s linha %d', 'wp-posts-import-export'),
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            ));
        }
    }

    public function ajax_import_process() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permissao negada.', 'wp-posts-import-export'));
            }

            check_ajax_referer('wp_pie_import_process', 'wp_pie_import_nonce');

            $import_id = isset($_POST['import_id']) ? sanitize_key($_POST['import_id']) : '';
            $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

            if (empty($import_id)) {
                wp_send_json_error(__('ID de importacao invalido.', 'wp-posts-import-export'));
            }

            $importer = new Importer();
            $result = $importer->process_batch($import_id, $offset);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                __('Erro interno do servidor: %s em %s linha %d', 'wp-posts-import-export'),
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            ));
        }
    }
}
