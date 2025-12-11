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
            'Departamentos (lista)',
            'Departamentos (lista)',
            'manage_options',
            'ans-tickets-departamentos',
            [self::class, 'render_departamentos_table']
        );

        add_submenu_page(
            'ans-tickets',
            'Assuntos',
            'Assuntos',
            'manage_options',
            'ans-tickets-assuntos',
            [self::class, 'render_assuntos_table']
        );

        add_submenu_page(
            'ans-tickets',
            'Status Custom',
            'Status Custom',
            'manage_options',
            'ans-tickets-status',
            [self::class, 'render_status_table']
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
        global $wpdb;
        $deps_table = ans_tickets_table('departamentos');
        $dept_users = ans_tickets_table('departamento_users');
        $departamentos = $wpdb->get_results("
            SELECT d.*, (SELECT COUNT(*) FROM {$dept_users} du WHERE du.departamento_id=d.id) AS users_count
            FROM {$deps_table} d
            ORDER BY d.ordem_fluxo ASC
        ");
        $link_deps = admin_url('admin.php?page=ans-tickets-departamentos');
        $link_assuntos = admin_url('admin.php?page=ans-tickets-assuntos');
        $link_status = admin_url('admin.php?page=ans-tickets-status');
        ?>
        <div class="wrap">
            <h1>Configurações Gerais</h1>

            <div class="ans-settings-grid">
                <div class="ans-config-card ans-card-highlight">
                    <h2>Shortcodes</h2>
                    <p class="description">Copie e cole nos formulários/páginas.</p>
                    <div class="ans-shortcode-row"><code>[ans_ticket_form]</code><button class="button button-secondary ans-copy" data-code="[ans_ticket_form]">Copiar</button></div>
                    <div class="ans-shortcode-row"><code>[ans_ticket_track]</code><button class="button button-secondary ans-copy" data-code="[ans_ticket_track]">Copiar</button></div>
                    <div class="ans-shortcode-row"><code>[ans_ticket_dashboard]</code><button class="button button-secondary ans-copy" data-code="[ans_ticket_dashboard]">Copiar</button></div>
                    <div class="ans-shortcode-row"><code>[ans_ticket_kanban]</code><button class="button button-secondary ans-copy" data-code="[ans_ticket_kanban]">Copiar</button></div>
                </div>
                <div class="ans-config-card">
                    <h2>Dados da Operadora</h2>
                    <label for="ans-config-ans">Número ANS</label>
                    <input type="text" id="ans-config-ans" class="regular-text" placeholder="000000" maxlength="10">
                    <p class="description">Número usado no protocolo.</p>
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
                <div class="ans-config-card">
                    <h2>Gestão</h2>
                    <p class="description">CRUD completos ficam nas páginas dedicadas.</p>
                    <p><a class="button" href="<?php echo esc_url($link_deps); ?>">Gerenciar departamentos</a></p>
                    <p><a class="button" href="<?php echo esc_url($link_assuntos); ?>">Gerenciar assuntos</a></p>
                    <p><a class="button" href="<?php echo esc_url($link_status); ?>">Gerenciar status custom</a></p>
                </div>
            </div>

            <h2>Departamentos ativos</h2>
            <div class="ans-dep-quicklist">
                <?php
                if (empty($departamentos)) {
                    echo '<p>Nenhum departamento cadastrado.</p>';
                } else {
                    foreach ($departamentos as $dep) {
                        $badge = $dep->cor ?: '#7a003c';
                        $count = (int)$dep->users_count;
                        echo '<div class="ans-dep-pill">';
                        echo '<span class="ans-dep-dot" style="background:' . esc_attr($badge) . '"></span>';
                        echo '<strong>' . esc_html($dep->nome) . '</strong> <small>(' . esc_html($dep->slug) . ')</small>';
                        echo ' • Ordem ' . (int)$dep->ordem_fluxo . ' • SLA ' . ($dep->sla_hours ? (int)$dep->sla_hours . 'h' : 'N/A');
                        echo ' • Atendentes: ' . ($count ? $count : 'nenhum');
                        echo ' <a href="' . esc_url(add_query_arg(['page' => 'ans-tickets-departamentos', 'action' => 'edit', 'id' => $dep->id], admin_url('admin.php'))) . '">Editar</a>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
        <style>
            .ans-settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:12px 0 24px}
            .ans-config-card { background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;box-shadow:0 6px 12px rgba(0,0,0,0.03); }
            .ans-config-card h2{margin-top:0;}
            .ans-card-highlight { border-color:#7a003c; box-shadow:0 6px 12px rgba(122,0,60,0.08); }
            .ans-config-card label { font-weight:600; display:block; margin-top:8px; }
            .ans-config-card input[type="text"], .ans-config-card input[type="number"] { width:100%; max-width:240px; }
            .ans-config-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
            .ans-shortcode-row { display:flex; gap:10px; align-items:center; margin:6px 0; }
            .ans-shortcode-row code { background:#f5f5f5; padding:6px 10px; border-radius:6px; border:1px solid #ddd; font-weight:600; }
            .ans-dep-quicklist { margin-top:12px; display:flex; flex-direction:column; gap:8px; }
            .ans-dep-pill { background:#fff; border:1px solid #eee; border-radius:10px; padding:10px 12px; box-shadow:0 4px 10px rgba(0,0,0,0.02); }
            .ans-dep-dot { width:12px; height:12px; border-radius:50%; display:inline-block; margin-right:6px; vertical-align:middle; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            loadSettings();

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

            $(document).on('click','.ans-copy', function(){
                const code = $(this).data('code');
                navigator.clipboard.writeText(code);
                $(this).text('Copiado!').prop('disabled', true);
                setTimeout(()=>{ $(this).text('Copiar').prop('disabled', false); }, 1200);
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

    public static function render_departamentos_table(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_users = ans_tickets_table('departamento_users');

        $message = '';
        if (!empty($_POST['ans_dep_nonce']) && wp_verify_nonce($_POST['ans_dep_nonce'], 'ans_dep_save')) {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $nome = sanitize_text_field($_POST['nome'] ?? '');
            $slug = $_POST['slug'] !== '' ? sanitize_title($_POST['slug']) : sanitize_title($nome);
            $usersSelected = array_map('intval', $_POST['users'] ?? []);
            $data = [
                'nome' => $nome,
                'slug' => $slug,
                'ordem_fluxo' => (int)($_POST['ordem_fluxo'] ?? 1),
                'cor' => sanitize_text_field($_POST['cor'] ?? '#7a003c'),
                'sla_hours' => $_POST['sla_hours'] !== '' ? (int)$_POST['sla_hours'] : null,
                'ativo' => !empty($_POST['ativo']) ? 1 : 0,
            ];
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                self::sync_departamento_users($id, $usersSelected);
                $message = 'Departamento atualizado.';
            } else {
                $wpdb->insert($table, $data);
                $newId = (int)$wpdb->insert_id;
                self::sync_departamento_users($newId, $usersSelected);
                $message = 'Departamento criado.';
            }
        }
        if (!empty($_GET['action']) && $_GET['action'] === 'toggle' && !empty($_GET['id']) && check_admin_referer('ans_dep_toggle_' . (int)$_GET['id'])) {
            $id = (int)$_GET['id'];
            $ativo = (int)$wpdb->get_var($wpdb->prepare("SELECT ativo FROM {$table} WHERE id=%d", $id));
            $wpdb->update($table, ['ativo' => $ativo ? 0 : 1], ['id' => $id]);
            $message = $ativo ? 'Departamento desativado.' : 'Departamento ativado.';
        }

        $list = new ANS_Departamento_List_Table();
        $list->prepare_items();
        $editing = null;
        $editingUsers = [];
        if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$_GET['id']), ARRAY_A);
            $editingUsers = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$table_users} WHERE departamento_id=%d",
                (int)$_GET['id']
            ));
        }
        $allUsers = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        ?>
        <div class="wrap">
            <h1>Departamentos</h1>
            <?php if ($message): ?><div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
            <form method="post" class="ans-inline-form">
                <?php wp_nonce_field('ans_dep_save', 'ans_dep_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($editing['id'] ?? 0); ?>">
                <input type="text" name="nome" placeholder="Nome" value="<?php echo esc_attr($editing['nome'] ?? ''); ?>" required>
                <input type="text" name="slug" placeholder="slug" value="<?php echo esc_attr($editing['slug'] ?? ''); ?>">
                <input type="number" name="ordem_fluxo" placeholder="Ordem" value="<?php echo esc_attr($editing['ordem_fluxo'] ?? 1); ?>" min="1">
                <input type="color" name="cor" value="<?php echo esc_attr($editing['cor'] ?? '#7a003c'); ?>">
                <input type="number" name="sla_hours" placeholder="SLA (h)" value="<?php echo esc_attr($editing['sla_hours'] ?? ''); ?>">
                <label><input type="checkbox" name="ativo" value="1" <?php checked($editing['ativo'] ?? 1); ?>> Ativo</label>
                <div class="ans-users-select">
                    <strong>Atendentes</strong>
                    <div class="ans-users-grid">
                        <?php foreach ($allUsers as $u): ?>
                            <label><input type="checkbox" name="users[]" value="<?php echo esc_attr($u->ID); ?>" <?php checked(in_array((int)$u->ID, $editingUsers, true)); ?>> <?php echo esc_html($u->display_name); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php submit_button($editing ? 'Atualizar' : 'Adicionar', 'primary', 'submit', false); ?>
                <?php if ($editing): ?>
                    <a href="<?php echo admin_url('admin.php?page=ans-tickets-departamentos'); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </form>
            <form method="get">
                <input type="hidden" name="page" value="ans-tickets-departamentos">
                <?php $list->search_box('Buscar', 'ans_dep_search'); ?>
                <?php $list->display(); ?>
            </form>
            <style>
                .ans-inline-form { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:14px; background:#fff; padding:10px; border:1px solid #ddd; border-radius:8px; }
                .ans-inline-form input[type="text"], .ans-inline-form input[type="number"], .ans-inline-form input[type="color"] { margin-right:4px; }
                .ans-users-select { padding:8px 10px; border:1px solid #e2e2e2; border-radius:8px; background:#f9f9fb; max-height:180px; overflow-y:auto; }
                .ans-users-select strong { display:block; margin-bottom:6px; }
                .ans-users-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:6px; }
            </style>
        </div>
        <?php
    }

    private static function sync_departamento_users(int $departamentoId, array $users): void
    {
        global $wpdb;
        $table_users = ans_tickets_table('departamento_users');
        $wpdb->delete($table_users, ['departamento_id' => $departamentoId]);
        $clean = array_unique(array_filter(array_map('intval', $users)));
        foreach ($clean as $userId) {
            $wpdb->insert($table_users, [
                'departamento_id' => $departamentoId,
                'user_id' => $userId,
            ]);
        }
    }

    public static function render_assuntos_table(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        global $wpdb;
        $table = ans_tickets_table('assuntos');
        $depsTable = ans_tickets_table('departamentos');

        $message = '';
        if (!empty($_POST['ans_assunto_nonce']) && wp_verify_nonce($_POST['ans_assunto_nonce'], 'ans_assunto_save')) {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $dep = (int)($_POST['departamento_id'] ?? 0);
            $nome = sanitize_text_field($_POST['nome'] ?? '');
            $slug = $_POST['slug'] !== '' ? sanitize_title($_POST['slug']) : sanitize_title($nome);
            $data = [
                'departamento_id' => $dep,
                'nome' => $nome,
                'slug' => $slug,
                'ativo' => !empty($_POST['ativo']) ? 1 : 0,
            ];
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                $message = 'Assunto atualizado.';
            } else {
                $wpdb->insert($table, $data);
                $message = 'Assunto criado.';
            }
        }
        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id']) && check_admin_referer('ans_assunto_delete_' . (int)$_GET['id'])) {
            $wpdb->delete($table, ['id' => (int)$_GET['id']]);
            $message = 'Assunto excluído.';
        }

        $list = new ANS_Assunto_List_Table();
        $list->prepare_items();
        $editing = null;
        if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$_GET['id']), ARRAY_A);
        }
        $deps = $wpdb->get_results("SELECT id,nome FROM {$depsTable} ORDER BY nome ASC");
        ?>
        <div class="wrap">
            <h1>Assuntos</h1>
            <?php if ($message): ?><div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
            <form method="post" class="ans-inline-form">
                <?php wp_nonce_field('ans_assunto_save', 'ans_assunto_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($editing['id'] ?? 0); ?>">
                <select name="departamento_id" required>
                    <option value="">Departamento</option>
                    <?php foreach ($deps as $d): ?>
                        <option value="<?php echo esc_attr($d->id); ?>" <?php selected(isset($editing['departamento_id']) && (int)$editing['departamento_id'] === (int)$d->id); ?>><?php echo esc_html($d->nome); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="nome" placeholder="Nome" value="<?php echo esc_attr($editing['nome'] ?? ''); ?>" required>
                <input type="text" name="slug" placeholder="slug" value="<?php echo esc_attr($editing['slug'] ?? ''); ?>">
                <label><input type="checkbox" name="ativo" value="1" <?php checked($editing['ativo'] ?? 1); ?>> Ativo</label>
                <?php submit_button($editing ? 'Atualizar' : 'Adicionar', 'primary', 'submit', false); ?>
                <?php if ($editing): ?>
                    <a href="<?php echo admin_url('admin.php?page=ans-tickets-assuntos'); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </form>
            <form method="get">
                <input type="hidden" name="page" value="ans-tickets-assuntos">
                <?php $list->search_box('Buscar', 'ans_assunto_search'); ?>
                <?php $list->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function render_status_table(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        global $wpdb;
        $table = ans_tickets_table('status_custom');
        $depsTable = ans_tickets_table('departamentos');

        $message = '';
        self::seed_default_statuses();
        if (!empty($_POST['ans_status_nonce']) && wp_verify_nonce($_POST['ans_status_nonce'], 'ans_status_save')) {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $dep = $_POST['departamento_id'] !== '' ? (int)$_POST['departamento_id'] : null;
            $nome = sanitize_text_field($_POST['nome'] ?? '');
            $slug = $_POST['slug'] !== '' ? sanitize_title($_POST['slug']) : sanitize_title($nome);
            $data = [
                'departamento_id' => $dep,
                'nome' => $nome,
                'slug' => $slug,
                'cor' => sanitize_text_field($_POST['cor'] ?? '#7a003c'),
                'ordem' => (int)($_POST['ordem'] ?? 0),
                'ativo' => !empty($_POST['ativo']) ? 1 : 0,
                'inicial' => !empty($_POST['inicial']) ? 1 : 0,
                'final_resolvido' => !empty($_POST['final_resolvido']) ? 1 : 0,
                'final_nao_resolvido' => !empty($_POST['final_nao_resolvido']) ? 1 : 0,
            ];
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                $message = 'Status atualizado.';
            } else {
                $wpdb->insert($table, $data);
                $id = $wpdb->insert_id;
                $message = 'Status criado.';
            }
            // enforce uniqueness of flags per departamento (or global)
            $depWhere = $dep ? $wpdb->prepare("= %d", $dep) : "IS NULL";
            if (!empty($_POST['inicial'])) {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET inicial=0 WHERE departamento_id {$depWhere} AND id != %d", $id));
            }
            if (!empty($_POST['final_resolvido'])) {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET final_resolvido=0 WHERE departamento_id {$depWhere} AND id != %d", $id));
            }
            if (!empty($_POST['final_nao_resolvido'])) {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET final_nao_resolvido=0 WHERE departamento_id {$depWhere} AND id != %d", $id));
            }
        }
        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id']) && check_admin_referer('ans_status_delete_' . (int)$_GET['id'])) {
            $wpdb->delete($table, ['id' => (int)$_GET['id']]);
            $message = 'Status excluído.';
        }

        $list = new ANS_Status_List_Table();
        $list->prepare_items();
        $editing = null;
        if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$_GET['id']), ARRAY_A);
        }
        $deps = $wpdb->get_results("SELECT id,nome FROM {$depsTable} ORDER BY nome ASC");
        ?>
        <div class="wrap">
            <h1>Status Custom</h1>
            <?php if ($message): ?><div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
            <form method="post" class="ans-inline-form">
                <?php wp_nonce_field('ans_status_save', 'ans_status_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($editing['id'] ?? 0); ?>">
                <select name="departamento_id">
                    <option value="">Global</option>
                    <?php foreach ($deps as $d): ?>
                        <option value="<?php echo esc_attr($d->id); ?>" <?php selected(isset($editing['departamento_id']) && (int)$editing['departamento_id'] === (int)$d->id); ?>><?php echo esc_html($d->nome); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="nome" placeholder="Nome" value="<?php echo esc_attr($editing['nome'] ?? ''); ?>" required>
                <input type="text" name="slug" placeholder="slug" value="<?php echo esc_attr($editing['slug'] ?? ''); ?>">
                <input type="color" name="cor" value="<?php echo esc_attr($editing['cor'] ?? '#7a003c'); ?>">
                <input type="number" name="ordem" placeholder="Ordem" value="<?php echo esc_attr($editing['ordem'] ?? 0); ?>">
                <label><input type="checkbox" name="inicial" value="1" <?php checked($editing['inicial'] ?? 0); ?>> Inicial</label>
                <label><input type="checkbox" name="final_resolvido" value="1" <?php checked($editing['final_resolvido'] ?? 0); ?>> Final Resolvido</label>
                <label><input type="checkbox" name="final_nao_resolvido" value="1" <?php checked($editing['final_nao_resolvido'] ?? 0); ?>> Final Não Resolvido</label>
                <label><input type="checkbox" name="ativo" value="1" <?php checked($editing['ativo'] ?? 1); ?>> Ativo</label>
                <?php submit_button($editing ? 'Atualizar' : 'Adicionar', 'primary', 'submit', false); ?>
                <?php if ($editing): ?>
                    <a href="<?php echo admin_url('admin.php?page=ans-tickets-status'); ?>" class="button">Cancelar</a>
                <?php endif; ?>
            </form>
            <form method="get">
                <input type="hidden" name="page" value="ans-tickets-status">
                <?php $list->display(); ?>
            </form>
        </div>
        <?php
    }

    private static function seed_default_statuses(): void
    {
        global $wpdb;
        $deptTable = ans_tickets_table('departamentos');
        $statusTable = ans_tickets_table('status_custom');
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $statusTable))) {
            return;
        }
        $deptMap = $wpdb->get_results("SELECT id, slug FROM {$deptTable}", OBJECT_K);
        $pipelines = [
            'assistencial' => [
                ['Recebido', true, false, false],
                ['Em Análise Técnica', false, false, false],
                ['Pendência de Documentos', false, false, false],
                ['Aguardando Cooperado / Rede', false, false, false],
                ['Autorizado', false, false, false],
                ['Negado / Glosado', false, false, true],
                ['Concluído', false, true, false],
            ],
            'atendimento' => [
                ['Recebido', true, false, false],
                ['Em Atendimento', false, false, false],
                ['Aguardando Retorno do Beneficiário', false, false, false],
                ['Aguardando Terceiros (TI / Comercial / Financeiro)', false, false, false],
                ['Resolvido', false, true, false],
                ['Não Resolvido', false, false, true],
            ],
            'comercial' => [
                ['Recebido', true, false, false],
                ['Qualificação da Demanda', false, false, false],
                ['Proposta Enviada', false, false, false],
                ['Aguardando Aprovação', false, false, false],
                ['Aprovado', false, true, false],
                ['Recusado', false, false, true],
                ['Concluído', false, true, false],
            ],
            'financeiro' => [
                ['Recebido', true, false, false],
                ['Em Verificação', false, false, false],
                ['Aguardando Documentos', false, false, false],
                ['Ajustes em Execução', false, false, false],
                ['Finalizado com Sucesso', false, true, false],
                ['Finalizado com Pendências', false, false, true],
            ],
            'ouvidoria' => [
                ['Recebido', true, false, false],
                ['Admissibilidade / Classificação', false, false, false],
                ['Encaminhado ao Setor Responsável', false, false, false],
                ['Aguardando Resposta', false, false, false],
                ['Retorno ao Beneficiário', false, false, false],
                ['Resolvido', false, true, false],
                ['Não Resolvido', false, false, true],
            ],
        ];
        foreach ($pipelines as $slugDept => $statuses) {
            if (empty($deptMap[$slugDept])) {
                continue;
            }
            $depId = (int)$deptMap[$slugDept]->id;
            foreach ($statuses as $idx => [$nome, $inicial, $finalOk, $finalNok]) {
                $slug = sanitize_title($nome);
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$statusTable} WHERE slug=%s AND departamento_id=%d", $slug, $depId));
                if ($exists) {
                    continue;
                }
                $wpdb->insert($statusTable, [
                    'departamento_id' => $depId,
                    'nome' => $nome,
                    'slug' => $slug,
                    'cor' => '#7a003c',
                    'ordem' => $idx,
                    'ativo' => 1,
                    'inicial' => $inicial ? 1 : 0,
                    'final_resolvido' => $finalOk ? 1 : 0,
                    'final_nao_resolvido' => $finalNok ? 1 : 0,
                ]);
            }
        }
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

