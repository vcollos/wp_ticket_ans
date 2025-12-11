<?php
if (!defined('ABSPATH')) {
    exit;
}

function ans_tickets_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . 'ans_' . $name;
}

function ans_tickets_protocol(): string
{
    global $wpdb;
    $settings = get_option(ANS_TICKETS_OPTION, []);
    $ans = $settings['ans_registro'] ?? '';
    $ans = preg_replace('/\D/', '', (string)$ans);

    if (!$ans) {
        $operadora_table = ans_tickets_table('operadora');
        $ans = $wpdb->get_var("SELECT ans_registro FROM {$operadora_table} LIMIT 1");
        $ans = preg_replace('/\D/', '', (string)$ans);
    }

    $ans = str_pad($ans, 6, '0', STR_PAD_RIGHT);
    $date = current_time('Ymd');
    $seq = get_option('ans_tickets_seq_' . $date);
    if (!$seq) {
        $seq = 1;
    } else {
        $seq = (int)$seq + 1;
    }
    update_option('ans_tickets_seq_' . $date, $seq, false);
    return $ans . $date . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}

function ans_tickets_allowed_mimes(): array
{
    return [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
}

function ans_tickets_max_file_size(): int
{
    return 5 * 1024 * 1024;
}

function ans_tickets_can_answer(): bool
{
    return current_user_can('ans_answer_tickets') || current_user_can('ans_manage_tickets');
}

function ans_tickets_can_manage(): bool
{
    return current_user_can('ans_manage_tickets');
}

function ans_tickets_default_prioridades(): array
{
    return ['baixa', 'media', 'alta'];
}

function ans_tickets_custom_statuses(): array
{
    global $wpdb;
    $table = ans_tickets_table('status_custom');
    if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        return [];
    }
    $rows = $wpdb->get_results("SELECT slug FROM {$table} WHERE ativo=1", ARRAY_A);
    return array_map(function ($row) {
        return $row['slug'];
    }, $rows);
}

function ans_tickets_statuses(): array
{
    $base = [
        'aberto',
        'em_triagem',
        'aguardando_informacoes_solicitante',
        'em_analise',
        'em_execucao',
        'aguardando_terceiros',
        'aguardando_aprovacao',
        'solucao_proposta',
        'resolvido',
        'fechado',
        'aguardando_acao',
        // legados
        'novo',
        'atendimento',
        'financeiro',
        'comercial',
        'assistencial',
        'ouvidoria',
        'concluido',
        'arquivado',
        'pendente_cliente',
    ];
    $custom = ans_tickets_custom_statuses();
    return array_values(array_unique(array_merge($base, $custom)));
}

function ans_tickets_initial_status(?int $departamento_id = null): string
{
    global $wpdb;
    $table = ans_tickets_table('status_custom');
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        $slug = null;
        if ($departamento_id) {
            $slug = $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$table} WHERE inicial=1 AND ativo=1 AND departamento_id=%d ORDER BY ordem ASC LIMIT 1", $departamento_id));
        }
        if (!$slug) {
            $slug = $wpdb->get_var("SELECT slug FROM {$table} WHERE inicial=1 AND ativo=1 AND departamento_id IS NULL ORDER BY ordem ASC LIMIT 1");
        }
        if ($slug) {
            return $slug;
        }
    }
    return 'aberto';
}
