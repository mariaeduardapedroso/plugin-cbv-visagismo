<?php
/**
 * Plugin Name: CBV Approval Email
 * Description: Envia email automático ao aluno quando a ficha dele é aprovada pelo admin. Complementa o plugin CBV Formandos Manager.
 * Version: 1.2.0
 * Author: Visage Education
 * Text Domain: cbv-approval-email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CBV_AE_VERSION', '1.2.0' );
define( 'CBV_AE_OPTION', 'cbv_approval_email_settings' );
define( 'CBV_AE_LOG_OPTION', 'cbv_approval_email_log' );
define( 'CBV_AE_META_SENT', '_cbv_approval_email_sent_count' );
define( 'CBV_AE_META_LAST_SENT', '_cbv_approval_email_last_sent' );
define( 'CBV_AE_META_NOTIFIED', '_cbv_last_notified_status' );

// ============================================================
// SETTINGS & LOG
// ============================================================

/**
 * Retorna as configurações do email (com defaults).
 */
function cbv_ae_get_settings() {
    $defaults = array(
        'enabled'      => 0,
        'from_email'   => get_option( 'admin_email' ),
        'from_name'    => 'Visage Education',
        'subject'      => 'Sua certificação CBV foi aprovada!',
        'message'      => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <h2 style="color: #1822DC; margin-bottom: 20px;">Parabéns, {nome}!</h2>

    <p style="font-size: 16px; line-height: 1.6;">
        Sua certificação como <strong>Barbeiro Visagista</strong> foi aprovada pela <strong>Visage Education</strong>.
    </p>

    <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #1822DC; margin: 25px 0;">
        <p style="margin: 0; font-size: 14px; color: #666;">Seu número CBV:</p>
        <p style="margin: 5px 0 0; font-size: 22px; font-weight: bold; color: #1822DC;">{cbv}</p>
    </div>

    <p style="text-align: center; margin-top: 30px;">
        <a href="https://visageducation.com/formandos/" style="background: #1822DC; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
            Ver minha certificação
        </a>
    </p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

    <p style="text-align: center; color: #999; font-size: 12px;">
        Atenciosamente,<br>
        <strong>Equipe Visage Education</strong>
    </p>
</div>',
        'activated_at' => 0,
    );
    $saved = get_option( CBV_AE_OPTION, array() );
    return wp_parse_args( $saved, $defaults );
}

/**
 * Log de emails enviados (últimos 100).
 */
function cbv_ae_log( $entry ) {
    $log = get_option( CBV_AE_LOG_OPTION, array() );
    $log[] = array_merge( array( 'time' => current_time( 'mysql' ) ), $entry );
    if ( count( $log ) > 100 ) {
        $log = array_slice( $log, -100 );
    }
    update_option( CBV_AE_LOG_OPTION, $log, false );
}

// ============================================================
// HOOKS - DETECÇÃO DE APROVAÇÃO
// ============================================================

/**
 * Captura o status ANTES do save para comparar com o novo.
 */
/**
 * Hook principal: detecta mudança de _cbv_status para 'aprovado'
 * e envia email UMA VEZ por transição.
 *
 * Usa meta persistente `_cbv_last_notified_status` para rastrear estado,
 * em vez de transient (que tinha problemas com saves aninhados).
 *
 * Regra (Opção B - manda toda vez que aprovar):
 * - Status mudou para 'aprovado' E flag != 'aprovado'  → ENVIA, seta flag
 * - Status já é 'aprovado' E flag == 'aprovado'        → NÃO envia (evita duplicado)
 * - Status mudou para outro (rejeitado/pendente)       → apaga a flag (próxima aprovação dispara)
 *
 * Prioridade 100 para rodar DEPOIS de todos os outros handlers.
 */
add_action( 'save_post_clientes', 'cbv_ae_check_approval_notification', 100, 3 );

