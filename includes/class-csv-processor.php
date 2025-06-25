<?php
/**
 * Classe processadora de CSV do CSV Importer Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class CSV_Importer_Processor {
    
    private $config;
    private $debug_messages = array();
    
    public function __construct() {
        $this->config = new CSV_Importer_Config();
    }
    
    public function process_file($file_path, $mapping_data) {
        $this->debug_messages = array();
        
        try {
            // Ler e processar CSV
            $csv_data = $this->read_csv($file_path);
            
            if (empty($csv_data)) {
                throw new Exception('Arquivo CSV vazio ou inválido');
            }
            
            $headers = array_shift($csv_data);
            $this->debug('Headers encontrados: ' . json_encode($headers));
            
            // Processar cada linha
            $results = array(
                'imported' => 0,
                'errors' => array(),
                'debug' => array()
            );
            
            foreach ($csv_data as $row_index => $row) {
                try {
                    $post_id = $this->process_row($row, $headers, $mapping_data, $row_index);
                    if ($post_id) {
                        $results['imported']++;
                        $this->debug("Linha " . ($row_index + 1) . ": Post criado com ID $post_id");
                    }
                } catch (Exception $e) {
                    $results['errors'][] = "Linha " . ($row_index + 1) . ": " . $e->getMessage();
                }
            }
            
            $results['debug'] = $this->debug_messages;
            return $results;
            
        } catch (Exception $e) {
            return array(
                'imported' => 0,
                'errors' => array($e->getMessage()),
                'debug' => $this->debug_messages
            );
        }
    }
    private function read_csv($file_path) {
    $delimiter = ';';
    $enclosure = '"';
    $content = file_get_contents($file_path);
    if ($content === false) {
        throw new Exception('Não foi possível ler o arquivo CSV');
    }

    // Normalizar quebra de linha
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // (Opcional) Garantir UTF-8
    $content = mb_convert_encoding($content, 'UTF-8', 'auto');

    $lines = explode("\n", $content);
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });

    if (empty($lines)) {
        throw new Exception('Arquivo CSV está vazio');
    }

    $csv_data = [];
    foreach ($lines as $line) {
        $row = str_getcsv($line, $delimiter, $enclosure);
        
        // (Opcional) Trim leve nas células
        $row = array_map(function($cell) {
            return trim($cell);
        }, $row);

        if (!empty(array_filter($row))) {
            $csv_data[] = $row;
        }
    }

    return $csv_data;
}

    
    private function process_row($row, $headers, $mapping, $row_index) {
        $this->debug("=== Processando linha " . ($row_index + 1) . " ===");
        $this->debug("Dados da linha: " . json_encode($row));
        
        // Construir título
        $title = $this->build_title($row, $headers, $mapping);
        if (empty($title)) {
            $title = 'Post Importado ' . ($row_index + 1);
        }
        
        $this->debug("Título construído: '$title'");
        
        // Preparar dados do post
        $post_data = array(
            'post_type' => $this->config->get_setting('post_type'),
            'post_status' => $this->config->get_setting('post_status'),
            'post_title' => $title
        );
        
        // Adicionar conteúdo se mapeado
        if (!empty($mapping['content_column'])) {
            $content = $this->get_mapped_value($mapping['content_column'], $row, $headers);
            if ($content) {
                $post_data['post_content'] = wp_kses_post($content);
                $this->debug("Conteúdo definido: " . substr($content, 0, 100) . "...");
            }
        }
        
        // Adicionar excerpt se mapeado
        if (!empty($mapping['excerpt_column'])) {
            $excerpt = $this->get_mapped_value($mapping['excerpt_column'], $row, $headers);
            if ($excerpt) {
                $post_data['post_excerpt'] = sanitize_textarea_field($excerpt);
                $this->debug("Excerpt definido: " . substr($excerpt, 0, 50) . "...");
            }
        }
        
        // Criar post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Erro ao criar post: ' . $post_id->get_error_message());
        }
        
        // Definir imagem destacada se configurada
        $featured_image = $this->config->get_setting('featured_image_id');
        if ($featured_image) {
            set_post_thumbnail($post_id, $featured_image);
            $this->debug("Imagem destacada definida: ID $featured_image");
        }
        
        // Processar taxonomias
        $this->process_taxonomies($post_id, $row, $headers, $mapping);
        
        
        $this->process_acf_fields($post_id, $row, $headers, $mapping);
        return $post_id;
    }
    
    private function build_title($row, $headers, $mapping) {
        $title_parts = array();
        
        $this->debug("=== CONSTRUINDO TÍTULO ===");
        $this->debug("Headers disponíveis: " . json_encode($headers));
        $this->debug("Row data: " . json_encode($row));
        $this->debug("Mapping recebido: " . json_encode($mapping));
        
        // Primeira parte do título
        if (!empty($mapping['title_column_1'])) {
            $part1 = $this->get_mapped_value($mapping['title_column_1'], $row, $headers);
            $this->debug("Título parte 1 - Mapping: {$mapping['title_column_1']}, Valor: '$part1'");
            if ($part1) {
                $title_parts[] = $part1;
            }
        } else {
            $this->debug("title_column_1 não definido no mapping");
        }
        
        // Segunda parte do título
        if (!empty($mapping['title_column_2'])) {
            $part2 = $this->get_mapped_value($mapping['title_column_2'], $row, $headers);
            $this->debug("Título parte 2 - Mapping: {$mapping['title_column_2']}, Valor: '$part2'");
            if ($part2) {
                $title_parts[] = $part2;
            }
        } else {
            $this->debug("title_column_2 no definido no mapping");
        }
        
        $final_title = implode(' - ', $title_parts);
        $this->debug("Título final construído: '$final_title'");
        
        return $final_title;
    }
    
    private function get_mapped_value($mapping_key, $row, $headers) {
        $this->debug("get_mapped_value chamado com: mapping_key='$mapping_key'");
        
        // Valores fixos
        if ($mapping_key === 'fixed_true') {
            $this->debug("Retornando valor fixo: '1'");
            return '1';
        }
        if ($mapping_key === 'fixed_false') {
            $this->debug("Retornando valor fixo: '0'");
            return '0';
        }
        
        // Nome da coluna como valor
        if (strpos($mapping_key, 'header_') === 0) {
            $index = intval(str_replace('header_', '', $mapping_key));
            $value = isset($headers[$index]) ? $headers[$index] : '';
            $this->debug("Header valor para ndice $index: '$value'");
            return $value;
        }
        
        // Valor da coluna
        if (is_numeric($mapping_key)) {
            $index = intval($mapping_key);
            $value = isset($row[$index]) ? trim($row[$index]) : '';
            $this->debug("Valor da coluna $index: '$value' (total colunas: " . count($row) . ")");
            return $value;
        }
        
        $this->debug("Mapping key não reconhecido: '$mapping_key'");
        return '';
    }
    
    private function process_taxonomies($post_id, $row, $headers, $mapping) {
        foreach ($mapping as $key => $column) {
            if (strpos($key, 'taxonomy_') === 0 && !empty($column)) {
                $taxonomy = str_replace('taxonomy_', '', $key);
                $terms_value = $this->get_mapped_value($column, $row, $headers);
                
                if ($terms_value) {
                    $terms = array_map('trim', explode(',', $terms_value));
                    $term_ids = array();
                    
                    foreach ($terms as $term_name) {
                        if (empty($term_name)) continue;
                        
                        $term = get_term_by('name', $term_name, $taxonomy);
                        if (!$term) {
                            $result = wp_insert_term($term_name, $taxonomy);
                            if (!is_wp_error($result)) {
                                $term_ids[] = $result['term_id'];
                            }
                        } else {
                            $term_ids[] = $term->term_id;
                        }
                    }
                    
                    if (!empty($term_ids)) {
                        wp_set_object_terms($post_id, $term_ids, $taxonomy);
                        $this->debug("Taxonomia '$taxonomy': " . count($term_ids) . " termos atribuídos");
                    }
                }
            }
        }
    }
    
    private function process_acf_fields($post_id, $row, $headers, $mapping) {
    if (!function_exists('update_field')) {
        $this->debug("❌ Função ACF 'update_field' não está disponível.");
        return;
    }

    // Campos simples
    foreach ($mapping as $key => $column) {
        if (strpos($key, 'acf_') === 0 && !empty($column)) {
            $field_name = str_replace('acf_', '', $key);
            $field_value = $this->get_mapped_value($column, $row, $headers);

            if ($field_value !== '') {
                update_field($field_name, $field_value, $post_id);
                $this->debug("✅ Campo ACF '$field_name' atualizado com: '$field_value'");
            } else {
                $this->debug("⚠️ Campo ACF '$field_name' recebeu valor vazio.");
            }
        }
    }

    // Campos repetidores
    $this->process_repeater_fields($post_id, $row, $headers, $mapping);
}


private function process_repeater_fields($post_id, $row, $headers, $mapping) {
    if (!function_exists('add_row')) {
        $this->debug("❌ Função ACF 'add_row' não está disponível.");
        return;
    }

    $repeaters = [];

    foreach ($mapping as $key => $column) {
        if (preg_match('/^repeater_([^_]+)_(\d+)_(.+)$/', $key, $matches)) {
            $field_name = $matches[1];
            $index = intval($matches[2]);
            $subfield = $matches[3];
            $value = $this->get_mapped_value($column, $row, $headers);

            if ($value !== '') {
                $repeaters[$field_name][$index][$subfield] = $value;
            }
        }
    }

    foreach ($repeaters as $field_name => $items) {
        delete_field($field_name, $post_id); // limpa antes de adicionar

        ksort($items);
        $added = 0;

        foreach ($items as $item) {
            $filtered = array_filter($item, fn($v) => $v !== '');
            if (!empty($filtered)) {
                add_row($field_name, $filtered, $post_id);
                $this->debug("➕ Linha adicionada ao repetidor '$field_name': " . json_encode($filtered));
                $added++;
            }
        }

        $this->debug("✅ Total de $added linhas adicionadas ao repetidor '$field_name'");
    }
}


    
    private function debug($message) {
        $this->debug_messages[] = $message;
    }
    
    public function get_debug_messages() {
        return $this->debug_messages;
    }
}