class ANS_Departamento_List_Table extends WP_List_Table
{
    private array $items_raw = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'departamento',
            'plural' => 'departamentos',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'nome' => 'Nome',
            'slug' => 'Slug',
            'ordem_fluxo' => 'Ordem',
            'sla_hours' => 'SLA (h)',
            'users_count' => 'Atendentes',
            'ativo' => 'Ativo',
        ];
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    protected function column_nome($item)
    {
        $actions = [
            'edit' => sprintf('<a href="%s">Editar</a>', esc_url(add_query_arg(['page' => 'ans-tickets-departamentos', 'action' => 'edit', 'id' => $item['id']], admin_url('admin.php')))),
            'toggle' => sprintf('<a href="%s">%s</a>', wp_nonce_url(add_query_arg(['page' => 'ans-tickets-departamentos', 'action' => 'toggle', 'id' => $item['id']], admin_url('admin.php')), 'ans_dep_toggle_' . $item['id']), $item['ativo'] ? 'Desativar' : 'Ativar'),
        ];
        return sprintf('<strong>%s</strong> %s', esc_html($item['nome']), $this->row_actions($actions));
    }

    protected function column_default($item, $column_name)
    {
        if ($column_name === 'ativo') {
            return $item['ativo'] ? 'Sim' : 'Não';
        }
        if ($column_name === 'users_count') {
            $count = (int)($item['users_count'] ?? 0);
            return $count ? $count : '0';
        }
        return esc_html($item[$column_name] ?? '');
    }

    protected function get_bulk_actions(): array
    {
        return [];
    }

    public function prepare_items(): void
    {
        global $wpdb;
        $table = ans_tickets_table('departamentos');
        $table_users = ans_tickets_table('departamento_users');
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = '';
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare("WHERE nome LIKE %s OR slug LIKE %s", $like, $like);
        }
        $this->items_raw = $wpdb->get_results("
            SELECT d.*, (SELECT COUNT(*) FROM {$table_users} du WHERE du.departamento_id=d.id) AS users_count
            FROM {$table} d
            {$where}
            ORDER BY d.ordem_fluxo ASC
        ", ARRAY_A);
        $this->items = $this->items_raw;
        $this->_column_headers = [$this->get_columns(), [], []];
    }
}

