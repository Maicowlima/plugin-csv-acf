<?php
/**
 * Plugin Name: CSV Importer Pro - Labbo
 * Description: Plugin para importar CSV com suporte completo a ACF e repetidores
 * Version: 1.0.0
 * Author: Maicon Lima - Labbo
 * Text Domain: csv-importer-pro-labbo
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('CSV_IMPORTER_PRO_VERSION', '2.0.0');
define('CSV_IMPORTER_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CSV_IMPORTER_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Classe principal do plugin
class CSV_Importer_Pro {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Carregar arquivos necessários
        $this->load_dependencies();
        
        // Inicializar componentes
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once CSV_IMPORTER_PRO_PLUGIN_DIR . 'includes/class-csv-config.php';
        require_once CSV_IMPORTER_PRO_PLUGIN_DIR . 'includes/class-csv-processor.php';
        require_once CSV_IMPORTER_PRO_PLUGIN_DIR . 'includes/class-csv-admin.php';
        require_once CSV_IMPORTER_PRO_PLUGIN_DIR . 'includes/class-csv-ajax.php';
    }
    
    private function init_hooks() {
        // Inicializar componentes
        new CSV_Importer_Config();
        new CSV_Importer_Admin();
        new CSV_Importer_Ajax();
    }
}

// Inicializar plugin
CSV_Importer_Pro::get_instance();