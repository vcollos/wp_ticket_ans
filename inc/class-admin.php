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
            'Departamentos',
            'Departamentos',
            'manage_options',
            'ans-tickets-departamentos',
            [self::class, 'render_departamentos_page']
        );

        add_submenu_page(
            'ans-tickets',
            'Configurações',
            'Configurações',
            'manage_options',
            'ans-tickets-settings',
            [self::class, 'render_settings_page']
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
            <div class="ans-admin-dashboard">
                <div class="ans-stats">
                    <?php
                    global $wpdb;
                    $table_tickets = ans_tickets_table('tickets');
                    $novos = $wpdb->get_var("SELECT COUNT(*) FROM {$table_tickets} WHERE status='novo'");
                    $em_atendimento = $wpdb->get_var("SELECT COUNT(*) FROM {$table_tickets} WHERE status='atendimento'");
                    $concluidos = $wpdb->get_var("SELECT COUNT(*) FROM {$table_tickets} WHERE status='concluido'");
                    ?>
                    <div class="ans-stat-card">
                        <h3>Novos</h3>
                        <p class="ans-stat-number"><?php echo esc_html($novos); ?></p>
                    </div>
                    <div class="ans-stat-card">
                        <h3>Em Atendimento</h3>
                        <p class="ans-stat-number"><?php echo esc_html($em_atendimento); ?></p>
                    </div>
                    <div class="ans-stat-card">
                        <h3>Concluídos</h3>
                        <p class="ans-stat-number"><?php echo esc_html($concluidos); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_departamentos_page(): void
    {
        ?>
        <div class="wrap">
            <h1>Departamentos</h1>
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
        </style>
        <script>
        jQuery(document).ready(function($) {
            loadDepartamentos();
            
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
        });
        </script>
        <?php
    }

    public static function render_settings_page(): void
    {
        $settings = get_option(ANS_TICKETS_OPTION, []);
        $sac_page = $settings['sac_page'] ?? '';
        $dashboard_page = $settings['dashboard_page'] ?? '';
        ?>
        <div class="wrap">
            <h1>Configurações - ANS Tickets</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ans_tickets_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="sac_page">Página do SAC</label></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'ans_tickets_settings[sac_page]',
                                'selected' => $sac_page,
                                'show_option_none' => 'Selecione uma página',
                                'option_none_value' => '',
                            ]);
                            ?>
                            <p class="description">Página onde os clientes abrem e acompanham chamados.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="dashboard_page">Página do Dashboard</label></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'ans_tickets_settings[dashboard_page]',
                                'selected' => $dashboard_page,
                                'show_option_none' => 'Selecione uma página',
                                'option_none_value' => '',
                            ]);
                            ?>
                            <p class="description">Página onde atendentes gerenciam os chamados.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

