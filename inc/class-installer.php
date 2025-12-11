<?php
if (!defined('ABSPATH')) {
    exit;
}

class ANS_Tickets_Installer
{
    public static function activate(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'ans_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = "
CREATE TABLE {$prefix}operadora (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    ans_registro CHAR(6) NOT NULL,
    email_suporte VARCHAR(150),
    timezone VARCHAR(64),
    sla_default_hours INT DEFAULT 72,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset;

CREATE TABLE {$prefix}departamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(60) NOT NULL,
    ordem_fluxo INT DEFAULT 1,
    cor VARCHAR(20),
    ativo BOOLEAN DEFAULT TRUE,
    sla_hours INT,
    pausa_pendente_cliente BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) $charset;

CREATE TABLE {$prefix}clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_completo VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(30) NOT NULL,
    whatsapp VARCHAR(30),
    documento VARCHAR(20) NOT NULL,
    data_nascimento DATE,
    cliente_uniodonto BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY documento_email (documento, email)
) $charset;

CREATE TABLE {$prefix}tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    protocolo VARCHAR(40) NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    assunto VARCHAR(80) NOT NULL,
    descricao TEXT NOT NULL,
    departamento_id BIGINT UNSIGNED,
    status VARCHAR(40) NOT NULL DEFAULT 'novo',
    prioridade VARCHAR(10) NOT NULL DEFAULT 'media',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ticket_origem BIGINT UNSIGNED,
    tipo_de_procedimento VARCHAR(120),
    prestador VARCHAR(150),
    data_evento DATE,
    numero_guia VARCHAR(80),
    PRIMARY KEY (id),
    UNIQUE KEY protocolo (protocolo),
    KEY cliente_id (cliente_id),
    KEY departamento_id (departamento_id),
    KEY status (status)
) $charset;

CREATE TABLE {$prefix}interacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    autor_tipo VARCHAR(20) NOT NULL,
    autor_id BIGINT UNSIGNED,
    mensagem TEXT NOT NULL,
    interno BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ticket_id (ticket_id)
) $charset;

CREATE TABLE {$prefix}anexos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    interacao_id BIGINT UNSIGNED,
    attachment_id BIGINT UNSIGNED,
    mime_type VARCHAR(80),
    tamanho_bytes BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ticket_id (ticket_id),
    KEY interacao_id (interacao_id)
) $charset;

CREATE TABLE {$prefix}departamento_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departamento_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY dept_user (departamento_id, user_id),
    KEY departamento_id (departamento_id),
    KEY user_id (user_id)
) $charset;

CREATE TABLE {$prefix}respostas_rapidas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(120) NOT NULL,
    conteudo TEXT NOT NULL,
    escopo VARCHAR(20) NOT NULL,
    departamento_id BIGINT UNSIGNED,
    user_id BIGINT UNSIGNED,
    status_slug VARCHAR(60),
    assunto_id BIGINT UNSIGNED,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY escopo (escopo),
    KEY departamento_id (departamento_id),
    KEY user_id (user_id),
    KEY status_slug (status_slug),
    KEY assunto_id (assunto_id)
) $charset;

CREATE TABLE {$prefix}filtros_salvos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    filtros LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id)
) $charset;

CREATE TABLE {$prefix}assuntos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departamento_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY dept_slug (departamento_id, slug)
) $charset;

CREATE TABLE {$prefix}status_custom (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departamento_id BIGINT UNSIGNED,
    slug VARCHAR(80) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    cor VARCHAR(20),
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    inicial BOOLEAN DEFAULT FALSE,
    final_resolvido BOOLEAN DEFAULT FALSE,
    final_nao_resolvido BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug_dept (slug, departamento_id)
) $charset;

