<?php
/**
 * Plugin Name: CBV Sync CSV
 * Description: Sincroniza dados de email/CBV a partir de um CSV embutido. Se o email já existe no CPT clientes, atualiza o CBV. Se não existe, cria uma ficha já com status "Aprovado".
 * Version: 1.0.0
 * Author: Visage Education
 * Text Domain: cbv-sync-csv
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CBV_SYNC_VERSION', '1.0.0' );
define( 'CBV_SYNC_LOG_OPTION', 'cbv_sync_csv_log' );
define( 'CBV_SYNC_LAST_RUN_OPTION', 'cbv_sync_csv_last_run' );

// ============================================================
// DADOS DO CSV EMBUTIDOS NO PLUGIN
// 254 registros extraidos de clientes_todos_campos.csv
// ============================================================
function cbv_sync_get_csv_data() {
    require __DIR__ . '/data/csv-data.php';
    return $cbv_csv_data;
}

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
    // Processar ação
    if ( isset( $_POST['cbv_sync_action'] ) && check_admin_referer( 'cbv_sync_run' ) ) {
        $action = sanitize_text_field( $_POST['cbv_sync_action'] );

        if ( $action === 'run_sync' ) {
            $dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === '1';
            $result = cbv_sync_run( $dry_run );

            $msg_class = 'notice-success';
            $msg_title = $dry_run ? 'Simulação concluída (nada foi salvo)' : 'Sincronização concluída';
            echo '<div class="notice ' . $msg_class . '"><p><strong>' . esc_html( $msg_title ) . '</strong><br>';
            echo 'Atualizadas (CBV): ' . intval( $result['updated'] ) . '<br>';
            echo 'Criadas (novas fichas aprovadas): ' . intval( $result['created'] ) . '<br>';
            echo 'Ignoradas (dados iguais): ' . intval( $result['skipped'] ) . '<br>';
            echo 'Erros: ' . intval( $result['errors'] ) . '<br>';
            echo 'Total processado: ' . intval( $result['total'] );
            echo '</p></div>';
        }

        if ( $action === 'clear_log' ) {
            delete_option( CBV_SYNC_LOG_OPTION );
            echo '<div class="notice notice-success"><p>Log limpo.</p></div>';
        }
    }

    $data = cbv_sync_get_csv_data();
    $log = get_option( CBV_SYNC_LOG_OPTION, array() );
    $log = array_reverse( $log );
    $last_run = get_option( CBV_SYNC_LAST_RUN_OPTION, '' );

    // Contar fichas atuais
    global $wpdb;
    $total_clientes = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'clientes' AND post_status IN ('publish','draft','pending')"
    );

    ?>
    <div class="wrap">
        <h1>Sincronizar CSV - CBV</h1>

        <div class="notice notice-info inline">
            <p>
                <strong>Registros no CSV embutido:</strong> <?php echo count( $data ); ?><br>
                <strong>Fichas atuais no CPT <code>clientes</code>:</strong> <?php echo $total_clientes; ?><br>
                <strong>Última execução:</strong> <?php echo $last_run ? esc_html( $last_run ) : '—'; ?>
            </p>
        </div>

        <div class="card" style="padding:20px; max-width:100%;">
            <h2>Como funciona</h2>
            <p>Para cada um dos <strong><?php echo count( $data ); ?> registros</strong> do CSV embutido, o plugin:</p>
            <ol>
                <li>Busca uma ficha em <code>clientes</code> pelo <strong>email</strong> (case-insensitive)</li>
                <li>
                    <strong>Se encontrou:</strong> atualiza o campo <code>numero_do_cbv</code> com o valor do CSV.<br>
                    <em>Nome, instagram e outros campos não são tocados.</em>
                </li>
                <li>
                    <strong>Se NÃO encontrou:</strong> cria uma nova ficha <strong>já com status "Aprovado"</strong>,
                    preenchendo nome, email, instagram e CBV.
                </li>
                <li>Cada decisão é registrada no log abaixo.</li>
            </ol>
        </div>

        <div style="display:flex; gap:20px; margin-top:20px;">

            <div class="card" style="padding:20px; flex:1;">
                <h2>Simulação (dry-run)</h2>
                <p>Roda a lógica <strong>sem salvar nada</strong>. Mostra o que seria feito. Recomendado antes de rodar de verdade.</p>
                <form method="post">
                    <?php wp_nonce_field( 'cbv_sync_run' ); ?>
                    <input type="hidden" name="cbv_sync_action" value="run_sync">
                    <input type="hidden" name="dry_run" value="1">
                    <button type="submit" class="button">Simular sincronização</button>
                </form>
            </div>

            <div class="card" style="padding:20px; flex:1;">
                <h2>Executar de verdade</h2>
                <p><strong>Atenção:</strong> isso vai <strong>alterar</strong> fichas existentes e <strong>criar</strong> novas. Faça backup antes.</p>
                <form method="post" onsubmit="return confirm('Tem certeza? Isso vai alterar o banco de dados.');">
                    <?php wp_nonce_field( 'cbv_sync_run' ); ?>
                    <input type="hidden" name="cbv_sync_action" value="run_sync">
                    <button type="submit" class="button button-primary">Executar sincronização</button>
                </form>
            </div>

        </div>

        <h2 style="margin-top:30px;">Log da última execução</h2>
        <form method="post" style="margin-bottom:10px;">
            <?php wp_nonce_field( 'cbv_sync_run' ); ?>
            <input type="hidden" name="cbv_sync_action" value="clear_log">
            <button type="submit" class="button">Limpar log</button>
        </form>

        <?php if ( empty( $log ) ) : ?>
            <p><em>Nenhum log registrado ainda. Rode uma simulação ou sincronização para ver o detalhamento.</em></p>
        <?php else : ?>
            <?php
            // Filtros rápidos
            $counts = array(
                'updated' => 0,
                'created' => 0,
                'skipped' => 0,
                'error'   => 0,
            );
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
                        $post_id = $entry['post_id'] ?? 0;
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
// LÓGICA PRINCIPAL DE SYNC
// ============================================================
function cbv_sync_run( $dry_run = false ) {
    $data = cbv_sync_get_csv_data();
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
                // CBV já igual - ignora
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

            // Atualizar CBV
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
                'post_status' => 'publish', // já publica = aprovado
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

            // Salvar meta
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

    // Salvar log (sempre sobrescreve com o da ultima execucao)
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
 * Busca uma ficha no CPT clientes pelo email (meta_key 'email'), case-insensitive.
 * Retorna o post_id ou 0 se não encontrar.
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
