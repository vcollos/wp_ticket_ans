<?php
if (!defined('ABSPATH')) {
    exit;
}

class ANS_Tickets_Routes
{
    public static function register(): void
    {
        register_rest_route(ANS_TICKETS_NAMESPACE, '/tickets', [
            'methods' => 'POST',
            'callback' => [self::class, 'create_ticket'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/login', [
            'methods' => 'POST',
            'callback' => [self::class, 'login_cliente'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/tickets/(?P<protocol>[A-Za-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_ticket'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/tickets/(?P<protocol>[A-Za-z0-9-]+)/messages', [
            'methods' => 'POST',
            'callback' => [self::class, 'add_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/tickets/recover', [
            'methods' => 'POST',
            'callback' => [self::class, 'recover_tickets'],
            'permission_callback' => '__return_true',
        ]);

        // Departamentos públicos (para formulário)
        register_rest_route(ANS_TICKETS_NAMESPACE, '/departamentos', [
            'methods' => 'GET',
            'callback' => [self::class, 'list_departamentos_public'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/departamentos/(?P<id>\\d+)/assuntos', [
            'methods' => 'GET',
            'callback' => [self::class, 'list_assuntos_public'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/assuntos', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_assuntos'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/assuntos', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_create_assunto'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/assuntos/(?P<id>\\d+)', [
            'methods' => ['PUT', 'DELETE'],
            'callback' => [self::class, 'admin_update_assunto'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);

        // Admin / Atendente
        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/departamentos', [
            'methods' => 'GET',
            'callback' => [self::class, 'list_departamentos'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/departamentos', [
            'methods' => 'POST',
            'callback' => [self::class, 'create_departamento'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/settings', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_get_settings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/settings', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_update_settings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/departamentos/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_departamento'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/departamentos/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [self::class, 'update_departamento'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/departamentos/(?P<id>\\d+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'delete_departamento'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/tickets', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_tickets'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/tickets/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_get_ticket'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/tickets/(?P<id>\\d+)', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'admin_update_ticket'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/tickets/(?P<id>\\d+)/reply', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_reply_ticket'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/tickets/(?P<id>\\d+)/transfer', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_transfer_ticket'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_stats'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/agents', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_agents'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/upload', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_upload'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/respostas-rapidas', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_quick_replies'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/respostas-rapidas', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_create_quick_reply'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/respostas-rapidas/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [self::class, 'admin_update_quick_reply'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/respostas-rapidas/(?P<id>\\d+)', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'admin_delete_quick_reply'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/filtros-salvos', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_saved_filters'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/filtros-salvos', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_create_saved_filter'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/filtros-salvos/(?P<id>\\d+)', [
            'methods' => ['PUT', 'DELETE'],
            'callback' => [self::class, 'admin_update_or_delete_filter'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/kanban/tickets', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_kanban'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/kanban/filters', [
            'methods' => ['GET', 'POST'],
            'callback' => [self::class, 'admin_kanban_filters'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/reports/v2', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_reports_v2'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/assuntos', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_assuntos'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);
        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/assuntos', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_create_assunto'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);
        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/assuntos/(?P<id>\\d+)', [
            'methods' => ['PUT','DELETE'],
            'callback' => [self::class, 'admin_update_assunto'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/status-custom', [
            'methods' => 'GET',
            'callback' => [self::class, 'admin_list_status_custom'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
            },
        ]);
        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/status-custom', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_create_status_custom'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);
        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/status-custom/(?P<id>\\d+)', [
            'methods' => ['PUT','DELETE'],
            'callback' => [self::class, 'admin_update_status_custom'],
            'permission_callback' => function () {
                return ans_tickets_can_manage();
            },
        ]);
    }

    public static function create_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $table_tickets = ans_tickets_table('tickets');
        $table_clientes = ans_tickets_table('clientes');
        $table_interacoes = ans_tickets_table('interacoes');
        $table_departamentos = ans_tickets_table('departamentos');
        $table_anexos = ans_tickets_table('anexos');

        $fields = ['nome_completo', 'email', 'telefone', 'whatsapp', 'documento', 'data_nascimento', 'cliente_uniodonto', 'assunto', 'descricao'];
        foreach ($fields as $f) {
            if (!$req->get_param($f) && $f !== 'cliente_uniodonto') {
                return new WP_REST_Response(['error' => "Campo obrigatório: {$f}"], 400);
            }
        }

        $assunto = sanitize_text_field($req->get_param('assunto'));
        $departamento_id = (int)$req->get_param('departamento_id');

        if ($departamento_id) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_departamentos} WHERE id=%d AND ativo=1", $departamento_id));
            if (!$exists) {
                return new WP_REST_Response(['error' => 'Departamento inválido ou inativo'], 400);
            }
        } else {
            $departamento_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_departamentos} WHERE slug=%s AND ativo=1", $assunto));
            if (!$departamento_id) {
                return new WP_REST_Response(['error' => 'Departamento inválido ou inativo'], 400);
            }
        }

        $wpdb->query('START TRANSACTION');
        try {
            // Cliente
            $doc = preg_replace('/\D/', '', $req->get_param('documento'));
            $email = sanitize_email($req->get_param('email'));
            $cliente_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_clientes} WHERE documento=%s OR email=%s LIMIT 1",
                $doc,
                $email
            ));
            
            $data_nascimento = $req->get_param('data_nascimento') ? sanitize_text_field($req->get_param('data_nascimento')) : null;
            $cliente_uniodonto = $req->get_param('cliente_uniodonto') === 'true' || $req->get_param('cliente_uniodonto') === true || $req->get_param('cliente_uniodonto') === '1' ? 1 : 0;
            
            if (!$cliente_id) {
                $inserted = $wpdb->insert($table_clientes, [
                    'nome_completo' => sanitize_text_field($req->get_param('nome_completo')),
                    'email' => $email,
                    'telefone' => sanitize_text_field($req->get_param('telefone')),
                    'whatsapp' => sanitize_text_field($req->get_param('whatsapp')),
                    'documento' => $doc,
                    'data_nascimento' => $data_nascimento,
                    'cliente_uniodonto' => $cliente_uniodonto,
                ]);
                if ($inserted === false) {
                    throw new Exception('Erro ao inserir cliente: ' . $wpdb->last_error);
                }
                $cliente_id = $wpdb->insert_id;
            } else {
                // Atualiza dados do cliente existente
                $update_data = [
                    'nome_completo' => sanitize_text_field($req->get_param('nome_completo')),
                    'email' => $email,
                    'telefone' => sanitize_text_field($req->get_param('telefone')),
                ];
                if ($req->get_param('whatsapp')) {
                    $update_data['whatsapp'] = sanitize_text_field($req->get_param('whatsapp'));
                }
                if ($data_nascimento) {
                    $update_data['data_nascimento'] = $data_nascimento;
                }
                $update_data['cliente_uniodonto'] = $cliente_uniodonto;
                $wpdb->update($table_clientes, $update_data, ['id' => $cliente_id]);
            }

            // Protocolo
            $protocolo = ans_tickets_protocol();

        $inserted = $wpdb->insert($table_tickets, [
            'protocolo' => $protocolo,
            'cliente_id' => $cliente_id,
            'assunto' => $assunto,
            'descricao' => wp_kses_post($req->get_param('descricao')),
            'departamento_id' => $departamento_id,
            'status' => ans_tickets_initial_status($departamento_id),
            'prioridade' => 'media',
            'ticket_origem' => $req->get_param('ticket_origem') ? sanitize_text_field($req->get_param('ticket_origem')) : null,
            'tipo_de_procedimento' => sanitize_text_field($req->get_param('tipo_de_procedimento')),
            'prestador' => sanitize_text_field($req->get_param('prestador')),
            'data_evento' => $req->get_param('data_evento'),
            'numero_guia' => sanitize_text_field($req->get_param('numero_guia')),
        ]);
            
            if ($inserted === false) {
                throw new Exception('Erro ao inserir ticket: ' . $wpdb->last_error);
            }
            
            $ticket_id = $wpdb->insert_id;

            // Interação inicial
            $inserted = $wpdb->insert($table_interacoes, [
                'ticket_id' => $ticket_id,
                'autor_tipo' => 'cliente',
                'mensagem' => wp_kses_post($req->get_param('descricao')),
                'interno' => 0,
            ]);
            
            if ($inserted === false) {
                throw new Exception('Erro ao inserir interação: ' . $wpdb->last_error);
            }

            $wpdb->query('COMMIT');
            return new WP_REST_Response([
                'protocolo' => $protocolo,
                'ticket_id' => $ticket_id,
                'message' => 'Ticket criado com sucesso'
            ], 200);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('ANS Tickets - Erro ao criar ticket: ' . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'Erro ao criar ticket',
                'detail' => $e->getMessage()
            ], 500);
        }
    }

    public static function login_cliente(WP_REST_Request $req)
    {
        global $wpdb;
        $table_tickets = ans_tickets_table('tickets');
        $table_clientes = ans_tickets_table('clientes');
        $protocolo = sanitize_text_field($req->get_param('protocolo'));
        $doc = preg_replace('/\D/', '', $req->get_param('documento'));
        if (!$protocolo || !$doc) {
            return new WP_REST_Response(['error' => 'Protocolo e documento são obrigatórios'], 400);
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.id, c.documento FROM {$table_tickets} t JOIN {$table_clientes} c ON t.cliente_id = c.id WHERE t.protocolo=%s AND c.documento=%s",
            $protocolo,
            $doc
        ));
        if (!$row) {
            return new WP_REST_Response(['error' => 'Ticket não encontrado'], 404);
        }
        // Token simples baseado em hash + expiração curta (1h)
        $payload = [
            'protocolo' => $protocolo,
            'documento' => $doc,
            'exp' => time() + 3600,
        ];
        $token = base64_encode(json_encode($payload)) . '.' . wp_hash($protocolo . '|' . $doc . '|' . $payload['exp']);
        return ['token' => $token];
    }

    private static function validate_token(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$b64, $hash] = $parts;
        $payload = json_decode(base64_decode($b64), true);
        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        $expected = wp_hash($payload['protocolo'] . '|' . $payload['documento'] . '|' . $payload['exp']);
        if (!hash_equals($expected, $hash)) {
            return null;
        }
        return $payload;
    }

    public static function get_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $table_tickets = ans_tickets_table('tickets');
        $table_clientes = ans_tickets_table('clientes');
        $table_interacoes = ans_tickets_table('interacoes');
        $table_departamentos = ans_tickets_table('departamentos');

        $token = $req->get_header('authorization');
        if (!$token) {
            return new WP_REST_Response(['error' => 'Auth requerida'], 401);
        }
        $token = str_replace('Bearer ', '', $token);
        $payload = self::validate_token($token);
        if (!$payload) {
            return new WP_REST_Response(['error' => 'Token inválido'], 401);
        }

        $protocol = sanitize_text_field($req['protocol']);
        if ($protocol !== $payload['protocolo']) {
            return new WP_REST_Response(['error' => 'Não autorizado'], 401);
        }

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, c.nome_completo, c.documento, d.nome AS departamento_nome
            FROM {$table_tickets} t
            JOIN {$table_clientes} c ON t.cliente_id = c.id
            LEFT JOIN {$table_departamentos} d ON t.departamento_id = d.id
            WHERE t.protocolo=%s",
            $protocol
        ), ARRAY_A);

        if (!$ticket) {
            return new WP_REST_Response(['error' => 'Ticket não encontrado'], 404);
        }
        if ($ticket['documento'] !== $payload['documento']) {
            return new WP_REST_Response(['error' => 'Não autorizado'], 401);
        }

        $interacoes = $wpdb->get_results($wpdb->prepare(
            "SELECT id, autor_tipo, autor_id, mensagem, interno, created_at FROM {$table_interacoes} WHERE ticket_id=%d ORDER BY created_at ASC",
            $ticket['id']
        ));
        $anexos = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.guid AS url FROM {$table_anexos} a LEFT JOIN {$wpdb->posts} p ON a.attachment_id=p.ID WHERE a.ticket_id=%d ORDER BY a.created_at ASC",
            $ticket['id']
        ));
        // Oculta interações internas para o cliente
        $ticket['interacoes'] = array_values(array_filter($interacoes, function($i){
            return empty($i->interno);
        }));
        $ticket['anexos'] = $anexos;
        $ticket['status_label'] = ans_tickets_status_label_for($ticket['status'], (int)$ticket['departamento_id']);
        return $ticket;
    }

    public static function add_message(WP_REST_Request $req)
    {
        global $wpdb;
        $token = $req->get_header('authorization');
        if (!$token) {
            return new WP_REST_Response(['error' => 'Auth requerida'], 401);
        }
        $token = str_replace('Bearer ', '', $token);
        $payload = self::validate_token($token);
        if (!$payload) {
            return new WP_REST_Response(['error' => 'Token inválido'], 401);
        }
        $protocol = sanitize_text_field($req['protocol']);
        if ($protocol !== $payload['protocolo']) {
            return new WP_REST_Response(['error' => 'Não autorizado'], 401);
        }
        $msg = wp_kses_post($req->get_param('mensagem'));
        if (!$msg) {
            return new WP_REST_Response(['error' => 'Mensagem obrigatória'], 400);
        }
        $table_tickets = ans_tickets_table('tickets');
        $table_interacoes = ans_tickets_table('interacoes');
        $ticket_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_tickets} WHERE protocolo=%s", $protocol));
        if (!$ticket_id) {
            return new WP_REST_Response(['error' => 'Ticket não encontrado'], 404);
        }
        $inserted = $wpdb->insert($table_interacoes, [
            'ticket_id' => $ticket_id,
            'autor_tipo' => 'cliente',
            'mensagem' => $msg,
            'interno' => 0,
        ]);
        if ($inserted === false) {
            return new WP_REST_Response(['error' => 'Erro ao inserir mensagem: ' . $wpdb->last_error], 500);
        }
        $wpdb->update($table_tickets, ['updated_at' => current_time('mysql'), 'status' => 'em_analise'], ['id' => $ticket_id]);
        return new WP_REST_Response(['status' => 'ok', 'message' => 'Mensagem adicionada com sucesso'], 200);
    }

    public static function admin_list_tickets(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $c = ans_tickets_table('clientes');
        $d = ans_tickets_table('departamentos');
        $u = $wpdb->users;
        $where = [];
        $params = [];
        if ($req->get_param('status')) {
            $where[] = 't.status=%s';
            $params[] = sanitize_text_field($req->get_param('status'));
        }
        if ($req->get_param('departamento_id')) {
            $where[] = 't.departamento_id=%d';
            $params[] = (int)$req->get_param('departamento_id');
        }
        if ($req->get_param('protocolo')) {
            $where[] = 't.protocolo=%s';
            $params[] = sanitize_text_field($req->get_param('protocolo'));
        }
        if ($req->get_param('documento')) {
            $where[] = 'c.documento=%s';
            $params[] = preg_replace('/\\D/', '', $req->get_param('documento'));
        }
        if ($req->get_param('responsavel_id')) {
            $where[] = 't.responsavel_id=%d';
            $params[] = (int)$req->get_param('responsavel_id');
        }
        if ($req->get_param('prioridade')) {
            $pri = sanitize_text_field($req->get_param('prioridade'));
            if (in_array($pri, ['baixa', 'media', 'alta'], true)) {
                $where[] = 't.prioridade=%s';
                $params[] = $pri;
            }
        }
        $sql = "SELECT t.id, t.protocolo, t.assunto, t.status, t.prioridade, t.departamento_id, t.responsavel_id, t.created_at, t.updated_at, c.nome_completo, d.nome AS departamento_nome, u.display_name AS responsavel_nome FROM {$t} t LEFT JOIN {$c} c ON t.cliente_id=c.id LEFT JOIN {$d} d ON t.departamento_id=d.id LEFT JOIN {$u} u ON t.responsavel_id=u.ID";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT 100';
        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared);
        foreach ($rows as &$row) {
            $row->status_label = ans_tickets_status_label_for($row->status, (int)$row->departamento_id);
        }
        return $rows;
    }

    public static function admin_get_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $c = ans_tickets_table('clientes');
        $d = ans_tickets_table('departamentos');
        $i = ans_tickets_table('interacoes');
        $a = ans_tickets_table('anexos');
        $id = (int)$req['id'];

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, c.nome_completo, c.documento, d.nome AS departamento_nome, d.sla_hours AS departamento_sla_hours, u.display_name AS responsavel_nome FROM {$t} t JOIN {$c} c ON t.cliente_id=c.id LEFT JOIN {$d} d ON t.departamento_id=d.id LEFT JOIN {$wpdb->users} u ON t.responsavel_id=u.ID WHERE t.id=%d",
            $id
        ), ARRAY_A);
        if (!$ticket) {
            return new WP_REST_Response(['error' => 'Ticket não encontrado'], 404);
        }
        $ticket['status_label'] = ans_tickets_status_label_for($ticket['status'], (int)$ticket['departamento_id']);
        $ticket['interacoes'] = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name AS usuario_nome FROM {$i} i LEFT JOIN {$wpdb->users} u ON i.autor_id=u.ID WHERE i.ticket_id=%d ORDER BY i.created_at ASC",
            $id
        ));
        $ticket['anexos'] = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.guid AS url FROM {$a} a LEFT JOIN {$wpdb->posts} p ON a.attachment_id=p.ID WHERE a.ticket_id=%d ORDER BY a.created_at ASC",
            $id
        ));
        return $ticket;
    }

    public static function admin_update_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $allowed_status = ans_tickets_statuses();
        $data = [];
        if ($req->get_param('status')) {
            $status = sanitize_text_field($req->get_param('status'));
            if (!in_array($status, $allowed_status, true)) {
                return new WP_REST_Response(['error' => 'Status inválido'], 400);
            }
            $data['status'] = $status;
        }
        if ($req->get_param('departamento_id')) {
            $data['departamento_id'] = (int)$req->get_param('departamento_id');
        }
        if ($req->get_param('prioridade')) {
            $data['prioridade'] = sanitize_text_field($req->get_param('prioridade'));
        }
        if ($req->get_param('responsavel_id')) {
            $data['responsavel_id'] = (int)$req->get_param('responsavel_id');
        }
        if (empty($data)) {
            return new WP_REST_Response(['error' => 'Nada para atualizar'], 400);
        }
        $data['updated_at'] = current_time('mysql');
        $updated = $wpdb->update($t, $data, ['id' => (int)$req['id']]);
        if ($updated === false) {
            return new WP_REST_Response(['error' => 'Falha ao atualizar'], 500);
        }
        return ['status' => 'ok'];
    }

    public static function admin_reply_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $i = ans_tickets_table('interacoes');
        $msg = wp_kses_post($req->get_param('mensagem'));
        if (!$msg) {
            return new WP_REST_Response(['error' => 'Mensagem obrigatória'], 400);
        }
        $interno = $req->get_param('interno') ? 1 : 0;
        $wpdb->insert($i, [
            'ticket_id' => (int)$req['id'],
            'autor_tipo' => 'usuario',
            'autor_id' => get_current_user_id(),
            'mensagem' => $msg,
            'interno' => $interno,
        ]);
        $t = ans_tickets_table('tickets');
        $wpdb->update($t, ['updated_at' => current_time('mysql')], ['id' => (int)$req['id']]);
        return ['status' => 'ok', 'interacao_id' => $wpdb->insert_id];
    }

    public static function admin_transfer_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $id = (int)$req['id'];
        $new_dep = (int)$req->get_param('departamento_id');
        if (!$new_dep) {
            return new WP_REST_Response(['error' => 'departamento_id é obrigatório'], 400);
        }
        $t = ans_tickets_table('tickets');
        $d = ans_tickets_table('departamentos');
        $i = ans_tickets_table('interacoes');

        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
        if (!$ticket) {
            return new WP_REST_Response(['error' => 'Ticket não encontrado'], 404);
        }
        $old_dep_name = $wpdb->get_var($wpdb->prepare("SELECT nome FROM {$d} WHERE id=%d", $ticket['departamento_id']));
        $new_dep_name = $wpdb->get_var($wpdb->prepare("SELECT nome FROM {$d} WHERE id=%d", $new_dep));
        if (!$new_dep_name) {
            return new WP_REST_Response(['error' => 'Departamento inválido'], 400);
        }

        $wpdb->update($t, [
            'departamento_id' => $new_dep,
            'updated_at' => current_time('mysql')
        ], ['id' => $id]);

        $msg = sprintf('Ticket transferido de %s para %s', $old_dep_name ?: 'N/A', $new_dep_name);
        $wpdb->insert($i, [
            'ticket_id' => $id,
            'autor_tipo' => 'usuario',
            'autor_id' => get_current_user_id(),
            'mensagem' => $msg,
            'interno' => 1,
        ]);

        return new WP_REST_Response(['message' => 'Transferido', 'departamento' => $new_dep_name], 200);
    }

    public static function admin_stats(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $d = ans_tickets_table('departamentos');
        $i = ans_tickets_table('interacoes');
        $stats = [];

        $stats['status_counts'] = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$t} GROUP BY status", ARRAY_A);
        $stats['department_counts'] = $wpdb->get_results("SELECT d.nome, COUNT(*) AS total FROM {$t} t LEFT JOIN {$d} d ON t.departamento_id=d.id GROUP BY d.nome", ARRAY_A);
        $stats['subject_counts'] = $wpdb->get_results("SELECT assunto, COUNT(*) AS total FROM {$t} GROUP BY assunto", ARRAY_A);
        $stats['avg_resolution_hours'] = (float)$wpdb->get_var("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) FROM {$t} WHERE status IN ('fechado','resolvido')");
        $stats['avg_first_response_hours'] = (float)$wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, iu.first_response))
             FROM {$t} t
             JOIN (
                SELECT ticket_id, MIN(created_at) AS first_response
                FROM {$i}
                WHERE autor_tipo='usuario'
                GROUP BY ticket_id
             ) iu ON iu.ticket_id = t.id"
        );
        $stats['dept_resolution'] = $wpdb->get_results(
            "SELECT d.nome, AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at)) AS avg_hours
             FROM {$t} t
             LEFT JOIN {$d} d ON t.departamento_id=d.id
             WHERE t.status IN ('fechado','resolvido')
             GROUP BY d.nome",
            ARRAY_A
        );
        $stats['top_agents'] = $wpdb->get_results(
            "SELECT u.display_name, COUNT(*) as total
             FROM {$i} inter
             LEFT JOIN {$wpdb->users} u ON inter.autor_id=u.ID
             WHERE inter.autor_tipo='usuario'
             GROUP BY inter.autor_id
             ORDER BY total DESC
             LIMIT 5",
            ARRAY_A
        );

        return new WP_REST_Response($stats, 200);
    }

    public static function admin_agents(WP_REST_Request $req)
    {
        $users = get_users([
            'number' => 200,
            'capability' => 'ans_answer_tickets',
        ]);
        $data = [];
        foreach ($users as $u) {
            $data[] = [
                'id' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
            ];
        }
        return $data;
    }
    public static function admin_upload(WP_REST_Request $req)
    {
        $files = $req->get_file_params();
        if (empty($files['file'])) {
            return new WP_REST_Response(['error' => 'Arquivo obrigatório'], 400);
        }
        $ticket_id = (int)$req->get_param('ticket_id');
        if (!$ticket_id) {
            return new WP_REST_Response(['error' => 'ticket_id obrigatório'], 400);
        }
        $file = $files['file'];
        if ($file['size'] > ans_tickets_max_file_size()) {
            return new WP_REST_Response(['error' => 'Arquivo excede o limite de 5MB'], 400);
        }
        $allowed = ans_tickets_allowed_mimes();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            return new WP_REST_Response(['error' => 'Tipo de arquivo não permitido'], 400);
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        add_filter('upload_mimes', function ($mimes) use ($allowed) {
            return $allowed + $mimes;
        });
        $upload = media_handle_upload('file', 0);
        if (is_wp_error($upload)) {
            return new WP_REST_Response(['error' => $upload->get_error_message()], 400);
        }
        global $wpdb;
        $a = ans_tickets_table('anexos');
        $i = ans_tickets_table('interacoes');
        $interacao_id = $req->get_param('interacao_id') ? (int)$req->get_param('interacao_id') : null;
        $wpdb->insert($a, [
            'ticket_id' => $ticket_id,
            'interacao_id' => $interacao_id,
            'attachment_id' => $upload,
            'mime_type' => get_post_mime_type($upload),
            'tamanho_bytes' => filesize(get_attached_file($upload)),
        ]);
        return ['attachment_id' => $upload, 'url' => wp_get_attachment_url($upload)];
    }

    public static function recover_tickets(WP_REST_Request $req)
    {
        global $wpdb;
        $table_tickets = ans_tickets_table('tickets');
        $table_clientes = ans_tickets_table('clientes');
        
        $doc = preg_replace('/\D/', '', $req->get_param('documento'));
        $data_nascimento = sanitize_text_field($req->get_param('data_nascimento'));
        
        if (!$doc || !$data_nascimento) {
            return new WP_REST_Response(['error' => 'CPF e Data de Nascimento são obrigatórios'], 400);
        }
        
        $cliente = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_clientes} WHERE documento=%s AND data_nascimento=%s",
            $doc,
            $data_nascimento
        ), ARRAY_A);
        
        if (!$cliente) {
            return new WP_REST_Response(['error' => 'Nenhum chamado encontrado com esses dados'], 404);
        }
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, d.nome AS departamento_nome FROM {$table_tickets} t 
            LEFT JOIN " . ans_tickets_table('departamentos') . " d ON t.departamento_id = d.id 
            WHERE t.cliente_id=%d ORDER BY t.created_at DESC",
            $cliente['id']
        ), ARRAY_A);
        
        return new WP_REST_Response([
            'cliente' => $cliente,
            'tickets' => $tickets
        ], 200);
    }

    public static function admin_get_settings(WP_REST_Request $req)
    {
        global $wpdb;
        $settings = get_option(ANS_TICKETS_OPTION, []);
        $operadora_table = ans_tickets_table('operadora');
        $stored_ans = $wpdb->get_var("SELECT ans_registro FROM {$operadora_table} LIMIT 1");
        $ans_registro = $settings['ans_registro'] ?? $stored_ans;
        $today = current_time('Ymd');
        $seq = get_option('ans_tickets_seq_' . $today);
        return new WP_REST_Response([
            'ans_registro' => $ans_registro,
            'seq_today' => $seq ?: 0,
        ], 200);
    }

    public static function admin_update_settings(WP_REST_Request $req)
    {
        global $wpdb;
        $settings = get_option(ANS_TICKETS_OPTION, []);
        $ans_registro = preg_replace('/\D/', '', (string)$req->get_param('ans_registro'));
        if ($ans_registro) {
            $settings['ans_registro'] = $ans_registro;
            update_option(ANS_TICKETS_OPTION, $settings);
            $operadora_table = ans_tickets_table('operadora');
            $wpdb->query($wpdb->prepare("UPDATE {$operadora_table} SET ans_registro=%s LIMIT 1", $ans_registro));
        }

        if ($req->get_param('reset_seq')) {
            // Remove todas as opções de sequencial
            $like = 'ans_tickets_seq_%';
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
        }

        if ($req->get_param('seq_start')) {
            $seq_start = max(1, (int)$req->get_param('seq_start'));
            $today = current_time('Ymd');
            update_option('ans_tickets_seq_' . $today, $seq_start, false);
        }

        return new WP_REST_Response(['message' => 'Configurações atualizadas'], 200);
    }

    public static function list_departamentos_public(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $rows = $wpdb->get_results("SELECT id, nome, slug, ordem_fluxo FROM {$table} WHERE ativo=1 ORDER BY ordem_fluxo ASC");
        return $rows;
    }

    public static function list_assuntos_public(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('assuntos');
        $dep = (int)$req['id'];
        return $wpdb->get_results($wpdb->prepare("SELECT id, nome, slug FROM {$table} WHERE departamento_id=%d AND ativo=1 ORDER BY nome ASC", $dep));
    }

    public static function list_departamentos(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_users = ans_tickets_table('departamento_users');
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY ordem_fluxo ASC");
        
        foreach ($rows as &$row) {
            $users = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$table_users} WHERE departamento_id=%d",
                $row->id
            ));
            $row->users = $users;
        }
        
        return $rows;
    }

    public static function get_departamento(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_users = ans_tickets_table('departamento_users');
        $id = (int)$req['id'];
        
        $dept = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if (!$dept) {
            return new WP_REST_Response(['error' => 'Departamento não encontrado'], 404);
        }
        
        $users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$table_users} WHERE departamento_id=%d",
            $id
        ));
        $dept['users'] = $users;
        
        return $dept;
    }

    public static function create_departamento(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_users = ans_tickets_table('departamento_users');
        
        $data = [
            'nome' => sanitize_text_field($req->get_param('nome')),
            'slug' => sanitize_text_field($req->get_param('slug')),
            'ordem_fluxo' => (int)$req->get_param('ordem_fluxo') ?: 1,
            'cor' => sanitize_text_field($req->get_param('cor')),
            'sla_hours' => $req->get_param('sla_hours') ? (int)$req->get_param('sla_hours') : null,
            'ativo' => $req->get_param('ativo') ? 1 : 0,
        ];
        
        $inserted = $wpdb->insert($table, $data);
        if ($inserted === false) {
            return new WP_REST_Response(['error' => 'Erro ao criar departamento: ' . $wpdb->last_error], 500);
        }
        
        $dept_id = $wpdb->insert_id;
        $users = $req->get_param('users') ?: [];
        
        foreach ($users as $user_id) {
            $wpdb->insert($table_users, [
                'departamento_id' => $dept_id,
                'user_id' => (int)$user_id,
            ]);
        }
        
        return new WP_REST_Response(['id' => $dept_id, 'message' => 'Departamento criado com sucesso'], 200);
    }

    public static function update_departamento(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_users = ans_tickets_table('departamento_users');
        $id = (int)$req['id'];
        
        $data = [];
        if ($req->get_param('nome')) {
            $data['nome'] = sanitize_text_field($req->get_param('nome'));
        }
        if ($req->get_param('slug')) {
            $data['slug'] = sanitize_text_field($req->get_param('slug'));
        }
        if ($req->get_param('ordem_fluxo')) {
            $data['ordem_fluxo'] = (int)$req->get_param('ordem_fluxo');
        }
        if ($req->get_param('cor')) {
            $data['cor'] = sanitize_text_field($req->get_param('cor'));
        }
        if ($req->get_param('sla_hours') !== null) {
            $data['sla_hours'] = $req->get_param('sla_hours') ? (int)$req->get_param('sla_hours') : null;
        }
        if ($req->get_param('ativo') !== null) {
            $data['ativo'] = $req->get_param('ativo') ? 1 : 0;
        }
        
        if (!empty($data)) {
            $data['updated_at'] = current_time('mysql');
            $updated = $wpdb->update($table, $data, ['id' => $id]);
            if ($updated === false) {
                return new WP_REST_Response(['error' => 'Erro ao atualizar departamento: ' . $wpdb->last_error], 500);
            }
        }
        
        // Atualizar usuários
        if ($req->get_param('users') !== null) {
            $wpdb->delete($table_users, ['departamento_id' => $id]);
            $users = $req->get_param('users') ?: [];
            foreach ($users as $user_id) {
                $wpdb->insert($table_users, [
                    'departamento_id' => $id,
                    'user_id' => (int)$user_id,
                ]);
            }
        }
        
        return new WP_REST_Response(['message' => 'Departamento atualizado com sucesso'], 200);
    }

    public static function delete_departamento(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_tickets = ans_tickets_table('tickets');
        $table_users = ans_tickets_table('departamento_users');
        $id = (int)$req['id'];
        
        // Verificar se há tickets abertos
        $tickets_abertos = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_tickets} WHERE departamento_id=%d AND status NOT IN ('concluido', 'arquivado')",
            $id
        ));
        
        if ($tickets_abertos > 0) {
            $transfer_to = (int)$req->get_param('transfer_to');
            if (!$transfer_to) {
                return new WP_REST_Response(['error' => 'É necessário selecionar um departamento para transferir os chamados abertos'], 400);
            }
            
            // Transferir tickets
            $wpdb->update($table_tickets, ['departamento_id' => $transfer_to], ['departamento_id' => $id]);
        }
        
        // Remover usuários do departamento
        $wpdb->delete($table_users, ['departamento_id' => $id]);
        
        // Excluir departamento
        $deleted = $wpdb->delete($table, ['id' => $id]);
        if ($deleted === false) {
            return new WP_REST_Response(['error' => 'Erro ao excluir departamento: ' . $wpdb->last_error], 500);
        }
        
        return new WP_REST_Response(['message' => 'Departamento excluído com sucesso'], 200);
    }

    public static function admin_list_quick_replies(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('respostas_rapidas');
        $links = ans_tickets_table('respostas_rapidas_links');
        $dept = (int)$req->get_param('departamento_id');
        $assunto_id = (int)$req->get_param('assunto_id');
        $status = sanitize_text_field($req->get_param('status'));
        $user_id = get_current_user_id();

        $baseWhere = "WHERE ativo=1";
        $globais = $wpdb->get_results("SELECT * FROM {$table} {$baseWhere} AND escopo='global' ORDER BY updated_at DESC", ARRAY_A);
        $pessoais = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} {$baseWhere} AND escopo='pessoal' AND user_id=%d ORDER BY updated_at DESC", $user_id), ARRAY_A);

        $matchIds = [];
        $conditions = [];
        $params = [];
        if ($dept) {
            $conditions[] = "departamento_id=%d";
            $params[] = $dept;
        }
        if ($assunto_id) {
            $conditions[] = "assunto_id=%d";
            $params[] = $assunto_id;
        }
        if ($status) {
            $conditions[] = "status_slug=%s";
            $params[] = $status;
        }
        if ($conditions) {
            $sql = "SELECT DISTINCT resposta_id FROM {$links} WHERE " . implode(' OR ', $conditions);
            $ids = $wpdb->get_col($wpdb->prepare($sql, $params));
            if ($ids) {
                $in = implode(',', array_map('intval', $ids));
                $matchIds = $wpdb->get_results("SELECT * FROM {$table} WHERE id IN ($in) AND ativo=1 ORDER BY updated_at DESC", ARRAY_A);
            }
        }

        $deptRows = [];
        if ($dept && !$conditions) {
            $deptRows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} {$baseWhere} AND escopo='departamento' AND departamento_id=%d ORDER BY updated_at DESC", $dept), ARRAY_A);
        }

        return [
            'globais' => $globais,
            'departamento' => $deptRows,
            'pessoais' => $pessoais,
            'vinculadas' => $matchIds,
        ];
    }

    public static function admin_create_quick_reply(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('respostas_rapidas');
        $linksTable = ans_tickets_table('respostas_rapidas_links');

        $escopo = sanitize_text_field($req->get_param('escopo'));
        $titulo = sanitize_text_field($req->get_param('titulo'));
        $conteudo = wp_kses_post($req->get_param('conteudo'));
        $departamento_ids = (array)$req->get_param('departamentos');
        $assunto_ids = (array)$req->get_param('assuntos');
        $statuses = (array)$req->get_param('statuses');
        $departamento_id = (int)$req->get_param('departamento_id'); // fallback legado

        if (!$titulo || !$conteudo || !$escopo) {
            return new WP_REST_Response(['error' => 'Campos obrigatórios: titulo, conteudo, escopo'], 400);
        }

        if ($escopo === 'global' && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Apenas administradores podem criar globais'], 403);
        }
        if ($escopo === 'departamento' && !ans_tickets_can_manage()) {
            return new WP_REST_Response(['error' => 'Sem permissão para criar no departamento'], 403);
        }
        if ($escopo === 'departamento' && !$departamento_id) {
            return new WP_REST_Response(['error' => 'departamento_id é obrigatório para escopo departamento'], 400);
        }

        $data = [
            'titulo' => $titulo,
            'conteudo' => $conteudo,
            'escopo' => $escopo,
            'departamento_id' => $departamento_id ?: null,
            'user_id' => $escopo === 'pessoal' ? get_current_user_id() : null,
            'ativo' => 1,
        ];

        $inserted = $wpdb->insert($table, $data);
        if ($inserted === false) {
            return new WP_REST_Response(['error' => 'Erro ao salvar'], 500);
        }
        $replyId = $wpdb->insert_id;
        $linkRows = [];
        foreach ($departamento_ids as $dep) {
            $linkRows[] = ['departamento_id' => (int)$dep];
        }
        foreach ($assunto_ids as $ass) {
            $linkRows[] = ['assunto_id' => (int)$ass];
        }
        foreach ($statuses as $st) {
            $linkRows[] = ['status_slug' => sanitize_text_field($st)];
        }
        foreach ($linkRows as $row) {
            $row['resposta_id'] = $replyId;
            $wpdb->insert($linksTable, $row);
        }
        return ['id' => $replyId];
    }

    public static function admin_update_quick_reply(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('respostas_rapidas');
        $linksTable = ans_tickets_table('respostas_rapidas_links');
        $id = (int)$req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if (!$row) {
            return new WP_REST_Response(['error' => 'Não encontrado'], 404);
        }
        if ($row['escopo'] === 'global' && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Sem permissão'], 403);
        }
        if ($row['escopo'] === 'departamento' && !ans_tickets_can_manage()) {
            return new WP_REST_Response(['error' => 'Sem permissão'], 403);
        }
        if ($row['escopo'] === 'pessoal' && (int)$row['user_id'] !== get_current_user_id()) {
            return new WP_REST_Response(['error' => 'Sem permissão'], 403);
        }
        $data = [];
        if ($req->get_param('titulo')) {
            $data['titulo'] = sanitize_text_field($req->get_param('titulo'));
        }
        if ($req->get_param('conteudo')) {
            $data['conteudo'] = wp_kses_post($req->get_param('conteudo'));
        }
        if ($req->get_param('ativo') !== null) {
            $data['ativo'] = $req->get_param('ativo') ? 1 : 0;
        }
        if (empty($data)) {
            return new WP_REST_Response(['error' => 'Nada para atualizar'], 400);
        }
        $updated = $wpdb->update($table, $data, ['id' => $id]);
        if ($updated === false) {
            return new WP_REST_Response(['error' => 'Erro ao atualizar'], 500);
        }
        if ($req->get_param('departamentos') !== null || $req->get_param('assuntos') !== null || $req->get_param('statuses') !== null) {
            $wpdb->delete($linksTable, ['resposta_id' => $id]);
            $depIds = (array)$req->get_param('departamentos');
            $assuntos = (array)$req->get_param('assuntos');
            $statuses = (array)$req->get_param('statuses');
            $linkRows = [];
            foreach ($depIds as $dep) {
                $linkRows[] = ['departamento_id' => (int)$dep];
            }
            foreach ($assuntos as $ass) {
                $linkRows[] = ['assunto_id' => (int)$ass];
            }
            foreach ($statuses as $st) {
                $linkRows[] = ['status_slug' => sanitize_text_field($st)];
            }
            foreach ($linkRows as $rowLink) {
                $rowLink['resposta_id'] = $id;
                $wpdb->insert($linksTable, $rowLink);
            }
        }
        return ['status' => 'ok'];
    }

    public static function admin_delete_quick_reply(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('respostas_rapidas');
        $linksTable = ans_tickets_table('respostas_rapidas_links');
        $id = (int)$req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if (!$row) {
            return new WP_REST_Response(['error' => 'Não encontrado'], 404);
        }
        if ($row['escopo'] === 'global' && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Sem permissão'], 403);
        }
        if ($row['escopo'] === 'departamento' && !ans_tickets_can_manage()) {
            return new WP_REST_Response(['error' => 'Sem permissão'], 403);
        }
        if ($row['escopo'] === 'pessoal' && (int)$row['user_id'] !== get_current_user_id()) {
            return new WP_REST_Response(['error' => 'Sem permissão'], 403);
        }
        $wpdb->delete($table, ['id' => $id]);
        $wpdb->delete($linksTable, ['resposta_id' => $id]);
        return ['status' => 'deleted'];
    }

    public static function admin_list_saved_filters(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('filtros_salvos');
        $user_id = get_current_user_id();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, nome, filtros, created_at FROM {$table} WHERE user_id=%d ORDER BY created_at DESC", $user_id), ARRAY_A);
        foreach ($rows as &$row) {
            $row['filtros'] = json_decode($row['filtros'], true);
        }
        return $rows;
    }

    public static function admin_create_saved_filter(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('filtros_salvos');
        $nome = sanitize_text_field($req->get_param('nome'));
        $filtros = $req->get_param('filtros');
        if (!$nome || !$filtros) {
            return new WP_REST_Response(['error' => 'Campos obrigatórios: nome, filtros'], 400);
        }
        $json = wp_json_encode($filtros);
        $wpdb->insert($table, [
            'user_id' => get_current_user_id(),
            'nome' => $nome,
            'filtros' => $json,
        ]);
        return ['id' => $wpdb->insert_id];
    }

    public static function admin_update_or_delete_filter(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('filtros_salvos');
        $id = (int)$req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND user_id=%d", $id, get_current_user_id()), ARRAY_A);
        if (!$row) {
            return new WP_REST_Response(['error' => 'Não encontrado'], 404);
        }
        if ($req->get_method() === 'DELETE') {
            $wpdb->delete($table, ['id' => $id]);
            return ['status' => 'deleted'];
        }
        $data = [];
        if ($req->get_param('nome')) {
            $data['nome'] = sanitize_text_field($req->get_param('nome'));
        }
        if ($req->get_param('filtros') !== null) {
            $data['filtros'] = wp_json_encode($req->get_param('filtros'));
        }
        if (empty($data)) {
            return new WP_REST_Response(['error' => 'Nada para atualizar'], 400);
        }
        $wpdb->update($table, $data, ['id' => $id]);
        return ['status' => 'ok'];
    }

    public static function admin_list_kanban(WP_REST_Request $req)
    {
        global $wpdb;
        $statusParam = sanitize_text_field($req->get_param('status'));
        $statusList = ans_tickets_statuses();
        $status = in_array($statusParam, $statusList, true) ? $statusParam : 'aberto';
        $limit = (int)$req->get_param('per_page') ?: 30;
        $offset = (int)$req->get_param('offset') ?: 0;

        $t = ans_tickets_table('tickets');
        $c = ans_tickets_table('clientes');
        $d = ans_tickets_table('departamentos');

        $where = ['t.status=%s'];
        $params = [$status];
        $filtersWhere = [];
        $filtersParams = [];

        if ($req->get_param('departamento_id')) {
            $where[] = 't.departamento_id=%d';
            $params[] = (int)$req->get_param('departamento_id');
            $filtersWhere[] = 't.departamento_id=%d';
            $filtersParams[] = (int)$req->get_param('departamento_id');
        }
        if ($req->get_param('responsavel_id')) {
            $where[] = 't.responsavel_id=%d';
            $params[] = (int)$req->get_param('responsavel_id');
            $filtersWhere[] = 't.responsavel_id=%d';
            $filtersParams[] = (int)$req->get_param('responsavel_id');
        }
        if ($req->get_param('prioridade')) {
            $where[] = 't.prioridade=%s';
            $params[] = sanitize_text_field($req->get_param('prioridade'));
            $filtersWhere[] = 't.prioridade=%s';
            $filtersParams[] = sanitize_text_field($req->get_param('prioridade'));
        }
        if ($req->get_param('documento')) {
            $where[] = 'c.documento=%s';
            $params[] = preg_replace('/\\D/', '', $req->get_param('documento'));
            $filtersWhere[] = 'c.documento=%s';
            $filtersParams[] = preg_replace('/\\D/', '', $req->get_param('documento'));
        }
        if ($req->get_param('protocolo')) {
            $where[] = 't.protocolo=%s';
            $params[] = sanitize_text_field($req->get_param('protocolo'));
            $filtersWhere[] = 't.protocolo=%s';
            $filtersParams[] = sanitize_text_field($req->get_param('protocolo'));
        }

        $sql = "SELECT t.id, t.protocolo, t.assunto, t.status, t.prioridade, t.departamento_id, t.created_at, t.updated_at, c.nome_completo, d.nome AS departamento_nome, d.sla_hours
                FROM {$t} t
                JOIN {$c} c ON t.cliente_id=c.id
                LEFT JOIN {$d} d ON t.departamento_id=d.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.updated_at DESC
                LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        $prepared = $wpdb->prepare($sql, $params);
        $items = $wpdb->get_results($prepared);
        foreach ($items as &$item) {
            $item->status_label = ans_tickets_status_label_for($item->status, (int)$item->departamento_id);
        }

        $whereCounts = $filtersWhere ? ('WHERE ' . implode(' AND ', $filtersWhere)) : '';
        $countsSql = "SELECT status, COUNT(*) as total FROM {$t} t LEFT JOIN {$c} c ON t.cliente_id=c.id {$whereCounts} GROUP BY status";
        $counts = $filtersParams ? $wpdb->get_results($wpdb->prepare($countsSql, $filtersParams), ARRAY_A) : $wpdb->get_results($countsSql, ARRAY_A);

        return [
            'items' => $items,
            'status' => $status,
            'counts' => $counts,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public static function admin_kanban_filters(WP_REST_Request $req)
    {
        $key = 'ans_kanban_filters';
        $user_id = get_current_user_id();
        if ($req->get_method() === 'GET') {
            $saved = get_user_meta($user_id, $key, true);
            return $saved ?: [];
        }
        $filters = $req->get_param('filters');
        if (!is_array($filters)) {
            return new WP_REST_Response(['error' => 'Filtros inválidos'], 400);
        }
        update_user_meta($user_id, $key, $filters);
        return ['status' => 'ok'];
    }

    public static function admin_reports_v2(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $d = ans_tickets_table('departamentos');
        $i = ans_tickets_table('interacoes');
        $users = $wpdb->users;

        $firstResponseDept = $wpdb->get_results(
            "SELECT d.nome AS departamento, AVG(TIMESTAMPDIFF(HOUR, t.created_at, ir.first_response)) AS horas
             FROM {$t} t
             JOIN (
                SELECT ticket_id, MIN(created_at) AS first_response
                FROM {$i}
                WHERE autor_tipo='usuario'
                GROUP BY ticket_id
             ) ir ON ir.ticket_id = t.id
             LEFT JOIN {$d} d ON t.departamento_id=d.id
             GROUP BY d.nome",
            ARRAY_A
        );

        $firstResponseAgent = $wpdb->get_results(
            "SELECT u.display_name AS agente, AVG(TIMESTAMPDIFF(HOUR, t.created_at, ir.first_response)) AS horas
             FROM {$t} t
             JOIN (
                SELECT ticket_id, MIN(created_at) AS first_response, autor_id
                FROM {$i}
                WHERE autor_tipo='usuario'
                GROUP BY ticket_id, autor_id
             ) ir ON ir.ticket_id = t.id
             LEFT JOIN {$users} u ON ir.autor_id=u.ID
             GROUP BY ir.autor_id",
            ARRAY_A
        );

        $slaResumo = $wpdb->get_row(
            "SELECT 
                SUM(CASE WHEN d.sla_hours IS NOT NULL AND d.sla_hours > 0 AND TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at) <= d.sla_hours THEN 1 ELSE 0 END) AS cumprido,
                SUM(CASE WHEN d.sla_hours IS NOT NULL AND d.sla_hours > 0 AND TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at) > d.sla_hours THEN 1 ELSE 0 END) AS estourado
             FROM {$t} t
             LEFT JOIN {$d} d ON t.departamento_id=d.id
             WHERE t.status IN ('fechado','resolvido','concluido')",
            ARRAY_A
        );

        $porAssunto = $wpdb->get_results("SELECT assunto, COUNT(*) as total FROM {$t} GROUP BY assunto", ARRAY_A);

        $porHora = $wpdb->get_results("SELECT HOUR(created_at) AS hora, COUNT(*) as total FROM {$t} GROUP BY HOUR(created_at)", ARRAY_A);

        $heatmap = $wpdb->get_results(
            "SELECT DAYOFWEEK(created_at) AS dia, HOUR(created_at) AS hora, COUNT(*) AS total
             FROM {$t}
             GROUP BY dia, hora",
            ARRAY_A
        );

        return [
            'first_response_departamento' => $firstResponseDept,
            'first_response_agente' => $firstResponseAgent,
            'sla' => $slaResumo,
            'assunto' => $porAssunto,
            'por_hora' => $porHora,
            'heatmap' => $heatmap,
        ];
    }

    public static function admin_list_assuntos(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('assuntos');
        $dep = (int)$req->get_param('departamento_id');
        $where = $dep ? $wpdb->prepare("WHERE departamento_id=%d", $dep) : '';
        return $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY nome ASC");
    }

    public static function admin_create_assunto(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('assuntos');
        $dep = (int)$req->get_param('departamento_id');
        $nome = sanitize_text_field($req->get_param('nome'));
        $slug = sanitize_title($req->get_param('slug') ?: $nome);
        if (!$dep || !$nome) {
            return new WP_REST_Response(['error' => 'departamento_id e nome são obrigatórios'], 400);
        }
        $insert = $wpdb->insert($table, [
            'departamento_id' => $dep,
            'nome' => $nome,
            'slug' => $slug,
            'ativo' => 1,
        ]);
        if ($insert === false) {
            return new WP_REST_Response(['error' => 'Erro ao criar assunto: ' . $wpdb->last_error], 500);
        }
        return ['id' => $wpdb->insert_id];
    }

    public static function admin_update_assunto(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('assuntos');
        $id = (int)$req['id'];
        if ($req->get_method() === 'DELETE') {
            $wpdb->delete($table, ['id' => $id]);
            return ['status' => 'deleted'];
        }
        $data = [];
        if ($req->get_param('nome')) {
            $data['nome'] = sanitize_text_field($req->get_param('nome'));
        }
        if ($req->get_param('slug')) {
            $data['slug'] = sanitize_title($req->get_param('slug'));
        }
        if ($req->get_param('ativo') !== null) {
            $data['ativo'] = $req->get_param('ativo') ? 1 : 0;
        }
        if (empty($data)) {
            return new WP_REST_Response(['error' => 'Nada para atualizar'], 400);
        }
        $ok = $wpdb->update($table, $data, ['id' => $id]);
        if ($ok === false) {
            return new WP_REST_Response(['error' => 'Erro ao atualizar assunto'], 500);
        }
        return ['status' => 'ok'];
    }

    public static function admin_list_status_custom(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('status_custom');
        $dep = (int)$req->get_param('departamento_id');
        if ($dep) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE ativo=1 AND (departamento_id=%d OR departamento_id IS NULL) ORDER BY ordem ASC, nome ASC",
                $dep
            ));
        }
        return $wpdb->get_results("SELECT * FROM {$table} WHERE ativo=1 ORDER BY ordem ASC, nome ASC");
    }

    public static function admin_create_status_custom(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('status_custom');
        $slug = sanitize_title($req->get_param('slug'));
        $nome = sanitize_text_field($req->get_param('nome'));
        $dep = $req->get_param('departamento_id') ? (int)$req->get_param('departamento_id') : null;
        if (!$slug || !$nome) {
            return new WP_REST_Response(['error' => 'slug e nome são obrigatórios'], 400);
        }
        $insert = $wpdb->insert($table, [
            'departamento_id' => $dep,
            'slug' => $slug,
            'nome' => $nome,
            'cor' => sanitize_text_field($req->get_param('cor')),
            'ordem' => (int)$req->get_param('ordem'),
            'ativo' => 1,
            'inicial' => $req->get_param('inicial') ? 1 : 0,
            'final_resolvido' => $req->get_param('final_resolvido') ? 1 : 0,
            'final_nao_resolvido' => $req->get_param('final_nao_resolvido') ? 1 : 0,
        ]);
        if ($insert === false) {
            return new WP_REST_Response(['error' => 'Erro ao criar status: ' . $wpdb->last_error], 500);
        }
        $id = $wpdb->insert_id;
        self::reset_status_flags($id, $dep, $req);
        return ['id' => $wpdb->insert_id];
    }

    public static function admin_update_status_custom(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('status_custom');
        $id = (int)$req['id'];
        if ($req->get_method() === 'DELETE') {
            $wpdb->delete($table, ['id' => $id]);
            return ['status' => 'deleted'];
        }
        $data = [];
        if ($req->get_param('slug')) {
            $data['slug'] = sanitize_title($req->get_param('slug'));
        }
        if ($req->get_param('nome')) {
            $data['nome'] = sanitize_text_field($req->get_param('nome'));
        }
        if ($req->get_param('cor')) {
            $data['cor'] = sanitize_text_field($req->get_param('cor'));
        }
        if ($req->get_param('ordem') !== null) {
            $data['ordem'] = (int)$req->get_param('ordem');
        }
        if ($req->get_param('ativo') !== null) {
            $data['ativo'] = $req->get_param('ativo') ? 1 : 0;
        }
        if ($req->get_param('departamento_id') !== null) {
            $data['departamento_id'] = (int)$req->get_param('departamento_id');
        }
        if ($req->get_param('inicial') !== null) {
            $data['inicial'] = $req->get_param('inicial') ? 1 : 0;
        }
        if ($req->get_param('final_resolvido') !== null) {
            $data['final_resolvido'] = $req->get_param('final_resolvido') ? 1 : 0;
        }
        if ($req->get_param('final_nao_resolvido') !== null) {
            $data['final_nao_resolvido'] = $req->get_param('final_nao_resolvido') ? 1 : 0;
        }
        if (empty($data)) {
            return new WP_REST_Response(['error' => 'Nada para atualizar'], 400);
        }
        $ok = $wpdb->update($table, $data, ['id' => $id]);
        if ($ok === false) {
            return new WP_REST_Response(['error' => 'Erro ao atualizar status'], 500);
        }
        self::reset_status_flags($id, $req->get_param('departamento_id') ? (int)$req->get_param('departamento_id') : null, $req);
        return ['status' => 'ok'];
    }

    private static function reset_status_flags(int $id, ?int $departamento_id, WP_REST_Request $req): void
    {
        global $wpdb;
        $table = ans_tickets_table('status_custom');
        $depWhere = $departamento_id ? $wpdb->prepare("= %d", $departamento_id) : "IS NULL";
        if ($req->get_param('inicial')) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET inicial=0 WHERE departamento_id {$depWhere} AND id != %d", $id));
        }
        if ($req->get_param('final_resolvido')) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET final_resolvido=0 WHERE departamento_id {$depWhere} AND id != %d", $id));
        }
        if ($req->get_param('final_nao_resolvido')) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET final_nao_resolvido=0 WHERE departamento_id {$depWhere} AND id != %d", $id));
        }
    }
}
