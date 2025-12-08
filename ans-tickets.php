<?php
/**
 * Plugin Name: ANS Tickets
 * Description: Sistema de tickets (ANS) com formulários, acompanhamento e ouvidoria. Cria tabelas próprias e usa mídia do WordPress para anexos.
 * Version: 0.2.0
 * Author: Collos Ltda
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ANS_TICKETS_VERSION', '0.2.0');

define('ANS_TICKETS_PATH', plugin_dir_path(__FILE__));
define('ANS_TICKETS_URL', plugin_dir_url(__FILE__));

define('ANS_TICKETS_NAMESPACE', 'ans-tickets/v1');

define('ANS_TICKETS_OPTION', 'ans_tickets_settings');

require_once ANS_TICKETS_PATH . 'inc/class-installer.php';
require_once ANS_TICKETS_PATH . 'inc/class-routes.php';
require_once ANS_TICKETS_PATH . 'inc/class-admin.php';
require_once ANS_TICKETS_PATH . 'inc/helpers.php';

register_activation_hook(__FILE__, ['ANS_Tickets_Installer', 'activate']);
add_action('plugins_loaded', ['ANS_Tickets_Installer', 'maybe_update']);

add_action('rest_api_init', ['ANS_Tickets_Routes', 'register']);
ANS_Tickets_Admin::init();

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
                <label class="half">Data de Nascimento
                    <input name="data_nascimento" type="date" required>
                </label>
                <label class="full">Assunto
                    <select name="assunto" id="ans-assunto" required>
                        <option value="">Selecione um assunto</option>
                    </select>
                </label>
                <label class="full">Descrição
                    <textarea name="descricao" required></textarea>
                </label>
                <div class="assist-block" style="display:none">
                    <label>Protocolo anterior (Ouvidoria)
                        <input name="ticket_origem">
                    </label>
                    <label>Tipo de procedimento (Assistencial)
                        <input name="tipo_de_procedimento">
                    </label>
                    <label>Prestador / Clínica
                        <input name="prestador">
                    </label>
                    <label>Data do evento
                        <input name="data_evento" type="date">
                    </label>
                    <label>Número de guia / orçamento
                        <input name="numero_guia">
                    </label>
                </div>
            </div>
            <div class="ans-actions">
                <button type="submit" class="ans-btn">Enviar</button>
            </div>
        </form>
        <div class="ans-ticket-result" style="display:none"></div>
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
        <h3>Acompanhar Protocolo</h3>
        <form class="track-form">
            <label>Protocolo<input name="protocolo"></label>
            <label>Documento (CPF/CNPJ)<input name="documento"></label>
            <label>Data de Nascimento<input name="data_nascimento" type="date"></label>
            <button type="submit">Consultar</button>
        </form>
        <div class="ans-ticket-details" style="display:none"></div>
    </div>
    <div id="ans-ticket-recover" class="ans-ticket-card" style="margin-top: 20px;">
        <h3>Recuperar Meus Chamados</h3>
        <p>Esqueceu o protocolo? Informe seu CPF e data de nascimento para recuperar todos os seus chamados.</p>
        <form class="recover-form">
            <label>CPF<input name="documento" required></label>
            <label>Data de Nascimento<input name="data_nascimento" type="date" required></label>
            <button type="submit">Recuperar Chamados</button>
        </form>
        <div class="ans-ticket-recover-results" style="display:none"></div>
    </div>
    <?php
    return ob_get_clean();
});

// Shortcode: dashboard de atendimento (fora do wp-admin)
add_shortcode('ans_ticket_dashboard', function () {
    if (!is_user_logged_in() || !current_user_can('ans_answer_tickets')) {
        return '<div class="ans-ticket-card">Acesso restrito aos atendentes.</div>';
    }
    wp_enqueue_style('ans-tickets-admin', ANS_TICKETS_URL . 'assets/admin.css', [], ANS_TICKETS_VERSION);
    wp_enqueue_script('ans-tickets-admin', ANS_TICKETS_URL . 'assets/admin.js', [], ANS_TICKETS_VERSION, true);
    wp_localize_script('ans-tickets-admin', 'ANS_TICKETS_ADMIN', [
        'api' => get_rest_url(null, ANS_TICKETS_NAMESPACE),
        'nonce' => wp_create_nonce('wp_rest'),
        'user' => wp_get_current_user()->display_name,
    ]);
    ob_start();
    ?>
    <div id="ans-dashboard" class="ans-dashboard">
        <header class="ans-dash-header">
            <div>
                <h2>Controle de Chamados</h2>
                <p>Atendente: <?php echo esc_html(wp_get_current_user()->display_name); ?></p>
            </div>
            <div class="ans-dash-filters">
                <select id="filter-status">
                    <option value="">Status</option>
                    <option value="novo">Novo</option>
                    <option value="atendimento">Atendimento</option>
                    <option value="financeiro">Financeiro</option>
                    <option value="comercial">Comercial</option>
                    <option value="assistencial">Assistencial</option>
                    <option value="ouvidoria">Ouvidoria</option>
                    <option value="pendente_cliente">Pendente Cliente</option>
                    <option value="concluido">Concluído</option>
                    <option value="arquivado">Arquivado</option>
                </select>
                <input type="text" id="filter-protocolo" placeholder="Protocolo">
                <input type="text" id="filter-documento" placeholder="Documento">
                <button id="apply-filters">Filtrar</button>
            </div>
        </header>
        <main class="ans-dash-main">
            <section class="ans-dash-list">
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
