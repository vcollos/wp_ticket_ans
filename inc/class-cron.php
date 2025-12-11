<?php
if (!defined('ABSPATH')) {
    exit;
}

class ANS_Tickets_Cron
{
    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'register_interval']);
        add_action('ans_tickets_sla_cron', [self::class, 'run_sla_check']);
    }

    public static function register_interval(array $schedules): array
    {
        $schedules['ans_tickets_5min'] = [
            'interval' => 5 * 60,
            'display' => 'ANS Tickets - 5 minutos',
        ];
        return $schedules;
    }

    public static function run_sla_check(): void
    {
        global $wpdb;
        $tickets_table = ans_tickets_table('tickets');
        $dept_table = ans_tickets_table('departamentos');
        $inter_table = ans_tickets_table('interacoes');

        $rows = $wpdb->get_results(
            "SELECT t.id, t.protocolo, t.responsavel_id, t.created_at, t.status, d.nome AS departamento_nome, d.sla_hours
             FROM {$tickets_table} t
             LEFT JOIN {$dept_table} d ON t.departamento_id = d.id
             WHERE d.sla_hours IS NOT NULL
             AND d.sla_hours > 0
             AND t.status NOT IN ('resolvido','fechado','arquivado','aguardando_acao')",
            ARRAY_A
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $created = strtotime($row['created_at']);
            $elapsedHours = (time() - $created) / 3600;
            if ($elapsedHours <= (float)$row['sla_hours']) {
                continue;
            }
            self::update_to_sla_breach($row, $tickets_table, $inter_table);
        }
    }

    private static function update_to_sla_breach(array $row, string $tickets_table, string $inter_table): void
    {
        global $wpdb;
        $wpdb->update(
            $tickets_table,
            [
                'status' => 'aguardando_acao',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int)$row['id']]
        );

        $message = sprintf(
            'SLA estourado (%.1fh / SLA %sh) no departamento %s. Status alterado para aguardando_acao automaticamente.',
            (time() - strtotime($row['created_at'])) / 3600,
            $row['sla_hours'],
            $row['departamento_nome'] ?? 'N/A'
        );

        $wpdb->insert($inter_table, [
            'ticket_id' => (int)$row['id'],
            'autor_tipo' => 'sistema',
            'autor_id' => 0,
            'mensagem' => $message,
            'interno' => 1,
        ]);

        if (!empty($row['responsavel_id'])) {
            self::notify_agent((int)$row['responsavel_id'], $row['protocolo'], $message);
        }
    }

    private static function notify_agent(int $user_id, string $protocolo, string $message): void
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return;
        }
        $subject = sprintf('[ANS Tickets] SLA estourado - protocolo %s', $protocolo);
        $body = sprintf(
            "Protocolo: %s\nMensagem: %s\nAcesse o painel para agir.",
            $protocolo,
            $message
        );
        wp_mail($user->user_email, $subject, $body);
    }
}