class ANS_Assunto_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'assunto',
            'plural' => 'assuntos',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'nome' => 'Nome',
            'slug' => 'Slug',
            'departamento' => 'Departamento',
            'ativo' => 'Ativo',
        ];
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    protected function column_nome($item)
    {
        $actions = [
            'edit' => sprintf('<a href="%s">Editar</a>', esc_url(add_query_arg(['page' => 'ans-tickets-assuntos', 'action' => 'edit', 'id' => $item['id']], admin_url('admin.php')))),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Excluir este assunto?\')">Excluir</a>', wp_nonce_url(add_query_arg(['page' => 'ans-tickets-assuntos', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 'ans_assunto_delete_' . $item['id'])),
        ];
        return sprintf('<strong>%s</strong> %s', esc_html($item['nome']), $this->row_actions($actions));
    }

    protected function column_default($item, $column_name)
    {
        if ($column_name === 'ativo') {
            return $item['ativo'] ? 'Sim' : 'Não';
        }
        return esc_html($item[$column_name] ?? '');
    }

    public function prepare_items(): void
    {
        global $wpdb;
        $a = ans_tickets_table('assuntos');
        $d = ans_tickets_table('departamentos');
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = '';
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare("WHERE a.nome LIKE %s OR a.slug LIKE %s", $like, $like);
        }
        $rows = $wpdb->get_results("SELECT a.*, d.nome AS departamento FROM {$a} a LEFT JOIN {$d} d ON a.departamento_id=d.id {$where} ORDER BY a.nome ASC", ARRAY_A);
        $this->items = $rows;
        $this->_column_headers = [$this->get_columns(), [], []];
    }
}

