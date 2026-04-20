<?php
/**
 * Plugin Name: CBV Formandos Manager
 * Description: Gerencia o cadastro e aprovação de formandos da Formação Barbeiro Visagista (CBV).
 * Version: 1.0.0
 * Author: Visage Education
 * Text Domain: cbv-formandos
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CBV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CBV_VERSION', '1.0.0' );

// ============================================================
// 1. REGISTRAR O CPT "CLIENTES"
// ============================================================
add_action( 'init', 'cbv_register_post_type' );

function cbv_register_post_type() {
    $labels = array(
        'name'               => 'Formandos CBV',
        'singular_name'      => 'Formando',
        'menu_name'          => 'Formandos CBV',
        'add_new'            => 'Adicionar Novo',
        'add_new_item'       => 'Adicionar Novo Formando',
        'edit_item'          => 'Editar Formando',
        'new_item'           => 'Novo Formando',
        'view_item'          => 'Ver Formando',
        'search_items'       => 'Buscar Formandos',
        'not_found'          => 'Nenhum formando encontrado',
        'not_found_in_trash' => 'Nenhum formando na lixeira',
        'all_items'          => 'Todos os Formandos',
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'clientes' ),
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-groups',
        'supports'            => array( 'title' ),
        'show_in_rest'        => true,
    );

    register_post_type( 'clientes', $args );

    // Registrar Taxonomias para filtros
    cbv_register_taxonomies();
}

function cbv_register_taxonomies() {
    // Taxonomia: País
    register_taxonomy( 'cbv_pais', 'clientes', array(
        'labels' => array(
            'name'          => 'Países',
            'singular_name' => 'País',
            'search_items'  => 'Buscar Países',
            'all_items'     => 'Todos os Países',
            'edit_item'     => 'Editar País',
            'add_new_item'  => 'Adicionar País',
            'new_item_name' => 'Novo País',
            'menu_name'     => 'Países',
        ),
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'cbv-pais' ),
    ) );

    // Taxonomia: Estado
    register_taxonomy( 'cbv_estado', 'clientes', array(
        'labels' => array(
            'name'          => 'Estados',
            'singular_name' => 'Estado',
            'search_items'  => 'Buscar Estados',
            'all_items'     => 'Todos os Estados',
            'edit_item'     => 'Editar Estado',
            'add_new_item'  => 'Adicionar Estado',
            'new_item_name' => 'Novo Estado',
            'menu_name'     => 'Estados',
        ),
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'cbv-estado' ),
    ) );

    // Taxonomia: Cidade
    register_taxonomy( 'cbv_cidade', 'clientes', array(
        'labels' => array(
            'name'          => 'Cidades',
            'singular_name' => 'Cidade',
            'search_items'  => 'Buscar Cidades',
            'all_items'     => 'Todas as Cidades',
            'edit_item'     => 'Editar Cidade',
            'add_new_item'  => 'Adicionar Cidade',
            'new_item_name' => 'Nova Cidade',
            'menu_name'     => 'Cidades',
        ),
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'cbv-cidade' ),
    ) );
}

// Flush rewrite rules on activation
register_activation_hook( __FILE__, 'cbv_activate' );
function cbv_activate() {
    cbv_register_post_type();
    cbv_register_taxonomies();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'cbv_deactivate' );
function cbv_deactivate() {
    flush_rewrite_rules();
}

// ============================================================
// 2. META BOXES - CAMPOS DO FORMANDO
// ============================================================
add_action( 'add_meta_boxes', 'cbv_add_meta_boxes' );

function cbv_add_meta_boxes() {
    add_meta_box(
        'cbv_dados_formando',
        'Dados do Formando',
        'cbv_render_meta_box',
        'clientes',
        'normal',
        'high'
    );

    add_meta_box(
        'cbv_status_box',
        'Status de Aprovação',
        'cbv_render_status_box',
        'clientes',
        'side',
        'high'
    );
}

function cbv_render_meta_box( $post ) {
    wp_nonce_field( 'cbv_save_meta', 'cbv_meta_nonce' );

    $fields = array(
        'nome_do_aluno'   => array( 'label' => 'Nome Completo', 'type' => 'text', 'required' => true ),
        'email'           => array( 'label' => 'E-mail', 'type' => 'email', 'required' => true ),
        'whatsapp'        => array( 'label' => 'WhatsApp com DDD', 'type' => 'text', 'required' => false ),
        'pais'            => array( 'label' => 'País', 'type' => 'text', 'required' => false ),
        'nome_do_estado'  => array( 'label' => 'Estado', 'type' => 'text', 'required' => false ),
        'nome_da_cidade'  => array( 'label' => 'Cidade', 'type' => 'text', 'required' => false ),
        'instagram'       => array( 'label' => 'Instagram', 'type' => 'text', 'required' => false ),
        'numero_do_cbv'   => array( 'label' => 'Número do CBV', 'type' => 'text', 'required' => false ),
    );

    echo '<table class="form-table cbv-form-table">';
    echo '<style>
        .cbv-form-table th { padding: 12px 10px; }
        .cbv-form-table td { padding: 8px 10px; }
        .cbv-form-table input[type="text"],
        .cbv-form-table input[type="email"] { width: 100%; max-width: 500px; padding: 8px; }
        .cbv-required { color: #dc3232; font-weight: bold; }
        .cbv-cbv-field input { border: 2px solid #0073aa; background: #f0f6fc; font-size: 16px; font-weight: bold; }
        .cbv-certificado-link { display: inline-block; margin-top: 5px; padding: 5px 12px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px; }
        .cbv-certificado-link:hover { background: #005a87; color: #fff; }
    </style>';

    foreach ( $fields as $key => $field ) {
        $value = get_post_meta( $post->ID, $key, true );
        $required_mark = $field['required'] ? ' <span class="cbv-required">*</span>' : '';
        $row_class = ( $key === 'numero_do_cbv' ) ? ' class="cbv-cbv-field"' : '';

        echo '<tr' . $row_class . '>';
        echo '<th><label for="cbv_' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . $required_mark . '</label></th>';
        echo '<td>';
        echo '<input type="' . esc_attr( $field['type'] ) . '" id="cbv_' . esc_attr( $key ) . '" name="cbv_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';

        if ( $key === 'numero_do_cbv' ) {
            echo '<p class="description">Obrigatório para aprovar/publicar o formando.</p>';
        }

        echo '</td>';
        echo '</tr>';
    }

    // Certificado upload
    $certificado_id  = get_post_meta( $post->ID, 'certificado', true );
    $certificado_url = get_post_meta( $post->ID, 'certificado_url', true );
    $has_certificado = ! empty( $certificado_id ) || ! empty( $certificado_url );

    echo '<tr>';
    echo '<th><label>Certificado</label></th>';
    echo '<td>';
    echo '<input type="hidden" id="cbv_certificado" name="cbv_certificado" value="' . esc_attr( $certificado_id ) . '">';
    echo '<input type="hidden" id="cbv_certificado_url" name="cbv_certificado_url" value="' . esc_attr( $certificado_url ) . '">';
    echo '<button type="button" class="button cbv-upload-btn" id="cbv_upload_certificado">Selecionar Arquivo</button>';
    echo '<button type="button" class="button cbv-remove-btn" id="cbv_remove_certificado" style="margin-left:5px;color:#a00;' . ( ! $has_certificado ? 'display:none;' : '' ) . '">Remover</button>';

    echo '<div id="cbv_certificado_preview" style="margin-top:8px;">';
    if ( ! empty( $certificado_id ) ) {
        $file_url  = wp_get_attachment_url( $certificado_id );
        $file_name = $file_url ? basename( $file_url ) : 'Ver certificado';
        if ( $file_url ) {
            echo '<a href="' . esc_url( $file_url ) . '" target="_blank" class="cbv-certificado-link">' . esc_html( $file_name ) . '</a>';
        }
    } elseif ( ! empty( $certificado_url ) ) {
        $file_name = basename( parse_url( $certificado_url, PHP_URL_PATH ) );
        if ( empty( $file_name ) ) {
            $file_name = 'Ver certificado';
        }
        echo '<a href="' . esc_url( $certificado_url ) . '" target="_blank" class="cbv-certificado-link">' . esc_html( $file_name ) . '</a>';
        echo '<p class="description" style="margin-top:4px;">Arquivo externo (WPForms)</p>';
    }
    echo '</div>';

    echo '</td>';
    echo '</tr>';

    echo '</table>';

    // Media uploader script
    ?>
    <script>
    jQuery(document).ready(function($) {
        var mediaUploader;

        $('#cbv_upload_certificado').on('click', function(e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: 'Selecionar Certificado',
                button: { text: 'Usar este arquivo' },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#cbv_certificado').val(attachment.id);
                $('#cbv_certificado_preview').html('<a href="' + attachment.url + '" target="_blank" class="cbv-certificado-link">' + attachment.filename + '</a>');
                $('#cbv_remove_certificado').show();
            });

            mediaUploader.open();
        });

        $('#cbv_remove_certificado').on('click', function(e) {
            e.preventDefault();
            $('#cbv_certificado').val('');
            $('#cbv_certificado_preview').html('');
            $(this).hide();
        });
    });
    </script>
    <?php
}

function cbv_render_status_box( $post ) {
    $status = get_post_meta( $post->ID, '_cbv_status', true );
    if ( empty( $status ) ) {
        $status = ( $post->post_status === 'publish' ) ? 'aprovado' : 'pendente';
    }

    $cbv_number = get_post_meta( $post->ID, 'numero_do_cbv', true );

    echo '<style>
        .cbv-status-box { padding: 10px 0; }
        .cbv-status-badge { display: inline-block; padding: 5px 12px; border-radius: 4px; font-weight: bold; font-size: 14px; margin-bottom: 10px; }
        .cbv-status-pendente { background: #fff3cd; color: #856404; }
        .cbv-status-aprovado { background: #d4edda; color: #155724; }
        .cbv-status-rejeitado { background: #f8d7da; color: #721c24; }
        .cbv-action-buttons { margin-top: 10px; }
        .cbv-action-buttons .button { display: block; width: 100%; margin-bottom: 5px; text-align: center; }
        .cbv-btn-aprovar { background: #28a745 !important; border-color: #28a745 !important; color: #fff !important; }
        .cbv-btn-aprovar:hover { background: #218838 !important; }
        .cbv-btn-rejeitar { background: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; }
        .cbv-btn-rejeitar:hover { background: #c82333 !important; }
        .cbv-warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 8px; margin-top: 8px; font-size: 12px; }
    </style>';

    echo '<div class="cbv-status-box">';

    // Current status badge
    $status_labels = array(
        'pendente'  => 'Pendente',
        'aprovado'  => 'Aprovado',
        'rejeitado' => 'Rejeitado',
    );
    $badge_class = 'cbv-status-' . esc_attr( $status );
    echo '<div class="cbv-status-badge ' . $badge_class . '">' . esc_html( $status_labels[ $status ] ?? $status ) . '</div>';

    // Hidden field for status
    echo '<input type="hidden" id="cbv_status" name="cbv_status" value="' . esc_attr( $status ) . '">';

    // Action buttons
    echo '<div class="cbv-action-buttons">';

    if ( $status !== 'aprovado' ) {
        echo '<button type="button" class="button cbv-btn-aprovar" id="cbv_btn_aprovar">Aprovar e Publicar</button>';
    }
    if ( $status !== 'rejeitado' ) {
        echo '<button type="button" class="button cbv-btn-rejeitar" id="cbv_btn_rejeitar">Rejeitar</button>';
    }
    if ( $status !== 'pendente' ) {
        echo '<button type="button" class="button" id="cbv_btn_pendente">Voltar para Pendente</button>';
    }

    echo '</div>';

    if ( empty( $cbv_number ) ) {
        echo '<div class="cbv-warning">O Número do CBV deve ser preenchido antes de aprovar.</div>';
    }

    echo '</div>';

    ?>
    <script>
    jQuery(document).ready(function($) {
        function cbvSetStatus(newStatus) {
            if (newStatus === 'aprovado') {
                var cbvVal = $('input[name="cbv_numero_do_cbv"]').val() || $('#cbv_numero_do_cbv').val();
                if (!cbvVal || cbvVal.trim() === '') {
                    alert('Você precisa preencher o Número do CBV antes de aprovar!');
                    return;
                }
            }
            $('#cbv_status').val(newStatus);

            if (newStatus === 'aprovado') {
                $('#post_status').val('publish');
                if ($('#publish').length) {
                    // Trigger save
                    $('#publish').click();
                }
            } else if (newStatus === 'rejeitado' || newStatus === 'pendente') {
                $('#post_status').val('draft');
                if ($('#publish').length) {
                    $('#publish').click();
                } else if ($('#save-post').length) {
                    $('#save-post').click();
                }
            }
        }

        $('#cbv_btn_aprovar').on('click', function() { cbvSetStatus('aprovado'); });
        $('#cbv_btn_rejeitar').on('click', function() { cbvSetStatus('rejeitado'); });
        $('#cbv_btn_pendente').on('click', function() { cbvSetStatus('pendente'); });
    });
    </script>
    <?php
}

// ============================================================
// 3. SALVAR META DADOS
// ============================================================
add_action( 'save_post_clientes', 'cbv_save_meta_data', 10, 2 );

function cbv_save_meta_data( $post_id, $post ) {
    // Verify nonce
    if ( ! isset( $_POST['cbv_meta_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_meta_nonce'], 'cbv_save_meta' ) ) {
        return;
    }

    // Check autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Save fields
    $meta_fields = array(
        'nome_do_aluno',
        'email',
        'whatsapp',
        'pais',
        'nome_do_estado',
        'nome_da_cidade',
        'instagram',
        'numero_do_cbv',
        'certificado',
        'certificado_url',
    );

    foreach ( $meta_fields as $field ) {
        $post_key = 'cbv_' . $field;
        if ( array_key_exists( $post_key, $_POST ) ) {
            $raw_value = wp_unslash( $_POST[ $post_key ] );
            if ( $field === 'certificado_url' ) {
                update_post_meta( $post_id, $field, esc_url_raw( $raw_value ) );
            } else {
                update_post_meta( $post_id, $field, sanitize_text_field( $raw_value ) );
            }
        }
    }

    // Save status
    if ( isset( $_POST['cbv_status'] ) ) {
        $status = sanitize_text_field( $_POST['cbv_status'] );
        update_post_meta( $post_id, '_cbv_status', $status );
    }

    // Auto-set title from nome_do_aluno
    if ( isset( $_POST['cbv_nome_do_aluno'] ) && ! empty( $_POST['cbv_nome_do_aluno'] ) ) {
        $new_title = sanitize_text_field( $_POST['cbv_nome_do_aluno'] );
        if ( $post->post_title !== $new_title ) {
            remove_action( 'save_post_clientes', 'cbv_save_meta_data', 10 );
            wp_update_post( array(
                'ID'         => $post_id,
                'post_title' => $new_title,
                'post_name'  => sanitize_title( $new_title ),
            ) );
            add_action( 'save_post_clientes', 'cbv_save_meta_data', 10, 2 );
        }
    }

    // Atribuir taxonomias automaticamente a partir dos meta fields
    cbv_sync_taxonomies( $post_id );
}

/**
 * Sincronizar meta fields com taxonomias
 */
