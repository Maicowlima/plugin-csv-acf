<?php
/**
 * Classe de administração do CSV Importer Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSV_Importer_Admin {
    
    private $config;
    
    public function __construct() {
        $this->config = new CSV_Importer_Config();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'CSV Importer Pro',
            'CSV Importer Pro',
            'manage_options',
            'csv-importer-pro',
            array($this, 'settings_page'),
            'dashicons-database-import',
            30
        );
        
        add_submenu_page(
            'csv-importer-pro',
            'Configurações',
            'Configurações',
            'manage_options',
            'csv-importer-pro',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'csv-importer-pro',
            'Importar CSV',
            'Importar CSV',
            'manage_options',
            'csv-importer-import',
            array($this, 'import_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'csv-importer') === false) {
            return;
        }
        
        // Enqueue WordPress media scripts
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        // ATUALIZADO: Carregar arquivo admin.js em vez de inline
        wp_enqueue_script(
            'csv-importer-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', // Sobe um nível para sair de /includes/
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localizar script com dados necessários
        wp_localize_script('csv-importer-admin', 'csvImporterPro', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('csv_importer_pro_nonce')
        ));
        
        // Adicionar CSS inline
        wp_add_inline_style('wp-admin', $this->get_inline_css());
    }
    
    // REMOVIDO: método get_inline_js() não é mais necessário
    
    private function get_inline_css() {
        return '
        .csv-importer-wrap { max-width: 1200px; }
        .csv-section { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; }
        .csv-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .csv-mapping-group { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #0073aa; }
        .csv-mapping-group h3 { margin-top: 0; color: #0073aa; }
        .csv-column-select { min-width: 250px; }
        .csv-image-selector { display: flex; align-items: center; gap: 10px; }
        .csv-image-preview img { max-width: 150px; height: auto; border-radius: 4px; border: 1px solid #ddd; margin-top: 10px; }
        .csv-repeater-field { border: 2px solid #0073aa; border-radius: 6px; padding: 15px; margin: 15px 0; background: #f0f8ff; }
        .csv-repeater-field h5 { margin: 0 0 15px; padding: 10px 15px; background: #0073aa; color: white; border-radius: 4px; font-weight: bold; }
        .csv-repeater-item { border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0; background: white; }
        .csv-repeater-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .csv-repeater-item-header h6 { margin: 0; font-weight: bold; }
        .csv-remove-repeater-item { color: #a00; text-decoration: none; }
        .csv-remove-repeater-item:hover { color: #dc3232; }
        .csv-add-repeater-item { margin-top: 10px; }
        .csv-progress-bar { width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .csv-progress-fill { height: 100%; background: #0073aa; width: 0%; transition: width 0.3s ease; }
        .csv-messages { margin: 20px 0; padding: 15px; border-radius: 4px; white-space: pre-line; font-family: monospace; font-size: 13px; }
        .csv-messages.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .csv-messages.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        ';
    }
    
    public function settings_page() {
        $post_type = $this->config->get_setting('post_type');
        $post_status = $this->config->get_setting('post_status');
        $featured_image_id = $this->config->get_setting('featured_image_id');
        $batch_size = $this->config->get_setting('batch_size');
        $delimiter = $this->config->get_setting('delimiter');
        $enclosure = $this->config->get_setting('enclosure');
        ?>
        
        <div class="wrap csv-importer-wrap">
            <h1>CSV Importer Pro - Configurações</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('csv_settings', 'csv_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="post_type">Tipo de Post</label>
                            </th>
                            <td>
                                <select name="post_type" id="post_type" class="regular-text" required>
                                    <option value="">Selecionar tipo de post</option>
                                    <?php
                                    $post_types = get_post_types(array('public' => true), 'objects');
                                    foreach ($post_types as $pt) {
                                        printf(
                                            '<option value="%s"%s>%s</option>',
                                            esc_attr($pt->name),
                                            selected($post_type, $pt->name, false),
                                            esc_html($pt->label)
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description">Tipo de post onde os dados serão importados.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="post_status">Status dos Posts</label>
                            </th>
                            <td>
                                <select name="post_status" id="post_status" class="regular-text">
                                    <option value="publish"<?php selected($post_status, 'publish'); ?>>Publicado</option>
                                    <option value="draft"<?php selected($post_status, 'draft'); ?>>Rascunho</option>
                                    <option value="pending"<?php selected($post_status, 'pending'); ?>>Pendente</option>
                                    <option value="private"<?php selected($post_status, 'private'); ?>>Privado</option>
                                </select>
                                <p class="description">Status padrão para todos os posts importados.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="featured_image">Imagem Destacada Padrão</label>
                            </th>
                            <td>
                                <div class="csv-image-selector">
                                    <input type="hidden" name="featured_image_id" id="featured_image_id" value="<?php echo esc_attr($featured_image_id); ?>">
                                    <button type="button" id="select_featured_image" class="button">Selecionar Imagem</button>
                                    <button type="button" id="remove_featured_image" class="button" style="<?php echo $featured_image_id ? '' : 'display:none;'; ?>">Remover</button>
                                    
                                    <div id="featured_image_preview" class="csv-image-preview">
                                        <?php
                                        if ($featured_image_id) {
                                            $image_url = wp_get_attachment_image_url($featured_image_id, 'thumbnail');
                                            if ($image_url) {
                                                echo '<img src="' . esc_url($image_url) . '" alt="Imagem destacada">';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <p class="description">Imagem que será definida como destacada para todos os posts importados.</p>
                            </td>
                        </tr>
                        
                    </tbody>
                </table>
                
                <?php submit_button('Salvar Configurações', 'primary', 'csv_save_settings'); ?>
            </form>
        </div>
        
        <?php
    }
    
    public function import_page() {
        $post_type = $this->config->get_setting('post_type');
        
        if (empty($post_type)) {
            ?>
            <div class="wrap">
                <h1>CSV Importer Pro - Importar</h1>
                <div class="notice notice-error">
                    <p>É necessário configurar o tipo de post antes de importar. <a href="<?php echo admin_url('admin.php?page=csv-importer-pro'); ?>">Ir para configurações</a></p>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        
        <div class="wrap csv-importer-wrap">
            <h1>CSV Importer Pro - Importar Dados</h1>
            
            <div class="csv-import-container">
                <form id="csv-import-form" method="post" enctype="multipart/form-data">
                    
                    <!-- Upload do arquivo -->
                    <div class="csv-section">
                        <h2>1. Selecionar Arquivo CSV</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="csv_file">Arquivo CSV</label>
                                </th>
                                <td>
                                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                                    <p class="description">Selecione o arquivo CSV para importação.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Seção de mapeamento (oculta inicialmente) -->
                    <div id="mapping-section" class="csv-section" style="display: none;">
                        <h2>2. Mapeamento de Campos</h2>
                        
                        <!-- Título do post -->
                        <div class="csv-mapping-group">
                            <h3>Título do Post</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Primeira parte</th>
                                    <td>
                                        <select name="title_column_1" class="csv-column-select">
                                            <option value="">Selecionar coluna</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Segunda parte (opcional)</th>
                                    <td>
                                        <select name="title_column_2" class="csv-column-select">
                                            <option value="">Selecionar coluna</option>
                                        </select>
                                        <p class="description">As duas partes serão unidas por " - "</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Campos nativos -->
                        <div class="csv-mapping-group">
                            <h3>Campos Nativos</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Conteúdo</th>
                                    <td>
                                        <select name="content_column" class="csv-column-select">
                                            <option value="">Selecionar coluna</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Resumo (Excerpt)</th>
                                    <td>
                                        <select name="excerpt_column" class="csv-column-select">
                                            <option value="">Selecionar coluna</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Taxonomias -->
                        <div class="csv-mapping-group">
                            <h3>Taxonomias</h3>
                            <table class="form-table">
                                <?php
                                $taxonomies = get_object_taxonomies($post_type, 'objects');
                                if (!empty($taxonomies)) {
                                    foreach ($taxonomies as $taxonomy) {
                                        ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html($taxonomy->label); ?></th>
                                            <td>
                                                <select name="taxonomy_<?php echo esc_attr($taxonomy->name); ?>" class="csv-column-select">
                                                    <option value="">Selecionar coluna</option>
                                                </select>
                                                <p class="description">Separe múltiplos termos com vírgula</p>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="2">Nenhuma taxonomia encontrada para este tipo de post.</td></tr>';
                                }
                                ?>
                            </table>
                        </div>
                        
                        <!-- Campos ACF -->
                        <div id="acf-fields-container" class="csv-mapping-group">
                            <!-- Campos ACF serão carregados via AJAX -->
                        </div>
                    </div>
                    
                    <!-- Resultados -->
                    <div id="import-results" class="csv-section" style="display: none;">
                        <h2>Resultados da Importação</h2>
                        <div id="import-progress" class="csv-progress">
                            <div class="csv-progress-bar">
                                <div class="csv-progress-fill"></div>
                            </div>
                            <p class="csv-progress-text">Processando...</p>
                        </div>
                        <div id="import-messages" class="csv-messages"></div>
                    </div>
                    
                    <!-- Boto de importação -->
                    <div id="import-actions" style="display: none;">
                        <?php submit_button('Iniciar Importação', 'primary large', 'start_import'); ?>
                    </div>
                    
                </form>
            </div>
        </div>
        
        <?php
    }
}