function cbv_ae_check_approval_notification( $post_id, $post, $update ) {
    // Evitar autosave/revisions
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    // CAMADA 4: Só em contexto de admin (evita cron/APIs)
    if ( ! is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        return;
    }

    $settings      = cbv_ae_get_settings();
    $current       = get_post_meta( $post_id, '_cbv_status', true );
    $last_notified = get_post_meta( $post_id, CBV_AE_META_NOTIFIED, true );

    // Se status atual NÃO é 'aprovado', resetar a flag (próxima aprovação dispara)
    if ( $current !== 'aprovado' ) {
        if ( ! empty( $last_notified ) ) {
            delete_post_meta( $post_id, CBV_AE_META_NOTIFIED );
        }
        return;
    }

    // Status é 'aprovado'

    // CAMADA 1: Desativado por padrão
    if ( empty( $settings['enabled'] ) ) {
        return;
    }

    // CAMADA 2: Se já notificamos para este "aprovado", não duplica
    if ( $last_notified === 'aprovado' ) {
        return;
    }

    // CAMADA 3: Exclui fichas criadas pelo CSV Sync
    $source = get_post_meta( $post_id, '_cbv_source', true );
    if ( $source === 'csv_sync' ) {
        update_post_meta( $post_id, CBV_AE_META_NOTIFIED, 'aprovado' );
        cbv_ae_log( array(
            'post_id' => $post_id,
            'email'   => get_post_meta( $post_id, 'email', true ),
            'status'  => 'skipped',
            'reason'  => 'Ficha criada pelo CSV Sync - email não enviado.',
        ) );
        return;
    }

    // Enviar!
    $result = cbv_ae_send_approval( $post_id );
    if ( ! is_wp_error( $result ) ) {
        update_post_meta( $post_id, CBV_AE_META_NOTIFIED, 'aprovado' );
    }
}

/**
 * Envia email de aprovação para uma ficha específica.
 */
function cbv_ae_send_approval( $post_id, $test_email = '' ) {
    $settings = cbv_ae_get_settings();

    $nome      = get_post_meta( $post_id, 'nome_do_aluno', true );
    $email     = $test_email ?: get_post_meta( $post_id, 'email', true );
    $cbv       = get_post_meta( $post_id, 'numero_do_cbv', true );
    $instagram = get_post_meta( $post_id, 'instagram', true );
    $cidade    = get_post_meta( $post_id, 'nome_da_cidade', true );
    $estado    = get_post_meta( $post_id, 'nome_do_estado', true );

    if ( empty( $email ) || ! is_email( $email ) ) {
        cbv_ae_log( array(
            'post_id' => $post_id,
            'email'   => $email,
            'status'  => 'error',
            'reason'  => 'Email inválido ou vazio.',
        ) );
        return new WP_Error( 'invalid_email', 'Email inválido' );
    }

    // Substituir variáveis
    $replacements = array(
        '{nome}'      => $nome,
        '{email}'     => $email,
        '{cbv}'       => $cbv,
        '{instagram}' => $instagram,
        '{cidade}'    => $cidade,
        '{estado}'    => $estado,
    );

    $subject = strtr( $settings['subject'], $replacements );
    $message = strtr( $settings['message'], $replacements );

    $headers = array();
    if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
        $from_name = $settings['from_name'] ?: 'Visage Education';
        $headers[] = 'From: ' . $from_name . ' <' . $settings['from_email'] . '>';
    }
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $sent = wp_mail( $email, $subject, $message, $headers );

    if ( $sent ) {
        if ( ! $test_email ) {
            $count = (int) get_post_meta( $post_id, CBV_AE_META_SENT, true );
            update_post_meta( $post_id, CBV_AE_META_SENT, $count + 1 );
            update_post_meta( $post_id, CBV_AE_META_LAST_SENT, current_time( 'mysql' ) );
        }
        cbv_ae_log( array(
            'post_id' => $post_id,
            'email'   => $email,
            'status'  => 'sent',
            'reason'  => $test_email ? 'Email de teste enviado.' : 'Enviado automaticamente após aprovação.',
        ) );
        return true;
    }

    cbv_ae_log( array(
        'post_id' => $post_id,
        'email'   => $email,
        'status'  => 'error',
        'reason'  => 'wp_mail() retornou false. Verifique configurações do WP Mail SMTP.',
    ) );
    return new WP_Error( 'send_failed', 'wp_mail() falhou' );
}

// ============================================================
// ADMIN PAGE
// ============================================================
add_action( 'admin_menu', 'cbv_ae_add_menu' );

function cbv_ae_add_menu() {
    // Verifica se o CPT 'clientes' existe (plugin cbv-formandos-manager precisa estar ativo)
    if ( post_type_exists( 'clientes' ) ) {
        add_submenu_page(
            'edit.php?post_type=clientes',
            'Email de Aprovação',
            'Email de Aprovação',
            'manage_options',
            'cbv-approval-email',
            'cbv_ae_render_page'
        );
    } else {
        // Fallback: adiciona como menu solto com aviso
        add_menu_page(
            'Email de Aprovação',
            'Email de Aprovação',
            'manage_options',
            'cbv-approval-email',
            'cbv_ae_render_page_no_cpt',
            'dashicons-email-alt',
            26
        );
    }
}