function cbv_sync_taxonomies( $post_id ) {
    $pais = get_post_meta( $post_id, 'pais', true );
    if ( ! empty( $pais ) ) {
        wp_set_object_terms( $post_id, $pais, 'cbv_pais' );
    }

    $estado = get_post_meta( $post_id, 'nome_do_estado', true );
    if ( ! empty( $estado ) ) {
        wp_set_object_terms( $post_id, $estado, 'cbv_estado' );
    }

    $cidade = get_post_meta( $post_id, 'nome_da_cidade', true );
    if ( ! empty( $cidade ) ) {
        wp_set_object_terms( $post_id, $cidade, 'cbv_cidade' );
    }
}

// ============================================================
// 4. VALIDAÇÃO - BLOQUEAR PUBLICAÇÃO SEM CBV
// ============================================================
add_action( 'admin_notices', 'cbv_admin_notices' );

function cbv_admin_notices() {
    global $post, $pagenow;

    if ( $pagenow !== 'post.php' || ! $post || $post->post_type !== 'clientes' ) {
        return;
    }

    $status = get_post_meta( $post->ID, '_cbv_status', true );
    $cbv = get_post_meta( $post->ID, 'numero_do_cbv', true );

    if ( $post->post_status === 'publish' && empty( $cbv ) ) {
        echo '<div class="notice notice-error"><p><strong>Atenção:</strong> Este formando está publicado sem numero_do_cbv! Por favor, adicione o CBV.</p></div>';
    }

    if ( $status === 'rejeitado' ) {
        echo '<div class="notice notice-warning"><p>Este formando foi <strong>rejeitado</strong>. Ele não aparece na página pública.</p></div>';
    }
}

