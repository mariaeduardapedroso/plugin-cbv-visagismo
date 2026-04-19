<?php
/**
 * Plugin Name: CBV Sync CSV
 * Description: Sincroniza dados de CSV enviado pelo admin. Se o email já existe no CPT clientes, atualiza o CBV. Se não existe, cria uma ficha já com status "Aprovado".
 * Version: 2.0.0
 * Author: Visage Education
 * Text Domain: cbv-sync-csv
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CBV_SYNC_VERSION', '2.0.0' );
define( 'CBV_SYNC_LOG_OPTION', 'cbv_sync_csv_log' );
define( 'CBV_SYNC_LAST_RUN_OPTION', 'cbv_sync_csv_last_run' );
define( 'CBV_SYNC_UPLOAD_OPTION', 'cbv_sync_csv_upload' ); // guarda o caminho do CSV temporariamente

// ============================================================
// MENU ADMIN
// ============================================================
add_action( 'admin_menu', 'cbv_sync_add_menu' );

function cbv_sync_add_menu() {
    add_submenu_page(
        'edit.php?post_type=clientes',
        'Sincronizar CSV (CBV)',
        'Sincronizar CSV',
        'manage_options',
        'cbv-sync-csv',
        'cbv_sync_render_page'
    );
}

// ============================================================
// PÁGINA ADMIN
// ============================================================
function cbv_sync_render_page() {
    $notices = array();

    // UPLOAD DO CSV
    if ( isset( $_POST['cbv_sync_action'] ) && $_POST['cbv_sync_action'] === 'upload_csv' && check_admin_referer( 'cbv_sync_run' ) ) {
        $result = cbv_sync_handle_upload();
        if ( is_wp_error( $result ) ) {
            $notices[] = array( 'type' => 'error', 'msg' => $result->get_error_message() );
        } else {
            $notices[] = array( 'type' => 'success', 'msg' => 'CSV enviado com sucesso. ' . $result['count'] . ' linhas válidas encontradas. Agora você pode simular ou executar a sincronização.' );
        }
    }

    // RODAR SYNC
    if ( isset( $_POST['cbv_sync_action'] ) && $_POST['cbv_sync_action'] === 'run_sync' && check_admin_referer( 'cbv_sync_run' ) ) {
        $dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === '1';
        $csv_data = cbv_sync_get_uploaded_data();

        if ( empty( $csv_data ) ) {
            $notices[] = array( 'type' => 'error', 'msg' => 'Nenhum CSV enviado. Faça o upload primeiro.' );
        } else {
            $result = cbv_sync_run( $csv_data, $dry_run );
            $title = $dry_run ? 'Simulação concluída (nada foi salvo)' : 'Sincronização concluída';
            $msg = sprintf(
                '<strong>%s</strong><br>Atualizadas (CBV): %d<br>Criadas (novas fichas aprovadas): %d<br>Ignoradas (dados iguais): %d<br>Erros: %d<br>Total processado: %d',
                esc_html( $title ),
                $result['updated'],
                $result['created'],
                $result['skipped'],
                $result['errors'],
                $result['total']
            );
            $notices[] = array( 'type' => 'success', 'msg' => $msg );
        }
    }

    // LIMPAR LOG
    if ( isset( $_POST['cbv_sync_action'] ) && $_POST['cbv_sync_action'] === 'clear_log' && check_admin_referer( 'cbv_sync_run' ) ) {
        delete_option( CBV_SYNC_LOG_OPTION );
        $notices[] = array( 'type' => 'success', 'msg' => 'Log limpo.' );
    }

    // LIMPAR CSV
    if ( isset( $_POST['cbv_sync_action'] ) && $_POST['cbv_sync_action'] === 'clear_csv' && check_admin_referer( 'cbv_sync_run' ) ) {
        cbv_sync_clear_uploaded_data();
        $notices[] = array( 'type' => 'success', 'msg' => 'CSV removido.' );
    }

    $log      = get_option( CBV_SYNC_LOG_OPTION, array() );
    $log      = array_reverse( $log );
    $last_run = get_option( CBV_SYNC_LAST_RUN_OPTION, '' );
    $csv_data = cbv_sync_get_uploaded_data();
    $has_csv  = ! empty( $csv_data );

    global $wpdb;
    $total_clientes = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'clientes' AND post_status IN ('publish','draft','pending')"
    );

    ?>
    <div class="wrap">
        <h1>Sincronizar CSV - CBV</h1>

        <?php foreach ( $notices as $n ) : ?>
            <div class="notice notice-<?php echo esc_attr( $n['type'] ); ?>"><p><?php echo wp_kses_post( $n['msg'] ); ?></p></div>
        <?php endforeach; ?>

        <div class="notice notice-info inline">
            <p>
                <strong>CSV enviado:</strong> <?php echo $has_csv ? count( $csv_data ) . ' linhas' : '—'; ?><br>
                <strong>Fichas atuais em <code>clientes</code>:</strong> <?php echo $total_clientes; ?><br>
                <strong>Última execução:</strong> <?php echo $last_run ? esc_html( $last_run ) : '—'; ?>
            </p>
        </div>

        <div class="card" style="padding:20px; max-width:100%;">
            <h2>1. Enviar CSV</h2>
            <p>Envie um arquivo <code>.csv</code> com pelo menos as colunas <code>email</code> e <code>Número do CBV</code> (ou <code>numero_do_cbv</code>). As colunas <code>nome_do_aluno</code> e <code>instagram</code> são opcionais.</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'cbv_sync_run' ); ?>
                <input type="hidden" name="cbv_sync_action" value="upload_csv">
                <input type="file" name="cbv_csv_file" accept=".csv" required>
                <button type="submit" class="button button-primary">Enviar CSV</button>
            </form>

            <?php if ( $has_csv ) : ?>
                <p style="margin-top:15px;">
                    <strong><?php echo count( $csv_data ); ?> linhas</strong> prontas para processar.
                </p>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'cbv_sync_run' ); ?>
                    <input type="hidden" name="cbv_sync_action" value="clear_csv">
                    <button type="submit" class="button button-link-delete">Descartar CSV enviado</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ( $has_csv ) : ?>
            <div style="display:flex; gap:20px; margin-top:20px;">

                <div class="card" style="padding:20px; flex:1;">
                    <h2>2. Simular (dry-run)</h2>
                    <p>Roda a lógica <strong>sem salvar nada</strong>. Mostra o que seria feito. Recomendado antes de rodar de verdade.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'cbv_sync_run' ); ?>
                        <input type="hidden" name="cbv_sync_action" value="run_sync">
                        <input type="hidden" name="dry_run" value="1">
                        <button type="submit" class="button">Simular sincronização</button>
                    </form>
                </div>

                <div class="card" style="padding:20px; flex:1;">
                    <h2>3. Executar de verdade</h2>
                    <p><strong>Atenção:</strong> isso vai <strong>alterar</strong> fichas existentes e <strong>criar</strong> novas. Faça backup antes.</p>
                    <form method="post" onsubmit="return confirm('Tem certeza? Isso vai alterar o banco de dados.');">
                        <?php wp_nonce_field( 'cbv_sync_run' ); ?>
                        <input type="hidden" name="cbv_sync_action" value="run_sync">
                        <button type="submit" class="button button-primary">Executar sincronização</button>
                    </form>
                </div>

            </div>
        <?php endif; ?>

        <h2 style="margin-top:30px;">Log da última execução</h2>
        <form method="post" style="margin-bottom:10px;">
            <?php wp_nonce_field( 'cbv_sync_run' ); ?>
            <input type="hidden" name="cbv_sync_action" value="clear_log">
            <button type="submit" class="button">Limpar log</button>
        </form>

        <?php if ( empty( $log ) ) : ?>
            <p><em>Nenhum log registrado ainda. Envie um CSV e rode uma simulação para ver o detalhamento.</em></p>
        <?php else :
            $counts = array( 'updated' => 0, 'created' => 0, 'skipped' => 0, 'error' => 0 );
            foreach ( $log as $entry ) {
                $t = $entry['tipo'] ?? 'info';
                if ( isset( $counts[ $t ] ) ) {
                    $counts[ $t ]++;
                }
            }
        ?>
            <p>
                <span style="background:#cfe2ff;padding:3px 8px;border-radius:3px;">Atualizados: <?php echo $counts['updated']; ?></span>
                <span style="background:#d1e7dd;padding:3px 8px;border-radius:3px;margin-left:5px;">Criados: <?php echo $counts['created']; ?></span>
                <span style="background:#e2e3e5;padding:3px 8px;border-radius:3px;margin-left:5px;">Ignorados: <?php echo $counts['skipped']; ?></span>
                <span style="background:#f8d7da;padding:3px 8px;border-radius:3px;margin-left:5px;">Erros: <?php echo $counts['error']; ?></span>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:140px;">Data/Hora</th>
                        <th style="width:90px;">Tipo</th>
                        <th>Email</th>
                        <th>Detalhes</th>
                        <th style="width:80px;">Ficha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $log as $entry ) :
                        $tipo = $entry['tipo'] ?? 'info';
                        $badges = array(
                            'updated' => '<span style="background:#cfe2ff;color:#084298;padding:2px 8px;border-radius:3px;">Atualizado</span>',
                            'created' => '<span style="background:#d1e7dd;color:#0f5132;padding:2px 8px;border-radius:3px;">Criado</span>',
                            'skipped' => '<span style="background:#e2e3e5;color:#41464b;padding:2px 8px;border-radius:3px;">Ignorado</span>',
                            'error'   => '<span style="background:#f8d7da;color:#842029;padding:2px 8px;border-radius:3px;">Erro</span>',
                            'info'    => '<span style="background:#fff3cd;color:#664d03;padding:2px 8px;border-radius:3px;">Info</span>',
                        );
                        $post_id   = $entry['post_id'] ?? 0;
                        $edit_link = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : '';
                    ?>
                        <tr>
                            <td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
                            <td><?php echo $badges[ $tipo ] ?? esc_html( $tipo ); ?></td>
                            <td><?php echo esc_html( $entry['email'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
                            <td>
                                <?php if ( $edit_link ) : ?>
                                    <a href="<?php echo esc_url( $edit_link ); ?>" target="_blank">#<?php echo intval( $post_id ); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================
// UPLOAD E PARSER DE CSV
// ============================================================

/**
 * Processa o upload do CSV e guarda os dados parseados em option.
 */
