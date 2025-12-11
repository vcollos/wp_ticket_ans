<?php
/**
 * Plugin Name: ANS Tickets
 * Description: Sistema de tickets (ANS) com formulários, acompanhamento e ouvidoria. Cria tabelas próprias e usa mídia do WordPress para anexos.
 * Version: 0.6.2
 * Author: Collos Ltda
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ANS_TICKETS_VERSION', '0.6.2');

define('ANS_TICKETS_PATH', plugin_dir_path(__FILE__));
define('ANS_TICKETS_URL', plugin_dir_url(__FILE__));

define('ANS_TICKETS_NAMESPACE', 'ans-tickets/v1');

define('ANS_TICKETS_OPTION', 'ans_tickets_settings');

spl_autoload_register(function ($class) {
    if (strpos($class, 'ANS\\Tickets\\') !== 0) {
        return;
    }
    $relative = str_replace('ANS\\Tickets\\', '', $class);
    $relativePath = str_replace('\\', '/', $relative);
    $file = ANS_TICKETS_PATH . strtolower($relativePath) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once ANS_TICKETS_PATH . 'inc/class-installer.php';
require_once ANS_TICKETS_PATH . 'inc/class-routes.php';
require_once ANS_TICKETS_PATH . 'inc/class-admin.php';
require_once ANS_TICKETS_PATH . 'inc/class-cron.php';
require_once ANS_TICKETS_PATH . 'inc/helpers.php';

register_activation_hook(__FILE__, ['ANS_Tickets_Installer', 'activate']);
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('ans_tickets_sla_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'ans_tickets_sla_cron');
    }
});
add_action('plugins_loaded', ['ANS_Tickets_Installer', 'maybe_update']);

add_action('rest_api_init', ['ANS_Tickets_Routes', 'register']);
ANS_Tickets_Admin::init();
ANS_Tickets_Cron::init();

// Shortcode: formulário de abertura
add_shortcode('ans_ticket_form', function () {
    wp_enqueue_style('ans-tickets-embed', ANS_TICKETS_URL . 'assets/embed.css', [], ANS_TICKETS_VERSION);
    wp_enqueue_script('ans-tickets-embed', ANS_TICKETS_URL . 'assets/embed.js', [], ANS_TICKETS_VERSION, true);
    wp_localize_script('ans-tickets-embed', 'ANS_TICKETS', [
        'api' => get_rest_url(null, ANS_TICKETS_NAMESPACE),
    ]);
    ob_start();
    ?>
    <div id="ans-ticket-form" class="ans-ticket-card">
        <h3>Novo Atendimento</h3>
        <p class="ans-section-subtitle">Abra um protocolo para falar com nossa equipe.</p>
        <form>
            <div class="ans-grid">
                <label class="full">
                    Você é cliente Uniodonto?
                    <select name="cliente_uniodonto" id="ans-cliente-uniodonto" required>
                        <option value="">Selecione</option>
                        <option value="true">Sim</option>
                        <option value="false">Não</option>
                    </select>
                </label>
                <label class="full">Nome Completo
                    <input name="nome_completo" required>
                </label>
                <label class="full">E-mail
                    <input name="email" type="email" required>
                </label>
                <label class="half">Telefone
                    <input name="telefone" required>
                </label>
                <label class="half">WhatsApp
                    <input name="whatsapp" required>
                </label>
                <label class="half">Documento (CPF/CNPJ)
                    <input name="documento" required>
                </label>
                <label class="half cliente-uni" style="display:none">Data de Nascimento
                    <input name="data_nascimento" type="date">
                </label>
                <label class="full">Departamento que deseja falar
                    <select name="departamento_id" id="ans-departamento" required>
                        <option value="">Selecione um departamento</option>
                    </select>
                </label>
                <label class="full">Assunto
                    <select name="assunto" id="ans-assunto" required>
                        <option value="">Selecione um assunto</option>
                    </select>
                </label>
                <label class="full field-ouvidoria" style="display:none">Protocolo anterior
                    <input name="ticket_origem" id="ans-ticket-origem">
                </label>
                <div id="ans-ouvidoria-notice" class="ans-ouvidoria-notice" style="display:none"></div>
                <div class="assist-block" style="display:none">
                    <label class="field-assistencial">Tipo de procedimento
                        <input name="tipo_de_procedimento">
                    </label>
                    <label class="field-assistencial">Prestador / Clínica
                        <input name="prestador">
                    </label>
                    <label class="field-assistencial">Data do evento
                        <input name="data_evento" type="date">
                    </label>
                    <label class="field-assistencial">Número de guia / orçamento
                        <input name="numero_guia">
                    </label>
                </div>
                <label class="full">Descreva seu atendimento
                    <textarea name="descricao" required></textarea>
                </label>
            </div>
            <div class="ans-actions">
                <button type="submit" class="ans-btn">Enviar Chamado</button>
            </div>
        </form>
        <div class="ans-ticket-result" style="display:none"></div>
    </div>
    <?php
    return ob_get_clean();
});

// Shortcode: kanban de atendimento
add_shortcode('ans_ticket_kanban', function () {
    if (!is_user_logged_in() || !ans_tickets_can_answer()) {
        return '<div class="ans-ticket-card"><p>Acesso restrito. Faça login como atendente.</p></div>';
    }
    wp_enqueue_style('ans-tickets-kanban', ANS_TICKETS_URL . 'assets/kanban.css', [], ANS_TICKETS_VERSION);
    wp_enqueue_script('ans-tickets-kanban', ANS_TICKETS_URL . 'assets/kanban.js', [], ANS_TICKETS_VERSION, true);
    wp_localize_script('ans-tickets-kanban', 'ANS_TICKETS_KANBAN', [
        'api' => get_rest_url(null, ANS_TICKETS_NAMESPACE),
        'nonce' => wp_create_nonce('wp_rest'),
        'status' => ans_tickets_statuses(),
        'prioridades' => ans_tickets_default_prioridades(),
    ]);
    ob_start();
    ?>
    <div id="ans-kanban" class="ans-kanban">
        <div class="kanban-filters">
            <select id="kanban-filter-status"><option value="">Status</option></select>
            <select id="kanban-filter-dep"><option value="">Departamento</option></select>
            <select id="kanban-filter-resp"><option value="">Responsável</option></select>
            <select id="kanban-filter-pri"><option value="">Prioridade</option><option value="baixa">Baixa</option><option value="media">Média</option><option value="alta">Alta</option></select>
            <input type="text" id="kanban-filter-proto" placeholder="Protocolo">
            <input type="text" id="kanban-filter-doc" placeholder="Documento">
            <button id="kanban-apply" class="ans-btn">Filtrar</button>
        </div>
        <div id="kanban-board" class="kanban-board" aria-live="polite"></div>
    </div>
    <?php
    return ob_get_clean();
});

// Shortcode: acompanhamento
add_shortcode('ans_ticket_track', function () {
    wp_enqueue_style('ans-tickets-embed', ANS_TICKETS_URL . 'assets/embed.css', [], ANS_TICKETS_VERSION);
    wp_enqueue_script('ans-tickets-embed', ANS_TICKETS_URL . 'assets/embed.js', [], ANS_TICKETS_VERSION, true);
    wp_localize_script('ans-tickets-embed', 'ANS_TICKETS', [
        'api' => get_rest_url(null, ANS_TICKETS_NAMESPACE),
    ]);
    ob_start();
    ?>
    <div id="ans-ticket-track" class="ans-ticket-card">
        <h3>Consultar Protocolo</h3>
        <p class="ans-section-subtitle">Se já tem o número, consulte aqui.</p>
        <form class="track-form">
            <label>Protocolo<input name="protocolo"></label>
            <label>Documento (CPF/CNPJ)<input name="documento"></label>
            <button type="submit">Consultar</button>
        </form>
        <div class="ans-ticket-details" style="display:none"></div>
    </div>
    <div id="ans-ticket-recover" class="ans-ticket-card" style="margin-top: 20px;">
        <h3>Esqueceu o protocolo?</h3>
        <p class="ans-section-subtitle">Informe CPF e data de nascimento para listar todos os seus chamados.</p>
        <form class="recover-form">
            <label>CPF<input name="documento" required></label>
            <label>Data de Nascimento<input name="data_nascimento" type="date" required></label>
            <button type="submit">Recuperar meus chamados</button>
        </form>
        <div class="ans-ticket-recover-results" style="display:none"></div>
    </div>
    <?php
    return ob_get_clean();
});

// Shortcode: dashboard de atendimento (fora do wp-admin)
add_shortcode('ans_ticket_dashboard', function () {
    $error = '';
    // Processa login frontal para atendentes
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ans_ticket_login_nonce']) && wp_verify_nonce($_POST['ans_ticket_login_nonce'], 'ans_ticket_agent_login')) {
        $creds = [
            'user_login' => sanitize_user($_POST['ans_ticket_user'] ?? ''),
            'user_password' => $_POST['ans_ticket_pass'] ?? '',
            'remember' => !empty($_POST['ans_ticket_remember']),
        ];
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) {
            $error = $user->get_error_message();
        } elseif (!$user->has_cap('ans_answer_tickets')) {
            wp_logout();
            $error = 'Seu usuário não tem permissão para responder chamados.';
        } else {
            wp_safe_redirect(get_permalink());
            exit;
        }
    }

    if (!is_user_logged_in() || !current_user_can('ans_answer_tickets')) {
        ob_start();
        ?>
        <div class="ans-ticket-card">
            <style>
                .ans-agent-login{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px 16px;margin-top:12px}
                .ans-agent-login label{display:flex;flex-direction:column;gap:6px;font-weight:600;color:#1c1c1c}
                .ans-agent-login input{padding:12px 14px;border:1px solid #dfe3e8;border-radius:10px;font-size:14px;background:#fbfbfd}
                .ans-agent-login .ans-check{flex-direction:row;align-items:center;font-weight:500}
                .ans-agent-login .ans-check input{width:auto;margin-right:8px;padding:0}
                .ans-agent-login button{background:#7a003c;color:#fff;border:none;padding:12px 18px;border-radius:10px;font-weight:700;cursor:pointer;width:fit-content}
                .ans-agent-login button:hover{background:#a10054}
                .ans-alert.error{padding:10px;border:1px solid #e8c4c4;background:#fff0f0;color:#a10000;border-radius:8px;margin-bottom:10px}
            </style>
            <h3>Área de Atendentes</h3>
            <p>Use seu usuário e senha do WordPress para acessar o painel de chamados.</p>
            <?php if ($error): ?>
                <div class="ans-alert error"><?php echo wp_kses_post($error); ?></div>
            <?php endif; ?>
            <form method="post" class="ans-agent-login">
                <?php wp_nonce_field('ans_ticket_agent_login', 'ans_ticket_login_nonce'); ?>
                <label>Usuário ou E-mail
                    <input type="text" name="ans_ticket_user" required autocomplete="username">
                </label>
                <label>Senha
                    <input type="password" name="ans_ticket_pass" required autocomplete="current-password">
                </label>
                <label class="ans-check">
                    <input type="checkbox" name="ans_ticket_remember" value="1"> Manter conectado
                </label>
                <button type="submit">Entrar</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    wp_enqueue_style('ans-tickets-admin', ANS_TICKETS_URL . 'assets/admin.css', [], ANS_TICKETS_VERSION);
    wp_enqueue_script('ans-tickets-admin', ANS_TICKETS_URL . 'assets/admin.js', [], ANS_TICKETS_VERSION, true);
    wp_localize_script('ans-tickets-admin', 'ANS_TICKETS_ADMIN', [
        'api' => get_rest_url(null, ANS_TICKETS_NAMESPACE),
        'nonce' => wp_create_nonce('wp_rest'),
        'user' => wp_get_current_user()->display_name,
        'user_id' => get_current_user_id(),
    ]);
    ob_start();
    ?>
    <div id="ans-dashboard" class="ans-dashboard">
        <header class="ans-dash-header">
            <div class="ans-head-left">
                <p class="ans-kicker">Área do atendente</p>
                <h2>Controle de Chamados</h2>
                <p class="ans-agent-name">Atendente: <?php echo esc_html(wp_get_current_user()->display_name); ?></p>
            </div>
            <div class="ans-head-meta">
                <div class="ans-pill">Sessão ativa</div>
            </div>
        </header>
        <section class="ans-filter-panel">
            <div class="ans-dash-filters">
                <div class="ans-filter-grid ans-filter-grid-4">
                    <label class="ans-field">
                        <span>Status</span>
                        <select id="filter-status">
                            <option value="">Todos</option>
                            <option value="aberto">Aberto</option>
                            <option value="em_triagem">Em Triagem</option>
                            <option value="aguardando_informacoes_solicitante">Aguardando Informações do Solicitante</option>
                            <option value="em_analise">Em Análise</option>
                            <option value="em_execucao">Em Atendimento / Execução</option>
                            <option value="aguardando_terceiros">Aguardando Terceiros</option>
                            <option value="aguardando_aprovacao">Aguardando Aprovação</option>
                            <option value="solucao_proposta">Solução Proposta</option>
                            <option value="resolvido">Resolvido</option>
                            <option value="fechado">Fechado</option>
                        </select>
                    </label>
                    <label class="ans-field">
                        <span>Departamento</span>
                        <select id="filter-departamento">
                            <option value="">Todos</option>
                        </select>
                    </label>
                    <label class="ans-field">
                        <span>Responsável</span>
                        <select id="filter-responsavel">
                            <option value="">Todos</option>
                        </select>
                    </label>
                    <label class="ans-field">
                        <span>Prioridade</span>
                        <select id="filter-prioridade">
                            <option value="">Todas</option>
                            <option value="alta">Alta</option>
                            <option value="media">Média</option>
                            <option value="baixa">Baixa</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="ans-filter-chips">
                <div>
                    <div class="ans-chip-label">Filtros ativos</div>
                    <div class="ans-chip-row" id="ans-active-chips"></div>
                </div>
                <div>
                    <div class="ans-chip-label">Filtros salvos</div>
                    <div class="ans-chip-row" id="ans-saved-chips"></div>
                </div>
            </div>
        </section>
        <main class="ans-dash-main">
            <section class="ans-dash-list">
                <div class="ans-side-filter">
                    <h4>Filtrar protocolos</h4>
                    <label class="ans-field">
                        <span>Protocolo</span>
                        <input type="text" id="filter-protocolo" placeholder="Ex: 3679662025">
                    </label>
                    <label class="ans-field">
                        <span>Documento</span>
                        <input type="text" id="filter-documento" placeholder="CPF/CNPJ">
                    </label>
                    <div class="ans-filter-actions">
                        <button id="apply-filters" class="btn btn-primary">Filtrar</button>
                        <button id="save-filters" type="button" class="btn btn-secondary">Salvar filtro</button>
                    </div>
                </div>
                <ul id="ticket-list"></ul>
            </section>
            <section class="ans-dash-detail" id="ticket-detail">
                <div class="placeholder">Selecione um chamado</div>
            </section>
        </main>
    </div>
    <?php
    return ob_get_clean();
});