// Prevent publishing without CBV via server-side check
add_filter( 'wp_insert_post_data', 'cbv_prevent_publish_without_cbv', 10, 2 );

function cbv_prevent_publish_without_cbv( $data, $postarr ) {
    if ( $data['post_type'] !== 'clientes' ) {
        return $data;
    }

    if ( $data['post_status'] === 'publish' ) {
        $cbv_key = 'cbv_numero_do_cbv';
        $cbv_value = isset( $postarr[ $cbv_key ] ) ? trim( $postarr[ $cbv_key ] ) : '';

        // Also check existing meta if not in POST
        if ( empty( $cbv_value ) && ! empty( $postarr['ID'] ) ) {
            $cbv_value = get_post_meta( $postarr['ID'], 'numero_do_cbv', true );
        }

        if ( empty( $cbv_value ) ) {
            $data['post_status'] = 'draft';
            add_filter( 'redirect_post_location', function( $location ) {
                return add_query_arg( 'cbv_error', 'no_cbv', $location );
            });
        }
    }

    return $data;
}

// Show error message when CBV is missing
add_action( 'admin_notices', 'cbv_no_cbv_error_notice' );

function cbv_no_cbv_error_notice() {
    if ( isset( $_GET['cbv_error'] ) && $_GET['cbv_error'] === 'no_cbv' ) {
        echo '<div class="notice notice-error"><p><strong>Erro:</strong> Não é possível publicar sem preencher o numero_do_cbv! O formando foi salvo como rascunho.</p></div>';
    }
}

// ============================================================
// 5. INTEGRAÇÃO AUTOMÁTICA WPFORMS → CPT CLIENTES
// ============================================================

// Gancho único para evitar duplicação (versão anterior tinha dois hooks e duplicava)
add_action( 'wpforms_process_complete', 'cbv_wpforms_to_cpt', 10, 4 );

/**
 * Grava log de debug no banco (option cbv_wpforms_log).
 * Mantém últimas 50 entradas.
 */
function cbv_log_wpforms( $message, $context = array() ) {
    $log = get_option( 'cbv_wpforms_log', array() );
    $log[] = array(
        'time'    => current_time( 'mysql' ),
        'message' => $message,
        'context' => $context,
    );
    // Mantém só os últimos 50
    if ( count( $log ) > 50 ) {
        $log = array_slice( $log, -50 );
    }
    update_option( 'cbv_wpforms_log', $log, false );
}

/**
 * Hook principal: wpforms_process_complete
 */
function cbv_wpforms_to_cpt( $fields, $entry, $form_data, $entry_id ) {
    $form_id_configured = 5304;
    $received_form_id   = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;

    cbv_log_wpforms( 'Hook wpforms_process_complete disparou', array(
        'form_id_recebido'   => $received_form_id,
        'form_id_esperado'   => $form_id_configured,
        'entry_id'           => $entry_id,
        'total_fields'       => is_array( $fields ) ? count( $fields ) : 0,
    ) );

    if ( $received_form_id !== $form_id_configured ) {
        cbv_log_wpforms( 'Form ID diferente - ignorando', array( 'recebido' => $received_form_id ) );
        return;
    }

    cbv_create_formando_from_fields( $fields, $entry_id, 'wpforms_process_complete' );
}