function cbv_sync_handle_upload() {
    if ( empty( $_FILES['cbv_csv_file'] ) || ! isset( $_FILES['cbv_csv_file']['tmp_name'] ) ) {
        return new WP_Error( 'no_file', 'Nenhum arquivo enviado.' );
    }

    $file = $_FILES['cbv_csv_file'];

    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', 'Erro no upload (código ' . $file['error'] . ').' );
    }

    $tmp_name = $file['tmp_name'];
    if ( ! is_uploaded_file( $tmp_name ) ) {
        return new WP_Error( 'invalid_file', 'Arquivo inválido.' );
    }

    // Ler o CSV
    $parsed = cbv_sync_parse_csv( $tmp_name );
    if ( is_wp_error( $parsed ) ) {
        return $parsed;
    }

    if ( empty( $parsed ) ) {
        return new WP_Error( 'empty_csv', 'CSV vazio ou sem linhas válidas com email.' );
    }

    // Guardar os dados parseados em uma option (mais seguro do que manter o arquivo)
    update_option( CBV_SYNC_UPLOAD_OPTION, $parsed, false );

    return array( 'count' => count( $parsed ) );
}

/**
 * Faz o parse do CSV. Aceita diferentes nomes de coluna (case-insensitive).
 * Colunas reconhecidas:
 *   email -> 'email'
 *   cbv -> 'Número do CBV', 'numero_do_cbv', 'cbv'
 *   nome -> 'nome_do_aluno', 'nome', 'name'
 *   instagram -> 'instagram'
 */