class ANS_Status_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'status_custom',
            'plural' => 'status_custom',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'nome' => 'Nome',
            'slug' => 'Slug',
            'departamento' => 'Departamento',
            'ordem' => 'Ordem',
            'flags' => 'Flags',
            'ativo' => 'Ativo',
        ];
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    protected function column_nome($item)
    {
        $color = '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' . esc_attr($item['cor'] ?: '#ccc') . ';margin-right:6px"></span>';
        $actions = [
            'edit' => sprintf('<a href="%s">Editar</a>', esc_url(add_query_arg(['page' => 'ans-tickets-status', 'action' => 'edit', 'id' => $item['id']], admin_url('admin.php')))),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Excluir este status?\')">Excluir</a>', wp_nonce_url(add_query_arg(['page' => 'ans-tickets-status', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 'ans_status_delete_' . $item['id'])),
        ];
        return sprintf('<strong>%s%s</strong> %s', $color, esc_html($item['nome']), $this->row_actions($actions));
    }

    protected function column_default($item, $column_name)
    {
        if ($column_name === 'ativo') {
            return $item['ativo'] ? 'Sim' : 'Não';
        }
        if ($column_name === 'flags') {
            $flags = [];
            if (!empty($item['inicial'])) {
                $flags[] = 'Inicial';
            }
            if (!empty($item['final_resolvido'])) {
                $flags[] = 'Final Resolvido';
            }
            if (!empty($item['final_nao_resolvido'])) {
                $flags[] = 'Final Não Resolvido';
            }
            return $flags ? implode(', ', $flags) : '-';
        }
        return esc_html($item[$column_name] ?? '');
    }

    public function prepare_items(): void
    {
        global $wpdb;
        $s = ans_tickets_table('status_custom');
        $d = ans_tickets_table('departamentos');
        $rows = $wpdb->get_results("SELECT s.*, d.nome AS departamento FROM {$s} s LEFT JOIN {$d} d ON s.departamento_id=d.id ORDER BY s.ordem ASC, s.nome ASC", ARRAY_A);
        $this->items = $rows;
        $this->_column_headers = [$this->get_columns(), [], []];
    }
}