/**
 * Função principal que cria o formando a partir dos campos.
 * Chamada pelo hook wpforms_process_complete.
 */
function cbv_create_formando_from_fields( $fields, $entry_id, $source = '' ) {
    if ( ! is_array( $fields ) ) {
        cbv_log_wpforms( 'Fields não é array - abortado', array( 'source' => $source ) );
        return false;
    }

    // Extrair valores usando busca inteligente (por label e por ID conhecido)
    $nome      = cbv_find_field_value( $fields, array( 2 ), array( 'nome completo', 'nome' ) );
    $whatsapp  = cbv_find_field_value( $fields, array( 7 ), array( 'whatsapp' ) );
    $pais      = cbv_find_field_value( $fields, array( 9 ), array( 'pais', 'país' ) );
    $instagram = cbv_find_field_value( $fields, array( 3 ), array( 'instagram' ) );
    $email     = cbv_find_field_value( $fields, array( 8 ), array( 'e-mail', 'email' ) );

    // Estado: pode vir do select (5) ou text (11)
    $estado = cbv_find_field_value( $fields, array( 5, 11 ), array( 'estado' ) );

    // Cidade: vem de muitos selects condicionais ou text (4)
    $cidade = '';
    $cidade_field_ids = array( 13, 14, 15, 16, 17, 18, 19, 20, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 4 );
    foreach ( $cidade_field_ids as $cid ) {
        if ( isset( $fields[ $cid ]['value'] ) && ! empty( $fields[ $cid ]['value'] ) ) {
            $cidade = sanitize_text_field( $fields[ $cid ]['value'] );
            break;
        }
    }
    // Fallback: procurar por label "cidade"
    if ( empty( $cidade ) ) {
        $cidade = cbv_find_field_value( $fields, array(), array( 'cidade' ) );
    }

    // Certificado (upload)
    $certificado_url = '';
    if ( isset( $fields[1]['value'] ) && ! empty( $fields[1]['value'] ) ) {
        $certificado_url = esc_url_raw( $fields[1]['value'] );
    }
    // Fallback: procurar campo do tipo file-upload
    if ( empty( $certificado_url ) ) {
        foreach ( $fields as $fid => $field ) {
            if ( isset( $field['type'] ) && $field['type'] === 'file-upload' && ! empty( $field['value'] ) ) {
                $certificado_url = esc_url_raw( $field['value'] );
                break;
            }
            if ( isset( $field['value_raw'] ) && ! empty( $field['value_raw'] ) && isset( $field['name'] ) && stripos( $field['name'], 'certificad' ) !== false ) {
                $certificado_url = esc_url_raw( is_array( $field['value_raw'] ) ? ( $field['value_raw'][0] ?? '' ) : $field['value_raw'] );
                break;
            }
        }
    }

    cbv_log_wpforms( 'Valores extraídos', array(
        'nome'       => $nome,
        'email'      => $email,
        'whatsapp'   => $whatsapp,
        'pais'       => $pais,
        'estado'     => $estado,
        'cidade'     => $cidade,
        'instagram'  => $instagram,
        'cert_url'   => $certificado_url,
        'source'     => $source,
    ) );

    // Se não tem nome nem email, não cria a ficha
    if ( empty( $nome ) && empty( $email ) ) {
        cbv_log_wpforms( 'Sem nome nem email - abortado' );
        return false;
    }

    // Título da ficha (fallback para email se não tiver nome)
    $title = ! empty( $nome ) ? $nome : $email;

    // Criar post
    $post_id = wp_insert_post( array(
        'post_title'  => $title,
        'post_type'   => 'clientes',
        'post_status' => 'draft',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        cbv_log_wpforms( 'Erro ao criar post', array( 'erro' => $post_id->get_error_message() ) );
        return false;
    }

    if ( ! $post_id ) {
        cbv_log_wpforms( 'wp_insert_post retornou 0' );
        return false;
    }

    // Salvar meta dados
    update_post_meta( $post_id, 'nome_do_aluno', $nome );
    update_post_meta( $post_id, 'email', $email );
    update_post_meta( $post_id, 'whatsapp', $whatsapp );
    update_post_meta( $post_id, 'pais', $pais );
    update_post_meta( $post_id, 'nome_do_estado', $estado );
    update_post_meta( $post_id, 'nome_da_cidade', $cidade );
    update_post_meta( $post_id, 'instagram', $instagram );
    update_post_meta( $post_id, '_cbv_status', 'pendente' );
    update_post_meta( $post_id, '_wpforms_entry_id', $entry_id );

    // Certificado
    if ( ! empty( $certificado_url ) ) {
        $attach_id = cbv_import_attachment_from_url( $certificado_url, $post_id );
        if ( $attach_id ) {
            update_post_meta( $post_id, 'certificado', $attach_id );
            cbv_log_wpforms( 'Certificado importado como attachment', array( 'attach_id' => $attach_id ) );
        } else {
            update_post_meta( $post_id, 'certificado_url', $certificado_url );
            cbv_log_wpforms( 'Certificado salvo como URL externa', array( 'url' => $certificado_url ) );
        }
    }

    // Sincronizar taxonomias
    cbv_sync_taxonomies( $post_id );

    cbv_log_wpforms( 'Ficha criada com sucesso', array(
        'post_id'  => $post_id,
        'title'    => $title,
        'entry_id' => $entry_id,
        'source'   => $source,
    ) );

    return $post_id;
}

/**
 * Busca valor de campo por ID conhecido OU por label (case-insensitive).
 */
function cbv_find_field_value( $fields, $ids = array(), $label_keywords = array() ) {
    // Primeiro tenta pelos IDs conhecidos
    foreach ( $ids as $id ) {
        if ( isset( $fields[ $id ]['value'] ) && ! empty( $fields[ $id ]['value'] ) ) {
            $value = $fields[ $id ]['value'];
            return is_email( $value ) ? sanitize_email( $value ) : sanitize_text_field( $value );
        }
    }

    // Fallback: procura por label
    if ( ! empty( $label_keywords ) ) {
        foreach ( $fields as $field ) {
            if ( ! isset( $field['name'] ) || ! isset( $field['value'] ) || empty( $field['value'] ) ) {
                continue;
            }
            $label_lower = strtolower( $field['name'] );
            foreach ( $label_keywords as $keyword ) {
                if ( strpos( $label_lower, strtolower( $keyword ) ) !== false ) {
                    $value = $field['value'];
                    return is_email( $value ) ? sanitize_email( $value ) : sanitize_text_field( $value );
                }
            }
        }
    }

    return '';
}

/**
 * Cria uma ficha a partir dos dados originais (mantido para compatibilidade).
 */
if ( ! function_exists( 'cbv_wpforms_legacy_create' ) ) :
function cbv_wpforms_legacy_create( $fields, $entry, $form_data, $entry_id ) {
    // Stub mantido para evitar erros caso haja referência antiga
    return cbv_wpforms_to_cpt( $fields, $entry, $form_data, $entry_id );
}
endif;

/**
 * Importar arquivo de URL como attachment do WordPress
 */
function cbv_import_attachment_from_url( $url, $parent_post_id = 0 ) {
    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Download do arquivo
    $tmp = download_url( $url );
    if ( is_wp_error( $tmp ) ) {
        return false;
    }

    $file_name = basename( parse_url( $url, PHP_URL_PATH ) );

    $file_array = array(
        'name'     => $file_name,
        'tmp_name' => $tmp,
    );

    $attach_id = media_handle_sideload( $file_array, $parent_post_id );

    if ( is_wp_error( $attach_id ) ) {
        @unlink( $tmp );
        return false;
    }

    return $attach_id;
}

// ============================================================
// 6. COLUNAS PERSONALIZADAS NA LISTAGEM
// ============================================================
add_filter( 'manage_clientes_posts_columns', 'cbv_custom_columns' );

function cbv_custom_columns( $columns ) {
    $new_columns = array(
        'cb'              => $columns['cb'],
        'title'           => 'Nome',
        'cbv_email'       => 'E-mail',
        'cbv_cidade'      => 'Cidade',
        'cbv_estado'      => 'Estado',
        'cbv_instagram'   => 'Instagram',
        'cbv_numero'      => 'CBV',
        'cbv_status'      => 'Status',
        'cbv_certificado' => 'Certificado',
        'date'            => 'Data',
    );
    return $new_columns;
}

add_action( 'manage_clientes_posts_custom_column', 'cbv_custom_column_content', 10, 2 );

function cbv_custom_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'cbv_email':
            echo esc_html( get_post_meta( $post_id, 'email', true ) );
            break;
        case 'cbv_cidade':
            echo esc_html( get_post_meta( $post_id, 'nome_da_cidade', true ) );
            break;
        case 'cbv_estado':
            echo esc_html( get_post_meta( $post_id, 'nome_do_estado', true ) );
            break;
        case 'cbv_instagram':
            $insta = get_post_meta( $post_id, 'instagram', true );
            if ( $insta ) {
                echo '<a href="https://instagram.com/' . esc_attr( ltrim( $insta, '@' ) ) . '" target="_blank">' . esc_html( $insta ) . '</a>';
            }
            break;
        case 'cbv_numero':
            $cbv = get_post_meta( $post_id, 'numero_do_cbv', true );
            if ( $cbv ) {
                echo '<strong>' . esc_html( $cbv ) . '</strong>';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            break;
        case 'cbv_status':
            $status = get_post_meta( $post_id, '_cbv_status', true );
            if ( empty( $status ) ) {
                $post_status = get_post_status( $post_id );
                $status = ( $post_status === 'publish' ) ? 'aprovado' : 'pendente';
            }
            $badges = array(
                'pendente'  => '<span style="background:#fff3cd;color:#856404;padding:3px 8px;border-radius:3px;font-size:12px;">Pendente</span>',
                'aprovado'  => '<span style="background:#d4edda;color:#155724;padding:3px 8px;border-radius:3px;font-size:12px;">Aprovado</span>',
                'rejeitado' => '<span style="background:#f8d7da;color:#721c24;padding:3px 8px;border-radius:3px;font-size:12px;">Rejeitado</span>',
            );
            echo $badges[ $status ] ?? esc_html( $status );
            break;
        case 'cbv_certificado':
            $cert_id  = get_post_meta( $post_id, 'certificado', true );
            $cert_url = get_post_meta( $post_id, 'certificado_url', true );
            $url      = '';
            if ( $cert_id ) {
                $url = wp_get_attachment_url( $cert_id );
            } elseif ( $cert_url ) {
                $url = $cert_url;
            }
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '" target="_blank">Ver</a>';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            break;
    }
}

