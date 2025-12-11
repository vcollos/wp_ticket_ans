<?php
if (!defined('ABSPATH')) {
    exit;
}

class ANS_Tickets_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    public static function add_admin_menu(): void
    {
        add_menu_page(
            'ANS Tickets',
            'ANS Tickets',
            'manage_options',
            'ans-tickets',
            [self::class, 'render_main_page'],
            'dashicons-tickets-alt',
            30
        );

        add_submenu_page(
            'ans-tickets',
            'Chamados (lista)',
            'Chamados',
            'manage_options',
            'ans-tickets-list',
            [self::class, 'render_list_page']
        );

        add_submenu_page(
            'ans-tickets',
            'Configurações',
            'Configurações',
            'manage_options',
            'ans-tickets-settings',
            [self::class, 'render_departamentos_page']
        );

        add_submenu_page(
            'ans-tickets',
            'Relatórios v2',
            'Relatórios v2',
            'manage_options',
            'ans-reports-v2',
            [self::class, 'render_reports_v2']
        );
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, 'ans-tickets') === false) {
            return;
        }

        wp_enqueue_style('ans-tickets-admin', ANS_TICKETS_URL . 'assets/admin.css', [], ANS_TICKETS_VERSION);
        wp_enqueue_script('ans-tickets-admin', ANS_TICKETS_URL . 'assets/admin.js', [], ANS_TICKETS_VERSION, true);
        wp_localize_script('ans-tickets-admin', 'ANS_TICKETS_ADMIN', [
            'api' => get_rest_url(null, ANS_TICKETS_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function render_main_page(): void
    {
        ?>
        <div class="wrap">
            <h1>ANS Tickets</h1>
            <div id="ans-admin-dashboard">
                <div id="ans-stats-cards" class="ans-stats-cards"></div>
                <div class="ans-chart-grid">
                    <div class="ans-chart-card">
                        <h3>Status</h3>
                        <div id="ans-chart-status"></div>
                    </div>
                    <div class="ans-chart-card">
                        <h3>Departamentos</h3>
                        <div id="ans-chart-dept"></div>
                    </div>
                    <div class="ans-chart-card">
                        <h3>Assuntos</h3>
                        <div id="ans-chart-subjects"></div>
                    </div>
                    <div class="ans-chart-card">
                        <h3>SLA / Resolução</h3>
                        <div id="ans-chart-sla"></div>
                    </div>
                    <div class="ans-chart-card">
                        <h3>Top Atendentes</h3>
                        <div id="ans-chart-agents"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_list_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        $table = new ANS_Tickets_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1>Chamados</h1>
            <form method="post">
                <?php $table->search_box('Buscar', 'ans_ticket_search'); ?>
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function render_departamentos_page(): void
    {
        ?>
        <div class="wrap">
            <h1>Configurações Gerais</h1>
            <div class="ans-config-grid">
                <div class="ans-config-card ans-card-highlight">
                    <h2>Shortcodes</h2>
                    <p class="description">Use estes shortcodes para exibir os formulários no site.</p>
                    <div class="ans-shortcode-row"><code>[ans_ticket_form]</code><span>Formulário de abertura</span></div>
                    <div class="ans-shortcode-row"><code>[ans_ticket_track]</code><span>Acompanhar / Recuperar chamados</span></div>
                    <div class="ans-shortcode-row"><code>[ans_ticket_dashboard]</code><span>Dashboard de atendentes (requer login/permissão)</span></div>
                </div>
                <div class="ans-config-card">
                    <h2>Dados da Operadora</h2>
                    <label for="ans-config-ans">Número ANS</label>
                    <input type="text" id="ans-config-ans" class="regular-text" placeholder="000000" maxlength="10">
                    <p class="description">Número que será usado no protocolo.</p>
                    <div class="ans-config-actions">
                        <button id="ans-save-settings" class="button button-primary">Salvar</button>
                    </div>
                </div>
                <div class="ans-config-card">
                    <h2>Sequencial de Protocolos</h2>
                    <p class="description">Defina o próximo número ou zere o sequencial.</p>
                    <label for="ans-seq-start">Próximo sequencial (hoje)</label>
                    <input type="number" id="ans-seq-start" class="small-text" min="1" value="1">
                    <div class="ans-config-actions">
                        <button id="ans-set-seq" class="button">Definir</button>
                        <button id="ans-reset-seq" class="button button-secondary">Zerar tudo</button>
                    </div>
                    <div id="ans-seq-info" class="description"></div>
                </div>
            </div>

            <hr>
            <h2>Departamentos</h2>
            <button id="ans-new-departamento" class="button button-primary">Novo Departamento</button>
            
            <div id="ans-departamentos-list"></div>

            <!-- Modal para criar/editar departamento -->
            <div id="ans-departamento-modal" style="display:none;">
                <div class="ans-modal-content">
                    <span class="ans-modal-close">&times;</span>
                    <h2 id="ans-modal-title">Novo Departamento</h2>
                    <form id="ans-departamento-form">
                        <input type="hidden" id="departamento-id" name="id">
                        <table class="form-table">
                            <tr>
                                <th><label for="departamento-nome">Nome</label></th>
                                <td><input type="text" id="departamento-nome" name="nome" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="departamento-slug">Slug</label></th>
                                <td><input type="text" id="departamento-slug" name="slug" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="departamento-ordem">Ordem</label></th>
                                <td><input type="number" id="departamento-ordem" name="ordem_fluxo" class="small-text" value="1"></td>
                            </tr>
                            <tr>
                                <th><label for="departamento-cor">Cor</label></th>
                                <td><input type="color" id="departamento-cor" name="cor" value="#0073aa"></td>
                            </tr>
                            <tr>
                                <th><label for="departamento-sla">SLA (horas)</label></th>
                                <td><input type="number" id="departamento-sla" name="sla_hours" class="small-text"></td>
                            </tr>
                            <tr>
                                <th><label for="departamento-ativo">Ativo</label></th>
                                <td><input type="checkbox" id="departamento-ativo" name="ativo" value="1" checked></td>
                            </tr>
                        </table>
                        <h3>Atendentes</h3>
                        <div id="ans-departamento-users">
                            <?php
                            $users = get_users(['who' => 'all']);
                            foreach ($users as $user) {
                                if (current_user_can('ans_answer_tickets') || $user->ID === get_current_user_id()) {
                                    echo '<label><input type="checkbox" name="users[]" value="' . esc_attr($user->ID) . '"> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</label><br>';
                                }
                            }
                            ?>
                        </div>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Salvar</button>
                            <button type="button" class="button ans-modal-cancel">Cancelar</button>
                        </p>
                    </form>
                </div>
            </div>

            <!-- Modal para excluir departamento -->
            <div id="ans-delete-departamento-modal" style="display:none;">
                <div class="ans-modal-content">
                    <span class="ans-modal-close">&times;</span>
                    <h2>Excluir Departamento</h2>
                    <p id="ans-delete-message"></p>
                    <form id="ans-delete-departamento-form">
                        <input type="hidden" id="delete-departamento-id" name="id">
                        <table class="form-table">
                            <tr>
                                <th><label for="transfer-departamento-id">Transferir para</label></th>
                                <td>
                                    <select id="transfer-departamento-id" name="transfer_to" required>
                                        <option value="">Selecione um departamento</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary button-delete">Excluir e Transferir</button>
                            <button type="button" class="button ans-modal-cancel">Cancelar</button>
                        </p>
                    </form>
                </div>
            </div>

            <hr>
            <h2>Assuntos por Departamento</h2>
            <div class="ans-config-grid">
                <div class="ans-config-card">
                    <label>Departamento</label>
                    <select id="ans-assunto-dep"></select>
                    <div class="ans-config-actions">
                        <input type="text" id="ans-assunto-nome" class="regular-text" placeholder="Novo assunto">
                        <input type="text" id="ans-assunto-slug" class="regular-text" placeholder="slug-opcional">
                        <button id="ans-assunto-save" class="button button-primary">Salvar</button>
                    </div>
                    <ul id="ans-assunto-list"></ul>
                </div>
                <div class="ans-config-card">
                    <h2>Status custom por departamento</h2>
                    <label>Departamento</label>
                    <select id="ans-status-dep"></select>
                    <div class="ans-config-actions">
                        <input type="text" id="ans-status-nome" class="regular-text" placeholder="Nome">
                        <input type="text" id="ans-status-slug" class="regular-text" placeholder="slug">
                        <input type="color" id="ans-status-cor" value="#a60069">
                        <input type="number" id="ans-status-ordem" class="small-text" placeholder="Ordem">
                        <label><input type="checkbox" id="ans-status-inicial"> Inicial</label>
                        <label><input type="checkbox" id="ans-status-final-ok"> Final Resolvido</label>
                        <label><input type="checkbox" id="ans-status-final-nok"> Final Não Resolvido</label>
                        <button id="ans-status-save" class="button button-primary">Salvar</button>
                    </div>
                    <ul id="ans-status-list"></ul>
                </div>
            </div>
        </div>
        <style>
            .ans-admin-dashboard { margin-top: 20px; }
            .ans-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
            .ans-stat-card { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; }
            .ans-stat-number { font-size: 32px; font-weight: bold; color: #0073aa; margin: 10px 0; }
            #ans-departamentos-list { margin-top: 20px; }
            .ans-departamento-item { background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
            .ans-departamento-info h3 { margin: 0 0 5px 0; }
            .ans-departamento-actions { display: flex; gap: 10px; }
            #ans-departamento-modal, #ans-delete-departamento-modal { position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
            .ans-modal-content { background: #fff; margin: 5% auto; padding: 20px; width: 80%; max-width: 600px; position: relative; }
            .ans-modal-close { position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; }
            #ans-departamento-users { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
            #ans-departamento-users label { display: block; padding: 5px; }
            .button-delete { background: #dc3232; border-color: #dc3232; color: #fff; }
            .ans-config-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(280px,1fr)); gap: 16px; margin: 12px 0 24px; }
            .ans-config-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px; }
            .ans-config-card h2 { margin-top: 0; }
            .ans-config-card label { font-weight: 600; display: block; margin-top: 8px; }
            .ans-config-card input[type="text"], .ans-config-card input[type="number"] { width: 100%; max-width: 240px; }
            .ans-config-actions { margin-top: 10px; display: flex; gap: 8px; }
            #ans-seq-info { margin-top: 6px; }
            .ans-card-highlight { border-color: #7a003c; box-shadow: 0 6px 12px rgba(122,0,60,0.08); }
            .ans-shortcode-row { display: flex; gap: 10px; align-items: center; margin: 6px 0; }
            .ans-shortcode-row code { background: #f5f5f5; padding: 6px 10px; border-radius: 6px; border: 1px solid #ddd; font-weight: 600; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            loadDepartamentos();
            loadSettings();
            initAssuntos();
            initStatus();
            
            $('#ans-new-departamento').on('click', function() {
                $('#ans-modal-title').text('Novo Departamento');
                $('#ans-departamento-form')[0].reset();
                $('#departamento-id').val('');
                $('#ans-departamento-modal').show();
            });

            $('.ans-modal-close, .ans-modal-cancel').on('click', function() {
                $('#ans-departamento-modal, #ans-delete-departamento-modal').hide();
            });

            $('#ans-departamento-form').on('submit', function(e) {
                e.preventDefault();
                const data = {
                    nome: $('#departamento-nome').val(),
                    slug: $('#departamento-slug').val(),
                    ordem_fluxo: $('#departamento-ordem').val(),
                    cor: $('#departamento-cor').val(),
                    sla_hours: $('#departamento-sla').val() || null,
                    ativo: $('#departamento-ativo').is(':checked') ? 1 : 0,
                    users: $('input[name="users[]"]:checked').map(function() { return $(this).val(); }).get()
                };
                const id = $('#departamento-id').val();
                const url = ANS_TICKETS_ADMIN.api + '/admin/departamentos' + (id ? '/' + id : '');
                const method = id ? 'PUT' : 'POST';

                $.ajax({
                    url: url,
                    method: method,
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    success: function() {
                        loadDepartamentos();
                        $('#ans-departamento-modal').hide();
                    },
                    error: function(xhr) {
                        alert('Erro: ' + (xhr.responseJSON?.error || 'Erro desconhecido'));
                    }
                });
            });

            function loadSettings(){
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/settings',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    success: function(res){
                        $('#ans-config-ans').val(res.ans_registro || '');
                        $('#ans-seq-info').text('Sequencial de hoje: ' + (res.seq_today || 0));
                        $('#ans-seq-start').val((res.seq_today||0)+1);
                    }
                });
            }

            $('#ans-save-settings').on('click', function(){
                const ans = $('#ans-config-ans').val();
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/settings',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    data: JSON.stringify({ ans_registro: ans }),
                    contentType: 'application/json',
                    success: function(){
                        alert('Configurações salvas.');
                        loadSettings();
                    },
                    error: function(xhr){
                        alert('Erro: ' + (xhr.responseJSON?.error || 'Erro desconhecido'));
                    }
                });
            });

            $('#ans-set-seq').on('click', function(){
                const seq = parseInt($('#ans-seq-start').val(),10) || 1;
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/settings',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    data: JSON.stringify({ seq_start: seq }),
                    contentType: 'application/json',
                    success: function(){
                        alert('Sequencial atualizado.');
                        loadSettings();
                    }
                });
            });

            $('#ans-reset-seq').on('click', function(){
                if(!confirm('Zerar todos os sequenciais?')) return;
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/settings',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    data: JSON.stringify({ reset_seq: true }),
                    contentType: 'application/json',
                    success: function(){
                        alert('Sequencial zerado.');
                        loadSettings();
                    }
                });
            });

            function loadDepartamentos() {
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/departamentos',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    success: function(departamentos) {
                        let html = '';
                        departamentos.forEach(function(dept) {
                            html += '<div class="ans-departamento-item">';
                            html += '<div class="ans-departamento-info">';
                            html += '<h3>' + dept.nome + ' (' + dept.slug + ')</h3>';
                            html += '<p>Ordem: ' + dept.ordem_fluxo + ' | SLA: ' + (dept.sla_hours || 'N/A') + 'h | Ativo: ' + (dept.ativo ? 'Sim' : 'Não') + '</p>';
                            html += '</div>';
                            html += '<div class="ans-departamento-actions">';
                            html += '<button class="button ans-edit-dept" data-id="' + dept.id + '">Editar</button>';
                            html += '<button class="button button-delete ans-delete-dept" data-id="' + dept.id + '">Excluir</button>';
                            html += '</div>';
                            html += '</div>';
                        });
                        $('#ans-departamentos-list').html(html || '<p>Nenhum departamento cadastrado.</p>');
                    }
                });
            }

            $(document).on('click', '.ans-edit-dept', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/departamentos/' + id,
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    success: function(dept) {
                        $('#departamento-id').val(dept.id);
                        $('#departamento-nome').val(dept.nome);
                        $('#departamento-slug').val(dept.slug);
                        $('#departamento-ordem').val(dept.ordem_fluxo);
                        $('#departamento-cor').val(dept.cor || '#0073aa');
                        $('#departamento-sla').val(dept.sla_hours || '');
                        $('#departamento-ativo').prop('checked', dept.ativo);
                        $('input[name="users[]"]').prop('checked', false);
                        if (dept.users) {
                            dept.users.forEach(function(userId) {
                                $('input[name="users[]"][value="' + userId + '"]').prop('checked', true);
                            });
                        }
                        $('#ans-modal-title').text('Editar Departamento');
                        $('#ans-departamento-modal').show();
                    }
                });
            });

            $(document).on('click', '.ans-delete-dept', function() {
                const id = $(this).data('id');
                $('#delete-departamento-id').val(id);
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/departamentos',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    success: function(departamentos) {
                        let options = '<option value="">Selecione um departamento</option>';
                        departamentos.forEach(function(dept) {
                            if (dept.id != id) {
                                options += '<option value="' + dept.id + '">' + dept.nome + '</option>';
                            }
                        });
                        $('#transfer-departamento-id').html(options);
                        $('#ans-delete-message').text('Este departamento possui protocolos abertos. Selecione um departamento para transferir os chamados.');
                        $('#ans-delete-departamento-modal').show();
                    }
                });
            });

            $('#ans-delete-departamento-form').on('submit', function(e) {
                e.preventDefault();
                const id = $('#delete-departamento-id').val();
                const transferTo = $('#transfer-departamento-id').val();
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/departamentos/' + id,
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    data: JSON.stringify({ transfer_to: transferTo }),
                    contentType: 'application/json',
                    success: function() {
                        loadDepartamentos();
                        $('#ans-delete-departamento-modal').hide();
                    },
                    error: function(xhr) {
                        alert('Erro: ' + (xhr.responseJSON?.error || 'Erro desconhecido'));
                    }
                });
            });

            function initAssuntos() {
                loadAssuntoDeps();
                $('#ans-assunto-save').on('click', function(){
                    const dep = parseInt($('#ans-assunto-dep').val(),10);
                    const nome = $('#ans-assunto-nome').val();
                    const slug = $('#ans-assunto-slug').val();
                    if(!dep || !nome){ alert('Selecione departamento e informe o nome'); return; }
                    $.ajax({
                        url: ANS_TICKETS_ADMIN.api + '/admin/assuntos',
                        method: 'POST',
                        headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                        data: JSON.stringify({departamento_id: dep, nome, slug}),
                        contentType: 'application/json',
                        success: function(){ $('#ans-assunto-nome').val(''); $('#ans-assunto-slug').val(''); loadAssuntos(dep); },
                        error: function(xhr){ alert(xhr.responseJSON?.error || 'Erro'); }
                    });
                });
                $('#ans-assunto-dep').on('change', function(){
                    loadAssuntos(parseInt(this.value,10));
                });
            }

            function loadAssuntoDeps(){
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/departamentos',
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    success: function(deps){
                        const opts = deps.map(d=>'<option value="'+d.id+'">'+d.nome+'</option>').join('');
                        $('#ans-assunto-dep, #ans-status-dep').html('<option value="">Selecione</option>'+opts);
                    }
                });
            }

            function loadAssuntos(depId){
                if(!depId){ $('#ans-assunto-list').html('<li>Selecione um departamento.</li>'); return; }
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/assuntos?departamento_id='+depId,
                    headers: { 'X-WP-Nonce': ANS_TICKETS_ADMIN.nonce },
                    success: function(rows){
                        if(!rows.length){ $('#ans-assunto-list').html('<li>Nenhum assunto.</li>'); return; }
                        const html = rows.map(r=>'<li data-id="'+r.id+'">'+r.nome+' <button class="button-link ans-del-assunto" data-id="'+r.id+'">Excluir</button></li>').join('');
                        $('#ans-assunto-list').html(html);
                    }
                });
            }

            $(document).on('click','.ans-del-assunto', function(){
                const id = $(this).data('id');
                if(!confirm('Excluir assunto?')) return;
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/assuntos/'+id,
                    method:'DELETE',
                    headers:{'X-WP-Nonce':ANS_TICKETS_ADMIN.nonce},
                    success:function(){ loadAssuntos(parseInt($('#ans-assunto-dep').val(),10)); }
                });
            });

            function initStatus(){
                $('#ans-status-save').on('click', function(){
                    const dep = parseInt($('#ans-status-dep').val(),10);
                    const nome = $('#ans-status-nome').val();
                    const slug = $('#ans-status-slug').val();
                    const cor = $('#ans-status-cor').val();
                    const ordem = parseInt($('#ans-status-ordem').val(),10) || 0;
                    const inicial = $('#ans-status-inicial').is(':checked');
                    const finalOk = $('#ans-status-final-ok').is(':checked');
                    const finalNok = $('#ans-status-final-nok').is(':checked');
                    if(!nome || !slug){ alert('Informe nome e slug'); return; }
                    $.ajax({
                        url: ANS_TICKETS_ADMIN.api + '/admin/status-custom',
                        method:'POST',
                        headers:{'X-WP-Nonce':ANS_TICKETS_ADMIN.nonce},
                        data: JSON.stringify({departamento_id: dep||null, nome, slug, cor, ordem, inicial, final_resolvido: finalOk, final_nao_resolvido: finalNok}),
                        contentType:'application/json',
                        success:function(){ $('#ans-status-nome').val(''); $('#ans-status-slug').val(''); $('#ans-status-inicial,#ans-status-final-ok,#ans-status-final-nok').prop('checked',false); loadStatus(dep); },
                        error:function(xhr){ alert(xhr.responseJSON?.error||'Erro'); }
                    });
                });
                $('#ans-status-dep').on('change', function(){
                    loadStatus(parseInt(this.value,10));
                });
            }

            function loadStatus(depId){
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/status-custom' + (depId ? ('?departamento_id='+depId) : ''),
                    headers:{'X-WP-Nonce':ANS_TICKETS_ADMIN.nonce},
                    success:function(rows){
                        if(!rows.length){ $('#ans-status-list').html('<li>Nenhum status custom.</li>'); return; }
                        const html = rows.map(r=>{
                            const flags = [
                                r.inicial ? 'Inicial' : '',
                                r.final_resolvido ? 'Final Resolvido' : '',
                                r.final_nao_resolvido ? 'Final Não Resolvido' : ''
                            ].filter(Boolean).join(' • ');
                            return '<li data-id="'+r.id+'"><span style="display:inline-block;width:12px;height:12px;background:'+ (r.cor||'#ccc') +';border-radius:50%;margin-right:6px"></span>'+r.nome+' ('+r.slug+') '+(flags?'<em>['+flags+']</em>':'')+' <button class="button-link ans-del-status" data-id="'+r.id+'">Excluir</button></li>';
                        }).join('');
                        $('#ans-status-list').html(html);
                    }
                });
            }

            $(document).on('click','.ans-del-status', function(){
                const id = $(this).data('id');
                if(!confirm('Excluir status?')) return;
                $.ajax({
                    url: ANS_TICKETS_ADMIN.api + '/admin/status-custom/'+id,
                    method:'DELETE',
                    headers:{'X-WP-Nonce':ANS_TICKETS_ADMIN.nonce},
                    success:function(){ loadStatus(parseInt($('#ans-status-dep').val(),10)); }
                });
            });
        });
        </script>
        <?php
    }

    public static function render_settings_page(): void
    {
        if (isset($_POST['ans_tickets_settings']) && check_admin_referer('ans_tickets_settings', 'ans_tickets_settings_nonce')) {
            $settings = $_POST['ans_tickets_settings'];
            update_option(ANS_TICKETS_OPTION, $settings);

            // Atualiza o número ANS na tabela operadora para refletir nos protocolos
            if (!empty($settings['ans_registro'])) {
                global $wpdb;
                $operadora_table = ans_tickets_table('operadora');
                $ans_number = preg_replace('/\D/', '', $settings['ans_registro']);
                $wpdb->query($wpdb->prepare("UPDATE {$operadora_table} SET ans_registro=%s LIMIT 1", $ans_number));
            }

            echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
        }
        
        $settings = get_option(ANS_TICKETS_OPTION, []);
        $ans_registro = $settings['ans_registro'] ?? '';
        ?>
        <div class="wrap">
            <h1>Configurações - ANS Tickets</h1>
            <form method="post" action="">
                <?php wp_nonce_field('ans_tickets_settings', 'ans_tickets_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="ans_registro">Número ANS da Operadora</label></th>
                        <td>
                            <input type="text" id="ans_registro" name="ans_tickets_settings[ans_registro]" value="<?php echo esc_attr($ans_registro); ?>" class="regular-text" placeholder="000000" maxlength="10">
                            <p class="description">Número ANS que será usado nos protocolos. Apenas dígitos.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function render_reports_v2(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
        wp_add_inline_script('chartjs', 'window.ANS_TICKETS_REPORTS = { api: "' . esc_js(get_rest_url(null, ANS_TICKETS_NAMESPACE . '/admin/reports/v2')) . '", nonce: "' . wp_create_nonce('wp_rest') . '" };');
        ?>
        <div class="wrap">
            <h1>Relatórios Avançados</h1>
            <div id="ans-reports" class="ans-reports-grid">
                <canvas id="chart-first-dept"></canvas>
                <canvas id="chart-first-agent"></canvas>
                <canvas id="chart-sla"></canvas>
                <canvas id="chart-assunto"></canvas>
                <canvas id="chart-hora"></canvas>
                <canvas id="chart-heatmap"></canvas>
            </div>
            <script>
            (function(){
                const cfg = window.ANS_TICKETS_REPORTS;
                async function fetchData(){
                    const res = await fetch(cfg.api,{headers:{'X-WP-Nonce':cfg.nonce}});
                    const json = await res.json();
                    render(json);
                }
                function render(data){
                    const ctx1 = document.getElementById('chart-first-dept');
                    if(ctx1){ new Chart(ctx1,{type:'bar',data:{labels:(data.first_response_departamento||[]).map(r=>r.departamento||'N/A'),datasets:[{label:'Horas',data:(data.first_response_departamento||[]).map(r=>parseFloat(r.horas||0)),backgroundColor:'#7a003c'}]}}); }
                    const ctx2 = document.getElementById('chart-first-agent');
                    if(ctx2){ new Chart(ctx2,{type:'bar',data:{labels:(data.first_response_agente||[]).map(r=>r.agente||'N/A'),datasets:[{label:'Horas',data:(data.first_response_agente||[]).map(r=>parseFloat(r.horas||0)),backgroundColor:'#0047ff'}]}}); }
                    const ctx3 = document.getElementById('chart-sla');
                    if(ctx3){ new Chart(ctx3,{type:'pie',data:{labels:['Cumprido','Estourado'],datasets:[{data:[data.sla?.cumprido||0,data.sla?.estourado||0],backgroundColor:['#3ac15b','#e84855']} ]}}); }
                    const ctx4 = document.getElementById('chart-assunto');
                    if(ctx4){ new Chart(ctx4,{type:'bar',data:{labels:(data.assunto||[]).map(r=>r.assunto||'N/A'),datasets:[{label:'Tickets',data:(data.assunto||[]).map(r=>parseInt(r.total||0,10)),backgroundColor:'#ffb000'}]}}); }
                    const ctx5 = document.getElementById('chart-hora');
                    if(ctx5){ new Chart(ctx5,{type:'line',data:{labels:(data.por_hora||[]).map(r=>r.hora),datasets:[{label:'Tickets',data:(data.por_hora||[]).map(r=>parseInt(r.total||0,10)),borderColor:'#7a003c',backgroundColor:'#f7ddee'}]}}); }
                    const ctx6 = document.getElementById('chart-heatmap');
                    if(ctx6){ new Chart(ctx6,{type:'bar',data:{labels:(data.heatmap||[]).map(r=>`D${r.dia}-H${r.hora}`),datasets:[{label:'Tickets',data:(data.heatmap||[]).map(r=>parseInt(r.total||0,10)),backgroundColor:'#60ebff'}]}}); }
                }
                fetchData();
            })();
            </script>
        </div>
        <?php
    }
}

// Garantir que a base de listagem esteja disponível antes de declarar a tabela customizada
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ANS_Tickets_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'ticket',
            'plural'   => 'tickets',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'protocolo' => 'Protocolo',
            'cliente' => 'Cliente',
            'status' => 'Status',
            'prioridade' => 'Prioridade',
            'departamento' => 'Departamento',
            'created_at' => 'Criado em',
            'updated_at' => 'Atualizado em',
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'protocolo' => ['protocolo', false],
            'created_at' => ['created_at', true],
            'updated_at' => ['updated_at', true],
        ];
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="ticket_ids[]" value="%d" />', $item['id']);
    }

    protected function column_protocolo($item)
    {
        $actions = [];
        $actions['view'] = '<a href="#" onclick="alert(\'Use o dashboard para responder.\');return false;">Ver</a>';
        $actions['delete'] = sprintf('<a href="%s" onclick="return confirm(\'Excluir este chamado?\');">Excluir</a>', wp_nonce_url(add_query_arg(['page'=>'ans-tickets-list','action'=>'delete','ticket'=>$item['id']]), 'ans_ticket_delete_'.$item['id']));
        return sprintf('<strong>%s</strong> %s', esc_html($item['protocolo']), $this->row_actions($actions));
    }

    protected function column_default($item, $column_name)
    {
        return esc_html($item[$column_name] ?? '');
    }

    public function get_bulk_actions(): array
    {
        return [
            'bulk-delete' => 'Excluir',
        ];
    }

    public function process_bulk_action()
    {
        if ($this->current_action() === 'delete' && !empty($_GET['ticket'])) {
            $id = (int)$_GET['ticket'];
            check_admin_referer('ans_ticket_delete_'.$id);
            $this->delete_tickets([$id]);
        }
        if ($this->current_action() === 'bulk-delete' && !empty($_POST['ticket_ids'])) {
            $ids = array_map('intval', (array)$_POST['ticket_ids']);
            $this->delete_tickets($ids);
        }
    }

    private function delete_tickets(array $ids): void
    {
        global $wpdb;
        if (!current_user_can('manage_options') || empty($ids)) {
            return;
        }
        $table_tickets = ans_tickets_table('tickets');
        $table_interacoes = ans_tickets_table('interacoes');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_interacoes} WHERE ticket_id IN ($placeholders)", $ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_tickets} WHERE id IN ($placeholders)", $ids));
    }

    public function prepare_items()
    {
        global $wpdb;
        $this->process_bulk_action();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $table_tickets = ans_tickets_table('tickets');
        $table_clientes = ans_tickets_table('clientes');
        $table_departamentos = ans_tickets_table('departamentos');

        $orderby = !empty($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'created_at';
        $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = '';
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare("WHERE t.protocolo LIKE %s OR c.nome_completo LIKE %s OR c.documento LIKE %s", $like, $like, $like);
        }

        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_tickets} t LEFT JOIN {$table_clientes} c ON t.cliente_id=c.id {$where}");

        $sql = $wpdb->prepare(
            "SELECT t.*, c.nome_completo AS cliente, d.nome AS departamento FROM {$table_tickets} t
             LEFT JOIN {$table_clientes} c ON t.cliente_id=c.id
             LEFT JOIN {$table_departamentos} d ON t.departamento_id=d.id
             {$where}
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);

        $this->items = $rows;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }
}