CREATE TABLE {$prefix}respostas_rapidas_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    resposta_id BIGINT UNSIGNED NOT NULL,
    departamento_id BIGINT UNSIGNED,
    assunto_id BIGINT UNSIGNED,
    status_slug VARCHAR(80),
    PRIMARY KEY (id),
    KEY resposta_id (resposta_id),
    KEY departamento_id (departamento_id),
    KEY assunto_id (assunto_id),
    KEY status_slug (status_slug)
) $charset;
";

        dbDelta($tables);

        // Seeds básicos
        $operadora = $wpdb->get_var("SELECT id FROM {$prefix}operadora LIMIT 1");
        if (!$operadora) {
            $wpdb->insert("{$prefix}operadora", [
                'nome' => get_bloginfo('name'),
                'ans_registro' => '000000',
                'email_suporte' => get_bloginfo('admin_email'),
            ]);
        }

        $departamentos = ['atendimento', 'financeiro', 'comercial', 'assistencial', 'ouvidoria'];
        foreach ($departamentos as $i => $slug) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}departamentos WHERE slug=%s", $slug));
            if (!$exists) {
                $wpdb->insert("{$prefix}departamentos", [
                    'nome' => ucfirst($slug),
                    'slug' => $slug,
                    'ordem_fluxo' => $i + 1,
                ]);
            }
        }

        // Roles e capabilities
        add_role('ans_agent', 'Atendente SAC', [
            'read' => true,
            'ans_answer_tickets' => true,
        ]);
        add_role('ans_supervisor', 'Supervisor SAC', [
            'read' => true,
            'ans_answer_tickets' => true,
            'ans_manage_tickets' => true,
        ]);
        // Garantir que administradores tenham acesso
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('ans_answer_tickets');
            $admin->add_cap('ans_manage_tickets');
        }
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('ans_answer_tickets');
            $editor->add_cap('ans_manage_tickets');
        }

        // Criar páginas SAC e Controle de Chamados se não existirem
        self::maybe_create_page('sac', 'SAC', "[ans_ticket_form]\n[ans_ticket_track]");
        self::maybe_create_page('controle-de-chamados', 'Controle de Chamados', "[ans_ticket_dashboard]");
        if (!wp_next_scheduled('ans_tickets_sla_cron')) {
            wp_schedule_event(time(), 'ans_tickets_5min', 'ans_tickets_sla_cron');
        }
    }

    private static function maybe_create_page(string $slug, string $title, string $content): void
    {
        $existing = get_page_by_path($slug);
        if ($existing) {
            return;
        }
        wp_insert_post([
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
    }

    public static function maybe_update(): void
    {
        $installed_version = get_option('ans_tickets_version', '0.0.0');
        if (version_compare($installed_version, ANS_TICKETS_VERSION, '<')) {
            self::update_database();
            update_option('ans_tickets_version', ANS_TICKETS_VERSION);
        }
    }

    private static function update_database(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'ans_';

        // Verificar e adicionar novos campos na tabela clientes
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}clientes");
        
        if (!in_array('whatsapp', $columns)) {
            $wpdb->query("ALTER TABLE {$prefix}clientes ADD COLUMN whatsapp VARCHAR(30) AFTER telefone");
        }
        if (!in_array('data_nascimento', $columns)) {
            $wpdb->query("ALTER TABLE {$prefix}clientes ADD COLUMN data_nascimento DATE AFTER documento");
        }
        if (!in_array('cliente_uniodonto', $columns)) {
            $wpdb->query("ALTER TABLE {$prefix}clientes ADD COLUMN cliente_uniodonto BOOLEAN DEFAULT FALSE AFTER data_nascimento");
        }

        // Criar tabela de relacionamento departamento_users se não existir
        $table_users = "CREATE TABLE IF NOT EXISTS {$prefix}departamento_users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            departamento_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY dept_user (departamento_id, user_id),
            KEY departamento_id (departamento_id),
            KEY user_id (user_id)
        ) $charset;";
        dbDelta($table_users);

        // Garantir coluna responsavel_id em tickets
        $cols_tickets = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}tickets");
        if (!in_array('responsavel_id', $cols_tickets)) {
            $wpdb->query("ALTER TABLE {$prefix}tickets ADD COLUMN responsavel_id BIGINT UNSIGNED NULL AFTER departamento_id");
        }

        // Tabela respostas rápidas
        $table_quick = "CREATE TABLE IF NOT EXISTS {$prefix}respostas_rapidas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(120) NOT NULL,
            conteudo TEXT NOT NULL,
            escopo VARCHAR(20) NOT NULL,
            departamento_id BIGINT UNSIGNED,
            user_id BIGINT UNSIGNED,
            status_slug VARCHAR(60),
            assunto_id BIGINT UNSIGNED,
            ativo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY escopo (escopo),
            KEY departamento_id (departamento_id),
            KEY user_id (user_id),
            KEY status_slug (status_slug),
            KEY assunto_id (assunto_id)
        ) $charset;";
        dbDelta($table_quick);

        // Tabela filtros salvos
        $table_filters = "CREATE TABLE IF NOT EXISTS {$prefix}filtros_salvos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(120) NOT NULL,
            filtros LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset;";
        dbDelta($table_filters);

        // Tabela assuntos
        $table_assuntos = "CREATE TABLE IF NOT EXISTS {$prefix}assuntos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            departamento_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            ativo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY dept_slug (departamento_id, slug)
        ) $charset;";
        dbDelta($table_assuntos);

        // Tabela status custom
        $table_status = "CREATE TABLE IF NOT EXISTS {$prefix}status_custom (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            departamento_id BIGINT UNSIGNED,
            slug VARCHAR(80) NOT NULL,
            nome VARCHAR(120) NOT NULL,
            cor VARCHAR(20),
            ordem INT DEFAULT 0,
            ativo BOOLEAN DEFAULT TRUE,
            inicial BOOLEAN DEFAULT FALSE,
            final_resolvido BOOLEAN DEFAULT FALSE,
            final_nao_resolvido BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug_dept (slug, departamento_id)
        ) $charset;";
        dbDelta($table_status);

        // Tabela links de respostas rápidas
        $table_links = "CREATE TABLE IF NOT EXISTS {$prefix}respostas_rapidas_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            resposta_id BIGINT UNSIGNED NOT NULL,
            departamento_id BIGINT UNSIGNED,
            assunto_id BIGINT UNSIGNED,
            status_slug VARCHAR(80),
            PRIMARY KEY (id),
            KEY resposta_id (resposta_id),
            KEY departamento_id (departamento_id),
            KEY assunto_id (assunto_id),
            KEY status_slug (status_slug)
        ) $charset;";
        dbDelta($table_links);
    }
}