function cbv_sync_parse_csv( $filepath ) {
    if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
        return new WP_Error( 'read_error', 'Não foi possível ler o arquivo.' );
    }

    $handle = fopen( $filepath, 'r' );
    if ( ! $handle ) {
        return new WP_Error( 'open_error', 'Não foi possível abrir o arquivo.' );
    }

    // Remover BOM se existir
    $bom = fread( $handle, 3 );
    if ( $bom !== "\xef\xbb\xbf" ) {
        rewind( $handle );
    }

    // Detectar delimitador (, ou ;)
    $first_line = fgets( $handle );
    if ( $first_line === false ) {
        fclose( $handle );
        return new WP_Error( 'empty_file', 'Arquivo vazio.' );
    }

    $delimiter = ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) ? ';' : ',';

    // Voltar para o início (pulando BOM se tinha)
    rewind( $handle );
    if ( $bom === "\xef\xbb\xbf" ) {
        fread( $handle, 3 );
    }

    // Ler cabeçalho
    $header = fgetcsv( $handle, 0, $delimiter );
    if ( ! $header ) {
        fclose( $handle );
        return new WP_Error( 'no_header', 'CSV sem cabeçalho.' );
    }

    // Normalizar cabeçalho (trim, lowercase)
    $header_map = array();
    foreach ( $header as $idx => $name ) {
        $name_lower = strtolower( trim( $name ) );
        $header_map[ $idx ] = $name_lower;
    }

    // Encontrar os índices das colunas que nos interessam
    $col_idx = array(
        'email'     => cbv_sync_find_column( $header_map, array( 'email', 'e-mail', 'e_mail' ) ),
        'cbv'       => cbv_sync_find_column( $header_map, array( 'número do cbv', 'numero do cbv', 'numero_do_cbv', 'cbv' ) ),
        'nome'      => cbv_sync_find_column( $header_map, array( 'nome_do_aluno', 'nome do aluno', 'nome', 'name' ) ),
        'instagram' => cbv_sync_find_column( $header_map, array( 'instagram', 'insta' ) ),
    );

    if ( $col_idx['email'] === false ) {
        fclose( $handle );
        return new WP_Error( 'missing_email_column', 'Coluna "email" não encontrada no CSV.' );
    }

    if ( $col_idx['cbv'] === false ) {
        fclose( $handle );
        return new WP_Error( 'missing_cbv_column', 'Coluna "Número do CBV" (ou numero_do_cbv / cbv) não encontrada no CSV.' );
    }

    // Ler linhas
    $records = array();
    $seen_emails = array();

    while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
        $email = isset( $row[ $col_idx['email'] ] ) ? strtolower( trim( $row[ $col_idx['email'] ] ) ) : '';
        $cbv   = isset( $row[ $col_idx['cbv'] ] ) ? trim( $row[ $col_idx['cbv'] ] ) : '';
        $nome  = ( $col_idx['nome'] !== false && isset( $row[ $col_idx['nome'] ] ) ) ? trim( $row[ $col_idx['nome'] ] ) : '';
        $insta = ( $col_idx['instagram'] !== false && isset( $row[ $col_idx['instagram'] ] ) ) ? trim( $row[ $col_idx['instagram'] ] ) : '';

        if ( empty( $email ) ) {
            continue;
        }
        if ( isset( $seen_emails[ $email ] ) ) {
            continue; // dedup
        }
        $seen_emails[ $email ] = true;

        $records[] = array(
            'email'     => $email,
            'cbv'       => $cbv,
            'nome'      => $nome,
            'instagram' => $insta,
        );
    }

    fclose( $handle );
    return $records;
}

