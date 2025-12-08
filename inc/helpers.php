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
    $operadora_table = ans_tickets_table('operadora');
    $ans = $wpdb->get_var("SELECT ans_registro FROM {$operadora_table} LIMIT 1");
    $ans = str_pad(preg_replace('/\D/', '', (string)$ans), 6, '0', STR_PAD_RIGHT);
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
