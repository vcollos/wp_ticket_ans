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
    return current_user_can('manage_options') || current_user_can('ans_answer_tickets') || current_user_can('ans_manage_tickets');
}

function ans_tickets_can_manage(): bool
{
    return current_user_can('manage_options') || current_user_can('ans_manage_tickets');
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

function ans_tickets_status_key(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = str_replace('-', '_', $slug);
    return $slug;
}

function ans_tickets_has_global_status_group(): bool
{
    global $wpdb;
    $table = ans_tickets_table('status_custom');
    if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        return false;
    }
    $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE ativo=1 AND departamento_id IS NULL");
    return $count > 0;
}

function ans_tickets_status_group_ready(int $departamento_id): bool
{
    global $wpdb;
    $table = ans_tickets_table('status_custom');
    if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        return false;
    }
    $initial = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ativo=1 AND departamento_id=%d AND inicial=1", $departamento_id));
    $finalOk = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ativo=1 AND departamento_id=%d AND final_resolvido=1", $departamento_id));
    $finalNok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ativo=1 AND departamento_id=%d AND final_nao_resolvido=1", $departamento_id));
    return $initial === 1 && $finalOk === 1 && $finalNok === 1;
}

function ans_tickets_effective_status_group_departamento_id(?int $departamento_id): ?int
{
    if (!$departamento_id) {
        return null; // global
    }
    if (!ans_tickets_has_global_status_group()) {
        return $departamento_id;
    }
    return ans_tickets_status_group_ready($departamento_id) ? $departamento_id : null;
}

function ans_tickets_migrate_tickets_from_global_to_departamento(int $departamento_id): int
{
    global $wpdb;
    $statusTable = ans_tickets_table('status_custom');
    $ticketsTable = ans_tickets_table('tickets');

    if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $statusTable))) {
        return 0;
    }

    // Migra apenas quando o grupo do departamento estiver completo.
    if (!ans_tickets_status_group_ready($departamento_id)) {
        return 0;
    }

    $global = $wpdb->get_results(
        "SELECT slug, inicial, final_resolvido, final_nao_resolvido, ordem
         FROM {$statusTable}
         WHERE ativo=1 AND departamento_id IS NULL
         ORDER BY ordem ASC, nome ASC",
        ARRAY_A
    );
    if (!$global) {
        return 0;
    }

    $dept = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT slug, inicial, final_resolvido, final_nao_resolvido, ordem
             FROM {$statusTable}
             WHERE ativo=1 AND departamento_id=%d
             ORDER BY ordem ASC, nome ASC",
            $departamento_id
        ),
        ARRAY_A
    );
    if (!$dept) {
        return 0;
    }

    $deptInitial = null;
    $deptFinalOk = null;
    $deptFinalNok = null;
    $deptMiddle = null;
    foreach ($dept as $row) {
        if ((int)$row['inicial'] === 1) {
            $deptInitial = $row['slug'];
        } elseif ((int)$row['final_resolvido'] === 1) {
            $deptFinalOk = $row['slug'];
        } elseif ((int)$row['final_nao_resolvido'] === 1) {
            $deptFinalNok = $row['slug'];
        } elseif (!$deptMiddle) {
            $deptMiddle = $row['slug'];
        }
    }
    if (!$deptInitial) {
        $deptInitial = $dept[0]['slug'];
    }
    if (!$deptMiddle) {
        $deptMiddle = $deptInitial;
    }

    // Mapa global (por categoria) -> status do departamento.
    $map = [];
    foreach ($global as $g) {
        $k = ans_tickets_status_key((string)$g['slug']);
        if ((int)$g['inicial'] === 1) {
            $map[$k] = $deptInitial;
        } elseif ((int)$g['final_resolvido'] === 1) {
            $map[$k] = $deptFinalOk ?: $deptInitial;
        } elseif ((int)$g['final_nao_resolvido'] === 1) {
            $map[$k] = $deptFinalNok ?: $deptInitial;
        } else {
            $map[$k] = $deptMiddle;
        }
    }

    // Variantes "_" <-> "-" para pegar tickets antigos.
    $candidates = array_values(array_unique(array_merge(
        array_map(fn($g) => (string)$g['slug'], $global),
        array_map(fn($g) => str_replace('-', '_', (string)$g['slug']), $global)
    )));

    $placeholders = implode(',', array_fill(0, count($candidates), '%s'));
    $query = $wpdb->prepare(
        "SELECT id, status FROM {$ticketsTable} WHERE departamento_id=%d AND status IN ({$placeholders})",
        $departamento_id,
        ...$candidates
    );
    $rows = $wpdb->get_results($query, ARRAY_A);
    if (!$rows) {
        return 0;
    }

    $migrated = 0;
    foreach ($rows as $t) {
        $key = ans_tickets_status_key((string)$t['status']);
        if (!isset($map[$key])) {
            continue;
        }
        $new = $map[$key];
        if ($new === $t['status']) {
            continue;
        }
        $ok = $wpdb->update($ticketsTable, ['status' => $new, 'updated_at' => current_time('mysql')], ['id' => (int)$t['id']]);
        if ($ok !== false) {
            $migrated++;
        }
    }

    return $migrated;
}

