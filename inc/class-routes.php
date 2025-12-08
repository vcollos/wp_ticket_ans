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

        register_rest_route(ANS_TICKETS_NAMESPACE, '/admin/upload', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_upload'],
            'permission_callback' => function () {
                return ans_tickets_can_answer();
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

        $fields = ['nome_completo', 'email', 'telefone', 'whatsapp', 'documento', 'data_nascimento', 'cliente_uniodonto', 'assunto', 'descricao'];
        foreach ($fields as $f) {
            if (!$req->get_param($f) && $f !== 'cliente_uniodonto') {
                return new WP_REST_Response(['error' => "Campo obrigatório: {$f}"], 400);
            }
        }

        $assunto = sanitize_text_field($req->get_param('assunto'));
        $permitidos = ['atendimento', 'financeiro', 'comercial', 'assistencial', 'ouvidoria'];
        if (!in_array($assunto, $permitidos, true)) {
            return new WP_REST_Response(['error' => 'Assunto inválido'], 400);
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

            // Departamento padrão
            $dep_slug = $assunto === 'ouvidoria' ? 'ouvidoria' : ($assunto === 'assistencial' ? 'assistencial' : 'atendimento');
            $departamento_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_departamentos} WHERE slug=%s", $dep_slug));

            // Protocolo
            $protocolo = ans_tickets_protocol();

            $inserted = $wpdb->insert($table_tickets, [
                'protocolo' => $protocolo,
                'cliente_id' => $cliente_id,
                'assunto' => $assunto,
                'descricao' => wp_kses_post($req->get_param('descricao')),
                'departamento_id' => $departamento_id,
                'status' => 'novo',
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
            "SELECT autor_tipo, autor_id, mensagem, interno, created_at FROM {$table_interacoes} WHERE ticket_id=%d ORDER BY created_at ASC",
            $ticket['id']
        ));
        $ticket['interacoes'] = $interacoes;
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
        $wpdb->update($table_tickets, ['updated_at' => current_time('mysql'), 'status' => 'assistencial'], ['id' => $ticket_id]);
        return new WP_REST_Response(['status' => 'ok', 'message' => 'Mensagem adicionada com sucesso'], 200);
    }

    public static function list_departamentos(WP_REST_Request $req)
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE ativo=1 ORDER BY ordem_fluxo ASC");
        return $rows;
    }

    public static function admin_list_tickets(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $c = ans_tickets_table('clientes');
        $d = ans_tickets_table('departamentos');
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
        $sql = "SELECT t.id, t.protocolo, t.assunto, t.status, t.prioridade, t.created_at, t.updated_at, c.nome_completo, d.nome AS departamento_nome FROM {$t} t JOIN {$c} c ON t.cliente_id=c.id LEFT JOIN {$d} d ON t.departamento_id=d.id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT 100';
        $prepared = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($prepared);
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
            "SELECT t.*, c.nome_completo, c.documento, d.nome AS departamento_nome FROM {$t} t JOIN {$c} c ON t.cliente_id=c.id LEFT JOIN {$d} d ON t.departamento_id=d.id WHERE t.id=%d",
            $id
        ), ARRAY_A);
        if (!$ticket) {
            return new WP_REST_Response(['error' => 'Ticket não encontrado'], 404);
        }
        $ticket['interacoes'] = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name AS usuario_nome FROM {$i} i LEFT JOIN {$wpdb->users} u ON i.autor_id=u.ID WHERE i.ticket_id=%d ORDER BY i.created_at ASC",
            $id
        ));
        $ticket['anexos'] = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.guid AS url FROM {$a} a LEFT JOIN {$wpdb->posts} p ON a.attachment_id=p.ID WHERE a.ticket_id=%d",
            $id
        ));
        return $ticket;
    }

    public static function admin_update_ticket(WP_REST_Request $req)
    {
        global $wpdb;
        $t = ans_tickets_table('tickets');
        $allowed_status = ['novo','atendimento','financeiro','comercial','assistencial','ouvidoria','concluido','arquivado','pendente_cliente'];
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
}