function cbv_ae_render_page_no_cpt() {
    echo '<div class="wrap"><h1>Email de Aprovação</h1>';
    echo '<div class="notice notice-error"><p><strong>Este plugin depende do CBV Formandos Manager.</strong> Ative o plugin principal primeiro.</p></div>';
    echo '</div>';
}

function cbv_ae_render_page() {
    // Processar ações
    if ( isset( $_POST['cbv_ae_action'] ) && check_admin_referer( 'cbv_ae_config' ) ) {
        $action = sanitize_text_field( $_POST['cbv_ae_action'] );

        if ( $action === 'save' ) {
            $new_settings = array(
                'enabled'    => isset( $_POST['enabled'] ) ? 1 : 0,
                'from_email' => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
                'from_name'  => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
                'subject'    => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
                'message'    => wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) ),
            );

            $current = cbv_ae_get_settings();

            if ( $new_settings['enabled'] && empty( $current['activated_at'] ) ) {
                $new_settings['activated_at'] = time();
            } else {
                $new_settings['activated_at'] = $current['activated_at'];
            }

            update_option( CBV_AE_OPTION, $new_settings, false );
            echo '<div class="notice notice-success"><p>Configurações salvas.</p></div>';
        }

        if ( $action === 'send_test' ) {
            $test_email = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
            if ( ! is_email( $test_email ) ) {
                echo '<div class="notice notice-error"><p>Informe um email de teste válido.</p></div>';
            } else {
                // Procura QUALQUER ficha (aprovada, pendente ou rascunho) para usar como base
                $sample_post = get_posts( array(
                    'post_type'   => 'clientes',
                    'post_status' => array( 'publish', 'draft', 'pending', 'any' ),
                    'numberposts' => 1,
                    'fields'      => 'ids',
                    'orderby'     => 'ID',
                    'order'       => 'DESC',
                ) );

                if ( empty( $sample_post ) ) {
                    // Envia email de teste com dados falsos
                    $result = cbv_ae_send_test_with_fake_data( $test_email );
                } else {
                    $result = cbv_ae_send_approval( $sample_post[0], $test_email );
                }

                if ( is_wp_error( $result ) ) {
                    echo '<div class="notice notice-error"><p>Falha no envio: ' . esc_html( $result->get_error_message() ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>Email de teste enviado para <strong>' . esc_html( $test_email ) . '</strong>! Verifique sua caixa (e spam).</p></div>';
                }
            }
        }

        if ( $action === 'clear_log' ) {
            delete_option( CBV_AE_LOG_OPTION );
            echo '<div class="notice notice-success"><p>Log limpo.</p></div>';
        }
    }

    $settings = cbv_ae_get_settings();
    $log      = get_option( CBV_AE_LOG_OPTION, array() );
    $log      = array_reverse( $log );

    ?>
    <div class="wrap">
        <h1>Email de Aprovação</h1>

        <?php if ( empty( $settings['enabled'] ) ) : ?>
            <div class="notice notice-warning"><p><strong>Envio automático está DESATIVADO.</strong> Nenhum email será enviado ao aprovar fichas. Ative abaixo para começar.</p></div>
        <?php else : ?>
            <div class="notice notice-success"><p><strong>Envio automático ATIVO.</strong> Ativado em: <?php echo $settings['activated_at'] ? esc_html( wp_date( 'd/m/Y H:i', $settings['activated_at'] ) ) : '—'; ?></p></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'cbv_ae_config' ); ?>
            <input type="hidden" name="cbv_ae_action" value="save">

            <table class="form-table">
                <tr>
                    <th><label for="enabled">Ativar envio automático</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
                            Enviar email automaticamente quando uma ficha for aprovada
                        </label>
                        <p class="description">Quando desligado, nenhum email é enviado - mesmo ao aprovar fichas.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="from_email">Email do remetente (From)</label></th>
                    <td>
                        <input type="email" name="from_email" id="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" class="regular-text">
                        <p class="description">Use um email do mesmo domínio do site (ex: resposta-formulario@visageducation.com).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="from_name">Nome do remetente</label></th>
                    <td>
                        <input type="text" name="from_name" id="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="subject">Assunto do email</label></th>
                    <td>
                        <input type="text" name="subject" id="subject" value="<?php echo esc_attr( $settings['subject'] ); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="message">Mensagem (aceita HTML)</label></th>
                    <td>
                        <textarea name="message" id="message" rows="14" class="large-text code"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
                        <p class="description">
                            <strong>Variáveis disponíveis:</strong>
                            <code>{nome}</code>
                            <code>{email}</code>
                            <code>{cbv}</code>
                            <code>{instagram}</code>
                            <code>{cidade}</code>
                            <code>{estado}</code>
                        </p>
                        <p class="description">
                            <strong>Dica:</strong> você pode usar tags HTML como <code>&lt;h2&gt;</code>, <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;a href=""&gt;</code>, <code>&lt;br&gt;</code>, <code>&lt;img&gt;</code>, etc.
                            Para estilos use <code>style="..."</code> direto no elemento (ex: <code>&lt;p style="color: blue;"&gt;...&lt;/p&gt;</code>).
                            Quebra de linha: use <code>&lt;br&gt;</code> ou <code>&lt;/p&gt;&lt;p&gt;</code>.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Salvar alterações</button>
            </p>
        </form>

        <hr>

        <h2>Enviar email de teste</h2>
        <p>Envia um email de teste usando a ficha mais recente (aprovada, pendente ou rascunho). Se não houver nenhuma ficha, usa dados fictícios.</p>
        <form method="post">
            <?php wp_nonce_field( 'cbv_ae_config' ); ?>
            <input type="hidden" name="cbv_ae_action" value="send_test">
            <input type="email" name="test_email" placeholder="seu-email@teste.com" class="regular-text" required>
            <button type="submit" class="button">Enviar teste</button>
        </form>

        <hr>

        <h2>Log de emails</h2>
        <form method="post" style="margin-bottom:10px;">
            <?php wp_nonce_field( 'cbv_ae_config' ); ?>
            <input type="hidden" name="cbv_ae_action" value="clear_log">
            <button type="submit" class="button">Limpar log</button>
        </form>

        <?php if ( empty( $log ) ) : ?>
            <p><em>Nenhum email enviado ainda.</em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:140px;">Data/Hora</th>
                        <th style="width:80px;">Status</th>
                        <th>Email</th>
                        <th>Motivo</th>
                        <th style="width:80px;">Ficha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $log as $entry ) :
                        $status   = $entry['status'] ?? 'info';
                        $badges = array(
                            'sent'    => '<span style="background:#d1e7dd;color:#0f5132;padding:2px 8px;border-radius:3px;">Enviado</span>',
                            'error'   => '<span style="background:#f8d7da;color:#842029;padding:2px 8px;border-radius:3px;">Erro</span>',
                            'skipped' => '<span style="background:#e2e3e5;color:#41464b;padding:2px 8px;border-radius:3px;">Ignorado</span>',
                        );
                        $post_id  = $entry['post_id'] ?? 0;
                        $edit_url = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : '';
                    ?>
                        <tr>
                            <td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
                            <td><?php echo $badges[ $status ] ?? esc_html( $status ); ?></td>
                            <td><?php echo esc_html( $entry['email'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $entry['reason'] ?? '' ); ?></td>
                            <td>
                                <?php if ( $edit_url ) : ?>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank">#<?php echo intval( $post_id ); ?></a>
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

/**
 * Envia um email de teste com dados fictícios (quando não há fichas cadastradas).
 */
function cbv_ae_send_test_with_fake_data( $test_email ) {
    $settings = cbv_ae_get_settings();

    $replacements = array(
        '{nome}'      => 'João Silva (teste)',
        '{email}'     => $test_email,
        '{cbv}'       => 'CBV - 000000',
        '{instagram}' => '@joaosilva',
        '{cidade}'    => 'São Paulo',
        '{estado}'    => 'São Paulo - SP',
    );

    $subject = '[TESTE] ' . strtr( $settings['subject'], $replacements );
    $message = '<p style="background:#fff3cd; color:#664d03; padding:10px; border-radius:4px; font-family:Arial,sans-serif;"><strong>[Este é um email de teste com dados fictícios]</strong></p>' . strtr( $settings['message'], $replacements );

    $headers = array();
    if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
        $from_name = $settings['from_name'] ?: 'Visage Education';
        $headers[] = 'From: ' . $from_name . ' <' . $settings['from_email'] . '>';
    }
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $sent = wp_mail( $test_email, $subject, $message, $headers );

    cbv_ae_log( array(
        'post_id' => 0,
        'email'   => $test_email,
        'status'  => $sent ? 'sent' : 'error',
        'reason'  => $sent ? 'Email de teste (dados fictícios) enviado.' : 'Falha ao enviar email de teste.',
    ) );

    return $sent ? true : new WP_Error( 'send_failed', 'wp_mail() falhou' );
}