/**
 * Procura o índice de uma coluna pelo nome (case-insensitive, múltiplos aliases).
 */
function cbv_sync_find_column( $header_map, $aliases ) {
    foreach ( $header_map as $idx => $name ) {
        foreach ( $aliases as $alias ) {
            if ( $name === strtolower( $alias ) ) {
                return $idx;
            }
        }
    }
    return false;
}

/**
 * Retorna os dados do CSV atualmente enviado.
 */
function cbv_sync_get_uploaded_data() {
    $data = get_option( CBV_SYNC_UPLOAD_OPTION, array() );
    return is_array( $data ) ? $data : array();
}

/**
 * Limpa os dados do CSV enviado.
 */
function cbv_sync_clear_uploaded_data() {
    delete_option( CBV_SYNC_UPLOAD_OPTION );
}

// ============================================================
// LÓGICA PRINCIPAL DE SYNC
// ============================================================
function cbv_sync_run( $data, $dry_run = false ) {
    $new_log = array();

    $updated = 0;
    $created = 0;
    $skipped = 0;
    $errors  = 0;

    foreach ( $data as $row ) {
        $email     = strtolower( trim( $row['email'] ) );
        $cbv       = trim( $row['cbv'] );
        $nome      = trim( $row['nome'] );
        $instagram = trim( $row['instagram'] );

        if ( empty( $email ) ) {
            continue;
        }

        // Buscar ficha existente por email (case-insensitive)
        $existing_post_id = cbv_sync_find_ficha_by_email( $email );

        if ( $existing_post_id ) {
            // JÁ EXISTE - atualizar somente o CBV
            $current_cbv = get_post_meta( $existing_post_id, 'numero_do_cbv', true );

            if ( trim( $current_cbv ) === $cbv && ! empty( $cbv ) ) {
                $skipped++;
                $new_log[] = array(
                    'time'    => current_time( 'mysql' ),
                    'tipo'    => 'skipped',
                    'email'   => $email,
                    'message' => 'Ficha existe e CBV já está igual (' . $cbv . '). Nada a fazer.',
                    'post_id' => $existing_post_id,
                );
                continue;
            }

            if ( empty( $cbv ) ) {
                $skipped++;
                $new_log[] = array(
                    'time'    => current_time( 'mysql' ),
                    'tipo'    => 'skipped',
                    'email'   => $email,
                    'message' => 'Ficha existe mas CSV não trouxe CBV. Nada a fazer.',
                    'post_id' => $existing_post_id,
                );
                continue;
            }

            if ( ! $dry_run ) {
                $result = update_post_meta( $existing_post_id, 'numero_do_cbv', $cbv );
                if ( $result === false ) {
                    $errors++;
                    $new_log[] = array(
                        'time'    => current_time( 'mysql' ),
                        'tipo'    => 'error',
                        'email'   => $email,
                        'message' => 'Falha ao atualizar CBV (update_post_meta retornou false).',
                        'post_id' => $existing_post_id,
                    );
                    continue;
                }
            }

            $updated++;
            $new_log[] = array(
                'time'    => current_time( 'mysql' ),
                'tipo'    => 'updated',
                'email'   => $email,
                'message' => ( $dry_run ? '[DRY-RUN] ' : '' ) . 'CBV atualizado de "' . $current_cbv . '" para "' . $cbv . '".',
                'post_id' => $existing_post_id,
            );

        } else {
            // NÃO EXISTE - criar nova ficha aprovada
            if ( $dry_run ) {
                $created++;
                $new_log[] = array(
                    'time'    => current_time( 'mysql' ),
                    'tipo'    => 'created',
                    'email'   => $email,
                    'message' => '[DRY-RUN] Criaria ficha "' . ( $nome ?: $email ) . '" com status Aprovado e CBV "' . $cbv . '".',
                    'post_id' => 0,
                );
                continue;
            }

            $title = ! empty( $nome ) ? $nome : $email;

            $post_id = wp_insert_post( array(
                'post_title'  => $title,
                'post_type'   => 'clientes',
                'post_status' => 'publish',
            ), true );

            if ( is_wp_error( $post_id ) || ! $post_id ) {
                $errors++;
                $new_log[] = array(
                    'time'    => current_time( 'mysql' ),
                    'tipo'    => 'error',
                    'email'   => $email,
                    'message' => 'Falha ao criar post: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'ID vazio' ),
                    'post_id' => 0,
                );
                continue;
            }

            update_post_meta( $post_id, 'nome_do_aluno', $nome );
            update_post_meta( $post_id, 'email', $email );
            update_post_meta( $post_id, 'instagram', $instagram );
            update_post_meta( $post_id, 'numero_do_cbv', $cbv );
            update_post_meta( $post_id, '_cbv_status', 'aprovado' );
            update_post_meta( $post_id, '_cbv_source', 'csv_sync' );

            $created++;
            $new_log[] = array(
                'time'    => current_time( 'mysql' ),
                'tipo'    => 'created',
                'email'   => $email,
                'message' => 'Ficha criada e aprovada. Nome: "' . $title . '", CBV: "' . $cbv . '".',
                'post_id' => $post_id,
            );
        }
    }

    update_option( CBV_SYNC_LOG_OPTION, $new_log, false );
    update_option( CBV_SYNC_LAST_RUN_OPTION, current_time( 'mysql' ) . ( $dry_run ? ' (simulação)' : '' ), false );

    return array(
        'total'   => count( $data ),
        'updated' => $updated,
        'created' => $created,
        'skipped' => $skipped,
        'errors'  => $errors,
    );
}

/**
 * Busca ficha no CPT clientes pelo email (meta_key 'email'), case-insensitive.
 */
function cbv_sync_find_ficha_by_email( $email ) {
    global $wpdb;
    $email = strtolower( trim( $email ) );
    if ( empty( $email ) ) {
        return 0;
    }

    $post_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.ID
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'clientes'
         AND p.post_status IN ('publish','draft','pending')
         AND pm.meta_key = 'email'
         AND LOWER(pm.meta_value) = %s
         LIMIT 1",
        $email
    ) );

    return intval( $post_id );
}