// Make columns sortable
add_filter( 'manage_edit-clientes_sortable_columns', 'cbv_sortable_columns' );

function cbv_sortable_columns( $columns ) {
    $columns['cbv_estado'] = 'cbv_estado';
    $columns['cbv_cidade'] = 'cbv_cidade';
    $columns['cbv_numero'] = 'cbv_numero';
    return $columns;
}

// ============================================================
// 7. FILTROS NA LISTAGEM DO ADMIN
// ============================================================
add_action( 'restrict_manage_posts', 'cbv_admin_filters' );

function cbv_admin_filters( $post_type ) {
    if ( $post_type !== 'clientes' ) {
        return;
    }

    // Filter by status
    $current_status = isset( $_GET['cbv_filter_status'] ) ? sanitize_text_field( $_GET['cbv_filter_status'] ) : '';
    echo '<select name="cbv_filter_status">';
    echo '<option value="">Todos os Status</option>';
    echo '<option value="pendente"' . selected( $current_status, 'pendente', false ) . '>Pendente</option>';
    echo '<option value="aprovado"' . selected( $current_status, 'aprovado', false ) . '>Aprovado</option>';
    echo '<option value="rejeitado"' . selected( $current_status, 'rejeitado', false ) . '>Rejeitado</option>';
    echo '</select>';

    // Filter by estado
    global $wpdb;
    $estados = $wpdb->get_col(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
         WHERE meta_key = 'nome_do_estado' AND meta_value != ''
         ORDER BY meta_value ASC"
    );

    if ( ! empty( $estados ) ) {
        $current_estado = isset( $_GET['cbv_filter_estado'] ) ? sanitize_text_field( $_GET['cbv_filter_estado'] ) : '';
        echo '<select name="cbv_filter_estado">';
        echo '<option value="">Todos os Estados</option>';
        foreach ( $estados as $estado ) {
            echo '<option value="' . esc_attr( $estado ) . '"' . selected( $current_estado, $estado, false ) . '>' . esc_html( $estado ) . '</option>';
        }
        echo '</select>';
    }
}

add_action( 'pre_get_posts', 'cbv_filter_query' );

function cbv_filter_query( $query ) {
    global $pagenow;

    if ( ! is_admin() || $pagenow !== 'edit.php' || ! $query->is_main_query() ) {
        return;
    }

    if ( $query->get( 'post_type' ) !== 'clientes' ) {
        return;
    }

    $meta_query = $query->get( 'meta_query' ) ?: array();

    // Only override post_status when WordPress default ("All" tab) is in use.
    // This preserves the behavior when user clicks "Rascunhos", "Publicados", etc.
    if ( ! isset( $_GET['post_status'] ) || $_GET['post_status'] === 'all' ) {
        $current_status = $query->get( 'post_status' );
        if ( empty( $current_status ) || $current_status === 'all' ) {
            $query->set( 'post_status', array( 'publish', 'draft', 'pending' ) );
        }
    }

    if ( ! empty( $_GET['cbv_filter_status'] ) ) {
        $meta_query[] = array(
            'key'   => '_cbv_status',
            'value' => sanitize_text_field( $_GET['cbv_filter_status'] ),
        );
    }

    if ( ! empty( $_GET['cbv_filter_estado'] ) ) {
        $meta_query[] = array(
            'key'   => 'nome_do_estado',
            'value' => sanitize_text_field( $_GET['cbv_filter_estado'] ),
        );
    }

    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }
}

