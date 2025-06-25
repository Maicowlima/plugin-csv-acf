(function($) {
    'use strict';

    // Objeto principal - agora como window.CSVImporterPro para acessibilidade global
    window.CSVImporterPro = {
        csvHeaders: [],

        init: function() {
            console.log('CSV Importer Pro: Inicializando...');
            this.bindEvents();
            this.loadACFFields();
        },

        bindEvents: function() {
            var self = this;
            console.log('Configurando eventos...');

            // Seletor de imagem destacada
            $(document).on('click', '#select_featured_image', function(e) {
                e.preventDefault();
                console.log('Abrindo seletor de imagem...');
                self.selectFeaturedImage();
            });

            // Remover imagem destacada
            $(document).on('click', '#remove_featured_image', function(e) {
                e.preventDefault();
                self.removeFeaturedImage();
            });

            // Upload do arquivo CSV
            $(document).on('change', '#csv_file', function() {
                console.log('Arquivo CSV selecionado');
                self.handleCSVUpload.call(this);
            });

            // Eventos dos repetidores
            $(document).on('click', '.csv-remove-repeater-item', function(e) {
                e.preventDefault();
                console.log('🗑️ Remover item clicado!');
                self.removeRepeaterItem(e);
            });

            $(document).on('click', '.csv-add-repeater-item', function(e) {
                e.preventDefault();
                console.log('🔥 Adicionar item clicado!', typeof self.addRepeaterItem);
                self.addRepeaterItem(e);
            });

            // Submit do formulário
            $(document).on('submit', '#csv-import-form', function(e) {
                e.preventDefault();
                console.log('Formulário enviado');
                self.handleFormSubmit(e);
            });

            // Mudança de post type
            $(document).on('change', '#post_type', function() {
                console.log('Post type alterado para:', $(this).val());
                self.handlePostTypeChange();
            });
        },

        selectFeaturedImage: function() {
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Erro: WordPress Media Library não está disponível');
                return;
            }

            var mediaUploader = wp.media({
                title: 'Selecionar Imagem',
                button: { text: 'Usar esta imagem' },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#featured_image_id').val(attachment.id);
                $('#featured_image_preview').html('<img src="' + attachment.url + '" style="max-width:150px;" alt="Imagem destacada">');
                $('#remove_featured_image').show();
            });

            mediaUploader.open();
        },

        removeFeaturedImage: function() {
            $('#featured_image_id').val('');
            $('#featured_image_preview').empty();
            $('#remove_featured_image').hide();
        },

        handleCSVUpload: function() {
            var file = this.files[0];
            if (!file || !file.name.toLowerCase().endsWith('.csv')) {
                alert('Por favor, selecione um arquivo CSV válido');
                return;
            }

            var reader = new FileReader();
            var self = window.CSVImporterPro;

            reader.onload = function(e) {
                try {
                    var content = e.target.result;
                    var lines = content.split(/\r?\n/);

                    if (lines.length < 2) {
                        alert('Arquivo CSV deve ter pelo menos 2 linhas (cabeçalho + dados)');
                        return;
                    }

                    // Parse das headers do CSV
                    self.csvHeaders = self.parseCSVLine(lines[0]);
                    console.log('Headers encontradas:', self.csvHeaders);

                    self.updateColumnSelects();

                    $('#mapping-section').slideDown();
                    $('#import-actions').slideDown();

                    // Carregar campos ACF
                    console.log('📁 CSV carregado, carregando campos ACF...');
                    self.loadACFFields();

                } catch (error) {
                    console.error('Erro ao processar CSV:', error);
                    alert('Erro ao processar arquivo CSV: ' + error.message);
                }
            };

            reader.onerror = function() {
                alert('Erro ao ler o arquivo CSV');
            };

            reader.readAsText(file);
        },

        parseCSVLine: function(line) {
            var result = [], inQuotes = false, currentField = '';

            for (var i = 0; i < line.length; i++) {
                var char = line[i];

                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ';' && !inQuotes) {
                    result.push(currentField.trim());
                    currentField = '';
                } else {
                    currentField += char;
                }
            }

            result.push(currentField.trim());
            return result.map(field => field.replace(/^"(.*)"$/, '$1'));
        },

        updateColumnSelects: function(specificSelect) {
            console.log('Atualizando selects de colunas...');
            
            var options = '<option value="">Selecionar coluna</option>';

            options += '<optgroup label="Valores Fixos">';
            options += '<option value="fixed_true">Verdadeiro</option>';
            options += '<option value="fixed_false">Falso</option>';
            options += '</optgroup>';

            if (this.csvHeaders.length > 0) {
                options += '<optgroup label="Colunas do CSV">';
                this.csvHeaders.forEach((header, index) => {
                    options += '<option value="' + index + '">' + header + '</option>';
                });
                options += '</optgroup>';

                options += '<optgroup label="Usar nome da coluna como valor">';
                this.csvHeaders.forEach((header, index) => {
                    options += '<option value="header_' + index + '">Nome: ' + header + '</option>';
                });
                options += '</optgroup>';
            }

            if (specificSelect) {
                // Atualizar apenas um select específico
                specificSelect.html(options);
            } else {
                // Atualizar todos os selects, preservando valores selecionados
                $('.csv-column-select').each(function() {
                    var currentValue = $(this).val();
                    $(this).html(options);
                    if (currentValue) {
                        $(this).val(currentValue);
                    }
                });
            }
        },

        loadACFFields: function() {
            var postType = $('#post_type').val();
            console.log('Carregando ACF para post type:', postType);

            if (!postType) {
                console.log('Post type não definido, tentando pegar do backend...');
                // Tentar pegar post type do backend
                var self = this;
                $.ajax({
                    url: csvImporterPro.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'csv_get_current_post_type',
                        nonce: csvImporterPro.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            postType = response.data;
                            console.log('Post type do backend:', postType);
                            self.loadACFFieldsForType(postType);
                        } else {
                            console.log('Nenhum post type configurado no backend');
                        }
                    }
                });
                return;
            }

            this.loadACFFieldsForType(postType);
        },

        loadACFFieldsForType: function(postType) {
            console.log('Carregando campos ACF para:', postType);
            var self = this;

            $('#acf-fields-container').html('<p>Carregando campos ACF...</p>');

            $.ajax({
                url: csvImporterPro.ajax_url,
                type: 'POST',
                data: {
                    action: 'csv_get_acf_fields',
                    post_type: postType,
                    nonce: csvImporterPro.nonce
                },
                success: function(response) {
                    console.log('Resposta ACF:', response);
                    if (response.success) {
                        $('#acf-fields-container').html(response.data);
                        
                        // Verificar se botões foram criados e configurar eventos
                        setTimeout(function() {
                            var addButtons = $('.csv-add-repeater-item');
                            var removeButtons = $('.csv-remove-repeater-item');
                            
                            console.log('✅ Verificação pós-inserção ACF:');
                            console.log('- Botões "adicionar" encontrados:', addButtons.length);
                            console.log('- Botões "remover" encontrados:', removeButtons.length);
                            console.log('- Método addRepeaterItem existe?', typeof self.addRepeaterItem);
                            
                            // Atualizar selects com headers do CSV se disponíveis
                            if (self.csvHeaders.length > 0) {
                                self.updateColumnSelects();
                            }
                        }, 100);
                        
                    } else {
                        $('#acf-fields-container').html('<p>Erro: ' + (response.data || 'Erro desconhecido') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ACF:', error);
                    console.log('Response text:', xhr.responseText);
                    $('#acf-fields-container').html('<p>Erro de comunicação: ' + error + '</p>');
                }
            });
        },

        addRepeaterItem: function(e) {
            e.preventDefault();
            console.log('🔥 addRepeaterItem foi chamada!');

            var button = $(e.currentTarget);
            var repeaterName = button.data('repeater');
            console.log('Repeater name:', repeaterName);

            // Encontrar o campo repetidor correto
            var repeaterField = button.closest('.csv-repeater-field');
            if (repeaterField.length === 0) {
                repeaterField = button.siblings('.csv-repeater-field');
                if (repeaterField.length === 0) {
                    repeaterField = button.prev('.csv-repeater-field');
                }
            }

            if (repeaterField.length === 0) {
                console.error('Campo repetidor não encontrado!');
                return;
            }

            var container = repeaterField.find('.csv-repeater-items');
            console.log('Container encontrado:', container.length);

            if (container.length === 0) {
                console.error('Container de itens não encontrado!');
                return;
            }

            var lastItem = container.find('.csv-repeater-item').last();
            console.log('Último item encontrado:', lastItem.length);

            if (lastItem.length === 0) {
                console.error('Nenhum item de repetidor encontrado para clonar.');
                return;
            }

            var newIndex = container.find('.csv-repeater-item').length;
            console.log('Novo índice:', newIndex);

            // Clonar o último item
            var newItem = lastItem.clone();

            // Atualizar o índice do novo item
            newItem.attr('data-index', newIndex);
            
            // Atualizar o título do item
            newItem.find('h6').text('Item ' + (newIndex + 1));

            // Atualizar os nomes dos campos select
            newItem.find('select').each(function() {
                var select = $(this);
                var name = select.attr('name');
                if (name) {
                    // Padrão: repeater_NOME_INDEX_CAMPO
                    var newName = name.replace(/_(\d+)_/, '_' + newIndex + '_');
                    select.attr('name', newName);
                    select.val(''); // Limpar o valor selecionado
                    console.log('Campo atualizado:', name, '->', newName);
                }
            });

            // Adicionar o novo item ao container
            container.append(newItem);
            console.log('Novo item adicionado com sucesso!');

            // Agora atualizar apenas os selects do novo item (que já está no DOM)
            newItem.find('select').each(function() {
                self.updateColumnSelects($(this));
            });
        },

        removeRepeaterItem: function(e) {
            e.preventDefault();

            var item = $(e.target).closest('.csv-repeater-item');
            var container = item.parent();

            if (container.find('.csv-repeater-item').length <= 1) {
                alert('Deve haver pelo menos um item no repetidor');
                return;
            }

            item.remove();

            // Reindexar os itens restantes
            container.find('.csv-repeater-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('h6').text('Item ' + (index + 1));
                $(this).find('select').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        var parts = name.split('_');
                        if (parts.length >= 4) {
                            parts[2] = index;
                            $(this).attr('name', parts.join('_'));
                        }
                    }
                });
            });
        },

        handleFormSubmit: function(e) {
            e.preventDefault();

            var form = $(e.target);
            var formData = new FormData(form[0]);
            formData.append('action', 'csv_process_import');
            formData.append('nonce', csvImporterPro.nonce);

            $('#import-results').slideDown();
            $('#import-progress').show();
            $('#import-messages').hide().removeClass('success error info');

            var submitButton = form.find('input[type="submit"]');
            var originalText = submitButton.val();
            submitButton.val('Processando...').prop('disabled', true);

            this.animateProgress();

            var self = this;
            $.ajax({
                url: csvImporterPro.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    self.handleImportResponse(response, submitButton, originalText);
                },
                error: function(xhr, status, error) {
                    self.showMessage('Erro de comunicação com o servidor: ' + error, 'error');
                    submitButton.val(originalText).prop('disabled', false);
                    $('#import-progress').hide();
                }
            });
        },

        handleImportResponse: function(response, submitButton, originalText) {
            $('#import-progress').hide();

            if (response.success) {
                this.showMessage(response.data.message, 'success');
            } else {
                this.showMessage(response.data || 'Erro durante a importação', 'error');
            }

            submitButton.val(originalText).prop('disabled', false);
        },

        showMessage: function(message, type) {
            $('#import-messages')
                .removeClass('success error info')
                .addClass(type)
                .text(message)
                .slideDown();
        },

        animateProgress: function() {
            var progress = 0;
            var interval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) {
                    progress = 90;
                    clearInterval(interval);
                }
                $('.csv-progress-fill').css('width', progress + '%');
            }, 200);
        },

        handlePostTypeChange: function() {
            this.loadACFFields();
        }
    };

    // Inicializar quando DOM estiver pronto
    $(document).ready(function() {
        console.log('DOM ready - Inicializando CSV Importer Pro');
        window.CSVImporterPro.init();

        // Carregar ACF automaticamente na página de importação
        if ($('#csv-import-form').length > 0) {
            console.log('Página de importação detectada, carregando ACF...');
            window.CSVImporterPro.loadACFFields();
        }
    });

})(jQuery);