function ans_tickets_pick_departamento_status_by_flag(int $departamento_id, string $flag): ?string
{
    global $wpdb;
    $statusTable = ans_tickets_table('status_custom');
    if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $statusTable))) {
        return null;
    }
    if (!ans_tickets_status_group_ready($departamento_id)) {
        return null;
    }
    if ($flag === 'inicial') {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT slug FROM {$statusTable} WHERE ativo=1 AND departamento_id=%d AND inicial=1 ORDER BY ordem ASC LIMIT 1",
            $departamento_id
        )) ?: null;
    }
    if ($flag === 'final_resolvido') {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT slug FROM {$statusTable} WHERE ativo=1 AND departamento_id=%d AND final_resolvido=1 ORDER BY ordem ASC LIMIT 1",
            $departamento_id
        )) ?: null;
    }
    if ($flag === 'final_nao_resolvido') {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT slug FROM {$statusTable} WHERE ativo=1 AND departamento_id=%d AND final_nao_resolvido=1 ORDER BY ordem ASC LIMIT 1",
            $departamento_id
        )) ?: null;
    }
    // middle (sem flags)
    return $wpdb->get_var($wpdb->prepare(
        "SELECT slug FROM {$statusTable}
         WHERE ativo=1 AND departamento_id=%d AND inicial=0 AND final_resolvido=0 AND final_nao_resolvido=0
         ORDER BY ordem ASC, nome ASC LIMIT 1",
        $departamento_id
    )) ?: null;
}

function ans_tickets_status_label_for(string $slug, ?int $departamento_id = null): string
{
    global $wpdb;
    $slug = sanitize_title($slug);
    $table = ans_tickets_table('status_custom');
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
        $name = null;
        $effective = ans_tickets_effective_status_group_departamento_id($departamento_id);
        if ($effective) {
            $name = $wpdb->get_var($wpdb->prepare(
                "SELECT nome FROM {$table} WHERE slug=%s AND departamento_id=%d AND ativo=1 LIMIT 1",
                $slug,
                $effective
            ));
        }
        if (!$name) {
            $name = $wpdb->get_var($wpdb->prepare(
                "SELECT nome FROM {$table} WHERE slug=%s AND departamento_id IS NULL AND ativo=1 LIMIT 1",
                $slug
            ));
        }
        if ($name) {
            return $name;
        }
    }
    $map = [
        'aberto' => 'Aberto',
        'em_triagem' => 'Em Triagem',
        'aguardando_informacoes_solicitante' => 'Aguardando Informações do Solicitante',
        'em_analise' => 'Em Análise',
        'em_execucao' => 'Em Atendimento / Execução',
        'aguardando_terceiros' => 'Aguardando Terceiros',
        'aguardando_aprovacao' => 'Aguardando Aprovação',
        'solucao_proposta' => 'Solução Proposta',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
        'aguardando_acao' => 'Aguardando Ação',
        // legados (sem sufixo legado para UX limpa)
        'novo' => 'Aberto',
        'atendimento' => 'Em Atendimento',
        'pendente_cliente' => 'Aguardando Cliente',
        'concluido' => 'Concluído',
        'arquivado' => 'Arquivado',
        'financeiro' => 'Financeiro',
        'comercial' => 'Comercial',
        'assistencial' => 'Assistencial',
        'ouvidoria' => 'Ouvidoria',
    ];
    if (isset($map[$slug])) {
        return $map[$slug];
    }
    $slug = str_replace('_', ' ', $slug);
    return ucwords($slug);
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
        $effective = ans_tickets_effective_status_group_departamento_id($departamento_id);
        if ($effective) {
            $slug = $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$table} WHERE inicial=1 AND ativo=1 AND departamento_id=%d ORDER BY ordem ASC LIMIT 1", $effective));
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