// ============================================================
// 8. AÇÕES RÁPIDAS NA LISTAGEM (BULK + ROW)
// ============================================================
add_filter( 'post_row_actions', 'cbv_row_actions', 10, 2 );

function cbv_row_actions( $actions, $post ) {
    if ( $post->post_type !== 'clientes' ) {
        return $actions;
    }

    $status = get_post_meta( $post->ID, '_cbv_status', true );
    $cbv = get_post_meta( $post->ID, 'numero_do_cbv', true );

    if ( $status !== 'aprovado' && ! empty( $cbv ) ) {
        $approve_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=cbv_quick_action&cbv_action=aprovar&post_id=' . $post->ID ),
            'cbv_quick_' . $post->ID
        );
        $actions['cbv_aprovar'] = '<a href="' . esc_url( $approve_url ) . '" style="color:#28a745;font-weight:bold;">Aprovar</a>';
    }

    if ( $status !== 'rejeitado' ) {
        $reject_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=cbv_quick_action&cbv_action=rejeitar&post_id=' . $post->ID ),
            'cbv_quick_' . $post->ID
        );
        $actions['cbv_rejeitar'] = '<a href="' . esc_url( $reject_url ) . '" style="color:#dc3545;">Rejeitar</a>';
    }

    return $actions;
}

// Handle quick actions
add_action( 'admin_post_cbv_quick_action', 'cbv_handle_quick_action' );

function cbv_handle_quick_action() {
    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
    $action = isset( $_GET['cbv_action'] ) ? sanitize_text_field( $_GET['cbv_action'] ) : '';

    if ( ! $post_id || ! wp_verify_nonce( $_GET['_wpnonce'], 'cbv_quick_' . $post_id ) ) {
        wp_die( 'Ação não autorizada.' );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( 'Você não tem permissão.' );
    }

    switch ( $action ) {
        case 'aprovar':
            $cbv = get_post_meta( $post_id, 'numero_do_cbv', true );
            if ( empty( $cbv ) ) {
                wp_redirect( admin_url( 'edit.php?post_type=clientes&cbv_msg=no_cbv' ) );
                exit;
            }
            wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
            update_post_meta( $post_id, '_cbv_status', 'aprovado' );
            break;

        case 'rejeitar':
            wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
            update_post_meta( $post_id, '_cbv_status', 'rejeitado' );
            break;
    }

    wp_redirect( admin_url( 'edit.php?post_type=clientes&cbv_msg=updated' ) );
    exit;
}

// Quick action admin notices
add_action( 'admin_notices', 'cbv_quick_action_notices' );

function cbv_quick_action_notices() {
    if ( ! isset( $_GET['cbv_msg'] ) ) {
        return;
    }

    if ( $_GET['cbv_msg'] === 'no_cbv' ) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Erro:</strong> Não é possível aprovar sem numero_do_cbv. Edite o formando e preencha o CBV primeiro.</p></div>';
    }

    if ( $_GET['cbv_msg'] === 'updated' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Status do formando atualizado com sucesso!</p></div>';
    }
}

// ============================================================
// 9. ENQUEUE MEDIA UPLOADER
// ============================================================
add_action( 'admin_enqueue_scripts', 'cbv_admin_scripts' );

function cbv_admin_scripts( $hook ) {
    global $post;

    if ( $hook === 'post-new.php' || $hook === 'post.php' ) {
        if ( $post && $post->post_type === 'clientes' ) {
            wp_enqueue_media();
        }
    }
}

// ============================================================
// 10. DASHBOARD WIDGET - RESUMO RÁPIDO
// ============================================================
add_action( 'wp_dashboard_setup', 'cbv_dashboard_widget' );

function cbv_dashboard_widget() {
    wp_add_dashboard_widget(
        'cbv_dashboard_overview',
        'Formandos CBV - Resumo',
        'cbv_dashboard_widget_content'
    );
}

