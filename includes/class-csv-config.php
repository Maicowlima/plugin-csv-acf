<?php
/**
 * Classe de configurações do CSV Importer Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSV_Importer_Config {
    
    private $default_settings = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'featured_image_id' => '',
        'batch_size' => 50,
        'delimiter' => ';',
        'enclosure' => '"'
    );
    
    public function __construct() {
        add_action('admin_init', array($this, 'save_settings'));
    }
    
    public function get_setting($key, $default = null) {
        $settings = get_option('csv_importer_pro_settings', $this->default_settings);
        return isset($settings[$key]) ? $settings[$key] : ($default !== null ? $default : $this->default_settings[$key]);
    }
    
    public function update_setting($key, $value) {
        $settings = get_option('csv_importer_pro_settings', $this->default_settings);
        $settings[$key] = $value;
        update_option('csv_importer_pro_settings', $settings);
    }
    
    public function save_settings() {
        if (!isset($_POST['csv_save_settings']) || !wp_verify_nonce($_POST['csv_nonce'], 'csv_settings')) {
            return;
        }
        
        $settings = array(
            'post_type' => sanitize_text_field($_POST['post_type']),
            'post_status' => sanitize_text_field($_POST['post_status']),
            'featured_image_id' => intval($_POST['featured_image_id']),
            'batch_size' => intval($_POST['batch_size']),
            'delimiter' => sanitize_text_field($_POST['delimiter']),
            'enclosure' => sanitize_text_field($_POST['enclosure'])
        );
        
        update_option('csv_importer_pro_settings', $settings);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
        });
    }
}