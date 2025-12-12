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
        $status_table = ans_tickets_table('status_custom');

        $rows = $wpdb->get_results(
            "SELECT t.id, t.protocolo, t.responsavel_id, t.created_at, t.status, t.departamento_id, d.nome AS departamento_nome, d.sla_hours,
                    COALESCE(s_dep.final_resolvido, s_glob.final_resolvido, 0) AS final_resolvido,
                    COALESCE(s_dep.final_nao_resolvido, s_glob.final_nao_resolvido, 0) AS final_nao_resolvido
             FROM {$tickets_table} t
             LEFT JOIN {$dept_table} d ON t.departamento_id = d.id
             LEFT JOIN {$status_table} s_dep ON s_dep.ativo=1 AND s_dep.departamento_id=t.departamento_id AND s_dep.slug = REPLACE(t.status,'_','-')
             LEFT JOIN {$status_table} s_glob ON s_glob.ativo=1 AND s_glob.departamento_id IS NULL AND s_glob.slug = REPLACE(t.status,'_','-')
             WHERE d.sla_hours IS NOT NULL
             AND d.sla_hours > 0
             AND COALESCE(s_dep.final_resolvido, s_glob.final_resolvido, 0)=0
             AND COALESCE(s_dep.final_nao_resolvido, s_glob.final_nao_resolvido, 0)=0",
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
        // Evita spam: cria só 1 registro de SLA estourado por ticket.
        $already = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$inter_table} WHERE ticket_id=%d AND autor_tipo='sistema' AND interno=1 AND mensagem LIKE %s LIMIT 1",
            (int)$row['id'],
            'SLA estourado (%'
        ));
        if ($already) {
            return;
        }

        $message = sprintf(
            'SLA estourado (%.1fh / SLA %sh) no departamento %s.',
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

        $wpdb->update(
            $tickets_table,
            [
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int)$row['id']]
        );

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