function cbv_dashboard_widget_content() {
    global $wpdb;

    $total = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'clientes' AND post_status IN ('publish', 'draft', 'pending')"
    );

    $aprovados = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'clientes'
         AND p.post_status = 'publish'
         AND pm.meta_key = '_cbv_status'
         AND pm.meta_value = 'aprovado'"
    );

    $pendentes = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'clientes'
         AND pm.meta_key = '_cbv_status'
         AND pm.meta_value = 'pendente'"
    );

    $rejeitados = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'clientes'
         AND pm.meta_key = '_cbv_status'
         AND pm.meta_value = 'rejeitado'"
    );

    echo '<style>
        .cbv-dashboard-stats { display: flex; gap: 10px; flex-wrap: wrap; }
        .cbv-stat { flex: 1; min-width: 80px; text-align: center; padding: 12px 8px; border-radius: 5px; }
        .cbv-stat-number { font-size: 28px; font-weight: bold; display: block; }
        .cbv-stat-label { font-size: 12px; display: block; margin-top: 4px; }
        .cbv-stat-total { background: #e8f0fe; color: #1a73e8; }
        .cbv-stat-aprovado { background: #d4edda; color: #155724; }
        .cbv-stat-pendente { background: #fff3cd; color: #856404; }
        .cbv-stat-rejeitado { background: #f8d7da; color: #721c24; }
        .cbv-dashboard-actions { margin-top: 12px; }
    </style>';

    echo '<div class="cbv-dashboard-stats">';
    echo '<div class="cbv-stat cbv-stat-total"><span class="cbv-stat-number">' . intval( $total ) . '</span><span class="cbv-stat-label">Total</span></div>';
    echo '<div class="cbv-stat cbv-stat-aprovado"><span class="cbv-stat-number">' . intval( $aprovados ) . '</span><span class="cbv-stat-label">Aprovados</span></div>';
    echo '<div class="cbv-stat cbv-stat-pendente"><span class="cbv-stat-number">' . intval( $pendentes ) . '</span><span class="cbv-stat-label">Pendentes</span></div>';
    echo '<div class="cbv-stat cbv-stat-rejeitado"><span class="cbv-stat-number">' . intval( $rejeitados ) . '</span><span class="cbv-stat-label">Rejeitados</span></div>';
    echo '</div>';

    echo '<div class="cbv-dashboard-actions">';
    echo '<a href="' . admin_url( 'edit.php?post_type=clientes&cbv_filter_status=pendente' ) . '" class="button">Ver Pendentes</a> ';
    echo '<a href="' . admin_url( 'post-new.php?post_type=clientes' ) . '" class="button button-primary">Adicionar Formando</a>';
    echo '</div>';
}

// ============================================================
// 11. QUICK EDIT CUSTOMIZADO - SOMENTE CBV E STATUS
// ============================================================

/**
 * CSS para esconder os campos padrão do Quick Edit (Título, Slug, Data, Senha,
 * Taxonomias) mantendo apenas os campos customizados (CBV e Status).
 */
add_action( 'admin_head-edit.php', 'cbv_quick_edit_hide_defaults_css' );

function cbv_quick_edit_hide_defaults_css() {
    global $post_type;
    if ( $post_type !== 'clientes' ) {
        return;
    }
    ?>
    <style>
        /* Esconde colunas padrão do Quick Edit para o CPT clientes */
        .post-type-clientes tr.inline-edit-row fieldset.inline-edit-col-left,
        .post-type-clientes tr.inline-edit-row fieldset.inline-edit-col-center {
            display: none !important;
        }
        /* Esconde a coluna padrão da direita (Status/Sticky) deixando apenas a customizada */
        .post-type-clientes tr.inline-edit-row fieldset.inline-edit-col-right:not(.cbv-quick-edit-fieldset) {
            display: none !important;
        }
        /* Faz o fieldset customizado ocupar toda a largura */
        .post-type-clientes tr.inline-edit-row fieldset.cbv-quick-edit-fieldset {
            width: 100% !important;
            float: none !important;
            padding: 0 0.5em !important;
        }
        .post-type-clientes tr.inline-edit-row fieldset.cbv-quick-edit-fieldset legend {
            margin-left: 0;
        }
    </style>
    <?php
}

/**
 * Adiciona campos customizados ao Quick Edit inline.
 * Exibe apenas CBV e Status.
 */
add_action( 'quick_edit_custom_box', 'cbv_quick_edit_custom_box', 10, 2 );

function cbv_quick_edit_custom_box( $column_name, $post_type ) {
    if ( $post_type !== 'clientes' ) {
        return;
    }

    // Só mostra uma vez (na primeira coluna custom disponível)
    static $rendered = false;
    if ( $rendered ) {
        return;
    }

    if ( $column_name !== 'cbv_numero' ) {
        return;
    }

    $rendered = true;

    wp_nonce_field( 'cbv_quick_edit', 'cbv_quick_edit_nonce' );
    ?>
    <fieldset class="inline-edit-col-right cbv-quick-edit-fieldset">
        <legend class="inline-edit-legend">Dados do Formando</legend>
        <div class="inline-edit-col">
            <div class="inline-edit-group wp-clearfix" style="margin-bottom:10px;">
                <label class="alignleft" style="display:block; width:100%;">
                    <span class="title">Número do CBV</span>
                    <input type="text" name="cbv_quick_numero_do_cbv" value="" style="width:100%; box-sizing:border-box;">
                </label>
            </div>
            <div class="inline-edit-group wp-clearfix">
                <label class="alignleft" style="display:block; width:100%;">
                    <span class="title">Status</span>
                    <select name="cbv_quick_status" style="width:100%;">
                        <option value="pendente">Pendente</option>
                        <option value="aprovado">Aprovado</option>
                        <option value="rejeitado">Rejeitado</option>
                    </select>
                </label>
            </div>
            <p class="description" style="margin-top:8px;">Status "Aprovado" só funciona com CBV preenchido.</p>
        </div>
    </fieldset>
    <?php
}

/**
 * Popula os campos do Quick Edit via JS com valores atuais do post.
 */
add_action( 'admin_footer-edit.php', 'cbv_quick_edit_populate_js' );

function cbv_quick_edit_populate_js() {
    global $post_type;
    if ( $post_type !== 'clientes' ) {
        return;
    }
    ?>
    <script>
    jQuery(function($) {
        var $wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(id) {
            $wp_inline_edit.apply(this, arguments);

            var post_id = 0;
            if (typeof(id) === 'object') {
                post_id = parseInt(this.getId(id));
            }
            if (!post_id) return;

            var $row = $('#post-' + post_id);
            var $edit = $('#edit-' + post_id);

            // Ler o CBV da coluna da listagem
            var cbvText = $row.find('.column-cbv_numero strong').text().trim();
            $edit.find('input[name="cbv_quick_numero_do_cbv"]').val(cbvText);

            // Ler o status da coluna
            var statusText = $row.find('.column-cbv_status span').text().trim().toLowerCase();
            if (['pendente','aprovado','rejeitado'].indexOf(statusText) >= 0) {
                $edit.find('select[name="cbv_quick_status"]').val(statusText);
            }
        };
    });
    </script>
    <?php
}

/**
 * Salva os dados do Quick Edit.
 */
add_action( 'save_post_clientes', 'cbv_save_quick_edit', 20, 2 );

function cbv_save_quick_edit( $post_id, $post ) {
    if ( ! isset( $_POST['cbv_quick_edit_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['cbv_quick_edit_nonce'], 'cbv_quick_edit' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Salvar CBV
    if ( isset( $_POST['cbv_quick_numero_do_cbv'] ) ) {
        $cbv = sanitize_text_field( wp_unslash( $_POST['cbv_quick_numero_do_cbv'] ) );
        update_post_meta( $post_id, 'numero_do_cbv', $cbv );
    }

    // Salvar status
    if ( isset( $_POST['cbv_quick_status'] ) ) {
        $status = sanitize_text_field( wp_unslash( $_POST['cbv_quick_status'] ) );
        if ( in_array( $status, array( 'pendente', 'aprovado', 'rejeitado' ), true ) ) {
            update_post_meta( $post_id, '_cbv_status', $status );

            // Se aprovar, publicar (desde que tenha CBV)
            if ( $status === 'aprovado' ) {
                $cbv_now = get_post_meta( $post_id, 'numero_do_cbv', true );
                if ( ! empty( $cbv_now ) && $post->post_status !== 'publish' ) {
                    remove_action( 'save_post_clientes', 'cbv_save_quick_edit', 20 );
                    wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
                    add_action( 'save_post_clientes', 'cbv_save_quick_edit', 20, 2 );
                }
            } elseif ( in_array( $status, array( 'pendente', 'rejeitado' ), true ) ) {
                if ( $post->post_status === 'publish' ) {
                    remove_action( 'save_post_clientes', 'cbv_save_quick_edit', 20 );
                    wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
                    add_action( 'save_post_clientes', 'cbv_save_quick_edit', 20, 2 );
                }
            }
        }
    }

    // Sincronizar taxonomias também
    cbv_sync_taxonomies( $post_id );
}

// ============================================================
// 12. PÁGINA DE DEBUG/FERRAMENTAS WPFORMS
// ============================================================
add_action( 'admin_menu', 'cbv_add_tools_submenu' );

function cbv_add_tools_submenu() {
    add_submenu_page(
        'edit.php?post_type=clientes',
        'Ferramentas WPForms',
        'Ferramentas WPForms',
        'manage_options',
        'cbv-wpforms-tools',
        'cbv_render_tools_page'
    );
}

function cbv_render_tools_page() {
    // Processar ações
    if ( isset( $_POST['cbv_action'] ) && check_admin_referer( 'cbv_tools' ) ) {
        $action = sanitize_text_field( $_POST['cbv_action'] );

        if ( $action === 'clear_log' ) {
            delete_option( 'cbv_wpforms_log' );
            echo '<div class="notice notice-success"><p>Log limpo.</p></div>';
        }

        if ( $action === 'import_old' ) {
            $count = cbv_import_old_wpforms_entries();
            echo '<div class="notice notice-success"><p>Importação concluída. Fichas criadas: <strong>' . intval( $count ) . '</strong></p></div>';
        }

        if ( $action === 'test_hook' ) {
            // Simula uma chamada do hook com dados fake
            $fake_fields = array(
                2 => array( 'name' => 'Qual seu nome completo?', 'value' => 'TESTE MANUAL ' . time() ),
                8 => array( 'name' => 'Email', 'value' => 'teste' . time() . '@example.com' ),
                9 => array( 'name' => 'País', 'value' => 'Brasil' ),
                5 => array( 'name' => 'Estado', 'value' => 'São Paulo - SP' ),
            );
            $result = cbv_create_formando_from_fields( $fake_fields, 'TEST_' . time(), 'manual_test' );
            if ( $result ) {
                echo '<div class="notice notice-success"><p>Teste OK! Ficha criada com ID <strong>' . intval( $result ) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Falha ao criar ficha de teste. Veja os logs abaixo.</p></div>';
            }
        }
    }

    $log = get_option( 'cbv_wpforms_log', array() );
    $log = array_reverse( $log ); // Mais recentes primeiro

    // Checar se WPForms está ativo
    $wpforms_active = class_exists( 'WPForms' ) || function_exists( 'wpforms' );

    // Contar entradas WPForms (se possível)
    $entries_count = 0;
    if ( function_exists( 'wpforms' ) ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpforms_entries';
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
        if ( $exists ) {
            $entries_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE form_id = 5304" );
        }
    }

    // Contar fichas já importadas do WPForms
    global $wpdb;
    $imported_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_wpforms_entry_id'
         AND p.post_type = 'clientes'"
    );

    ?>
    <div class="wrap">
        <h1>Ferramentas WPForms - CBV</h1>

        <div class="notice notice-info inline">
            <p>
                <strong>WPForms ativo:</strong> <?php echo $wpforms_active ? '✓ Sim' : '✗ Não'; ?><br>
                <strong>Entradas no WPForms (form 5304):</strong> <?php echo $entries_count; ?><br>
                <strong>Fichas já importadas:</strong> <?php echo $imported_count; ?>
            </p>
        </div>

        <div style="display:flex; gap:20px; margin-top:20px;">

            <div class="card" style="padding:20px; flex:1;">
                <h2>Testar o Hook</h2>
                <p>Cria uma ficha de teste diretamente (sem precisar submeter o formulário). Se funcionar aqui mas não pelo formulário, o problema está no hook do WPForms.</p>
                <form method="post">
                    <?php wp_nonce_field( 'cbv_tools' ); ?>
                    <input type="hidden" name="cbv_action" value="test_hook">
                    <button type="submit" class="button button-primary">Criar ficha de teste</button>
                </form>
            </div>

            <div class="card" style="padding:20px; flex:1;">
                <h2>Importar entradas antigas</h2>
                <p>Cria fichas para <strong>todas as entradas</strong> existentes no WPForms que ainda não foram importadas. Seguro de rodar várias vezes (não duplica).</p>
                <form method="post" onsubmit="return confirm('Isso vai criar fichas para todas as entradas ainda não importadas. Continuar?');">
                    <?php wp_nonce_field( 'cbv_tools' ); ?>
                    <input type="hidden" name="cbv_action" value="import_old">
                    <button type="submit" class="button button-primary">Importar entradas não-importadas</button>
                </form>
            </div>

        </div>

        <h2 style="margin-top:30px;">Logs (últimos 50 eventos)</h2>
        <form method="post" style="margin-bottom:10px;">
            <?php wp_nonce_field( 'cbv_tools' ); ?>
            <input type="hidden" name="cbv_action" value="clear_log">
            <button type="submit" class="button">Limpar log</button>
        </form>

        <?php if ( empty( $log ) ) : ?>
            <p><em>Nenhum log registrado ainda. Submeta um formulário e recarregue esta página.</em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:160px;">Data/Hora</th>
                        <th>Mensagem</th>
                        <th>Contexto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $log as $entry ) : ?>
                        <tr>
                            <td><?php echo esc_html( $entry['time'] ); ?></td>
                            <td><strong><?php echo esc_html( $entry['message'] ); ?></strong></td>
                            <td><pre style="margin:0; font-size:11px; white-space:pre-wrap;"><?php echo esc_html( wp_json_encode( $entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Importa entradas antigas do WPForms que ainda não viraram fichas.
 */
function cbv_import_old_wpforms_entries() {
    if ( ! function_exists( 'wpforms' ) ) {
        cbv_log_wpforms( 'Importação antiga: WPForms não está ativo' );
        return 0;
    }

    global $wpdb;
    $entries_table = $wpdb->prefix . 'wpforms_entries';
    $fields_table  = $wpdb->prefix . 'wpforms_entry_fields';

    // Buscar entradas do form 5304 que ainda não foram importadas
    $query = "
        SELECT e.entry_id, e.fields
        FROM {$entries_table} e
        WHERE e.form_id = 5304
        AND e.entry_id NOT IN (
            SELECT meta_value FROM {$wpdb->postmeta}
            WHERE meta_key = '_wpforms_entry_id'
        )
        LIMIT 500
    ";

    $entries = $wpdb->get_results( $query );
    $count = 0;

    if ( empty( $entries ) ) {
        cbv_log_wpforms( 'Importação antiga: nenhuma entrada pendente' );
        return 0;
    }

    foreach ( $entries as $entry ) {
        $fields_data = json_decode( $entry->fields, true );
        if ( ! is_array( $fields_data ) ) {
            continue;
        }

        $result = cbv_create_formando_from_fields( $fields_data, $entry->entry_id, 'import_old' );
        if ( $result ) {
            $count++;
        }
    }

    cbv_log_wpforms( 'Importação antiga concluída', array( 'importadas' => $count, 'total_candidatas' => count( $entries ) ) );
    return $count;
}
