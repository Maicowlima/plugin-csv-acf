<?php
/**
 * Classe de handlers AJAX do CSV Importer Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSV_Importer_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_csv_get_acf_fields', array($this, 'get_acf_fields'));
        add_action('wp_ajax_csv_process_import', array($this, 'process_import'));
        add_action('wp_ajax_csv_get_current_post_type', array($this, 'get_current_post_type'));
    }
    
    public function get_current_post_type() {
        check_ajax_referer('csv_importer_pro_nonce', 'nonce');
        
        $config = new CSV_Importer_Config();
        $post_type = $config->get_setting('post_type');
        
        if ($post_type) {
            wp_send_json_success($post_type);
        } else {
            wp_send_json_error('Post type não configurado');
        }
    }
    
    public function get_acf_fields() {
        check_ajax_referer('csv_importer_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissões insuficientes');
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $html = $this->generate_acf_fields_html($post_type);
        
        wp_send_json_success($html);
    }
    
    private function generate_acf_fields_html($post_type) {
        // Verificar se ACF está disponível de forma segura
        if (!class_exists('ACF') || !function_exists('acf_get_field_groups')) {
            return '<p>Plugin ACF não está ativo ou não foi carregado ainda.</p>';
        }
        
        // Aguardar ACF estar totalmente carregado
        if (!did_action('acf/init')) {
            return '<p>ACF ainda não foi inicializado. Recarregue a página.</p>';
        }
        
        $field_groups = acf_get_field_groups(array('post_type' => $post_type));
        
        if (empty($field_groups)) {
            return '<p>Nenhum campo ACF encontrado para este tipo de post.</p>';
        }
        
        $html = '<h3>Campos ACF</h3>';
        
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);
            
            if (empty($fields)) {
                continue;
            }
            
            $html .= '<h4>' . esc_html($group['title']) . '</h4>';
            $html .= '<table class="form-table">';
            
            foreach ($fields as $field) {
                if ($field['type'] === 'tab') {
                    continue;
                }
                
                if ($field['type'] === 'repeater') {
                    $html .= $this->generate_repeater_field_html($field);
                } else {
                    $html .= $this->generate_simple_field_html($field);
                }
            }
            
            $html .= '</table>';
        }
        
        return $html;
    }
    
    private function generate_simple_field_html($field) {
        $html = '<tr>';
        $html .= '<th scope="row">' . esc_html($field['label']) . '</th>';
        $html .= '<td>';
        $html .= '<select name="acf_' . esc_attr($field['name']) . '" class="csv-column-select">';
        $html .= '<option value="">Selecionar coluna</option>';
        $html .= '</select>';
        $html .= '<p class="description">Tipo: ' . esc_html($field['type']) . '</p>';
        $html .= '</td>';
        $html .= '</tr>';
        
        return $html;
    }
    
    private function generate_repeater_field_html($field) {
        if (empty($field['sub_fields'])) {
            return '';
        }
        
        $html = '<tr>';
        $html .= '<td colspan="2">';
        $html .= '<div class="csv-repeater-field">';
        $html .= '<h5>Repetidor: ' . esc_html($field['label']) . '</h5>';
        
        $html .= '<div class="csv-repeater-items">';
        
        // Primeiro item do repetidor
        $html .= '<div class="csv-repeater-item" data-repeater="' . esc_attr($field['name']) . '" data-index="0">';
        $html .= '<div class="csv-repeater-item-header">';
        $html .= '<h6>Item 1</h6>';
        $html .= '<button type="button" class="button-link csv-remove-repeater-item">Remover</button>';
        $html .= '</div>';
        
        $html .= '<table class="form-table">';
        foreach ($field['sub_fields'] as $sub_field) {
            if ($sub_field['type'] === 'tab') {
                continue;
            }
            
            $html .= '<tr>';
            $html .= '<th scope="row">' . esc_html($sub_field['label']) . '</th>';
            $html .= '<td>';
            $html .= '<select name="repeater_' . esc_attr($field['name']) . '_0_' . esc_attr($sub_field['name']) . '" class="csv-column-select">';
            $html .= '<option value="">Selecionar coluna</option>';
            $html .= '</select>';
            $html .= '<p class="description">Tipo: ' . esc_html($sub_field['type']) . '</p>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        
        $html .= '</div>'; // .csv-repeater-item
        
        $html .= '</div>'; // .csv-repeater-items
        
        $html .= '<button type="button" class="button csv-add-repeater-item" data-repeater="' . esc_attr($field['name']) . '">Adicionar Item</button>';
        
        $html .= '</div>'; // .csv-repeater-field
        $html .= '</td>';
        $html .= '</tr>';
        
        return $html;
    }
    
    public function process_import() {
        check_ajax_referer('csv_importer_pro_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissões insuficientes');
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Erro no upload do arquivo');
        }
        
        try {
            $processor = new CSV_Importer_Processor();
            $results = $processor->process_file($_FILES['csv_file']['tmp_name'], $_POST);
            
            $message = sprintf(
                'Importação concluída!\n\n✅ Posts importados: %d\n❌ Erros: %d',
                $results['imported'],
                count($results['errors'])
            );
            
            if (!empty($results['errors'])) {
                $message .= "\n\nPrimeiros erros:\n" . implode("\n", array_slice($results['errors'], 0, 5));
            }
            
            if (!empty($results['debug'])) {
                $message .= "\n\n=== DEBUG ===\n" . implode("\n", $results['debug']);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'imported' => $results['imported'],
                'errors' => $results['errors'],
                'debug' => $results['debug']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Erro durante importação: ' . $e->getMessage());
        }
    }
}