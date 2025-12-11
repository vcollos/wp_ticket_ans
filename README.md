# ANS Tickets (Uniodonto) – v0.7.7

Sistema de chamados inspirado em Monday/Material Design para WordPress. Front-end para beneficiários e painel (wp-admin ou página pública) para atendentes.

## Funcionalidades
- **Formulário público** `[ans_ticket_form]`: abre chamado com dados do beneficiário (nome, email, telefone, WhatsApp, documento, data de nascimento se cliente Uniodonto), seleção de departamento e assuntos ligados ao departamento (carregados via API), descrição, campos de Ouvidoria (protocolo anterior + aviso) e Assistencial/Atendimento (tipo de procedimento, prestador, data do evento, número de guia). Exibe protocolo gerado na hora. Usa o status inicial definido pelos administradores (custom) quando existir.
- **Acompanhar/recuperar** `[ans_ticket_track]`: 
  - Com protocolo: login rápido (protocolo + CPF/CNPJ) gera token de 1h, mostra detalhes, histórico e permite responder.
  - Sem protocolo: CPF + data de nascimento → lista todos os chamados do cliente e abre cada um.
- **Dashboard para atendentes** `[ans_ticket_dashboard]`: painel público para usuários WP com `ans_answer_tickets`/`ans_manage_tickets` (login embutido). Lista cards de tickets (prioriza “sem atendente”), filtros salvos, respostas rápidas globais/departamento/pessoais + vinculadas a assunto/status (atalho “/”), auto-save de rascunho, respostas públicas ou notas internas, upload de anexos (até 5MB), transferência de departamento, barra de SLA e botão de “Assumir”. Histórico exibe anexos no fluxo e mensagens ordenadas por dia/autor (Beneficiário/Atendente/Nota interna).
- **Kanban** `[ans_ticket_kanban]`: colunas por status, filtros iguais ao dashboard, arrastar e soltar para alterar status, lazy load por coluna, contadores, persistência de filtros por usuário (user_meta).
- **Painel admin (wp-admin)**:
  - **Dashboard interno**: cards (total, tempo médio de resolução/1ª resposta) e gráficos de status, departamentos, assuntos, SLA por depto e top atendentes.
  - **Chamados (lista)**: WP_List_Table com busca (protocolo/cliente/documento), ordenação, paginação e exclusão individual ou em massa. Restrito a `manage_options`.
  - **Configurações**: número ANS (prefixo do protocolo), sequencial diário (definir próximo ou zerar todos), visualização de shortcodes, CRUD de departamentos e associação de usuários.
  - **Relatórios v2** (`/wp-admin/admin.php?page=ans-reports-v2`): primeira resposta por depto/atendente, SLA cumprido x estourado, volume por assunto, tickets por hora e heatmap semanal; Chart.js; export via copiar da API REST.
- **Departamentos**: CRUD com nome/slug, ordem de fluxo, cor, SLA (horas), ativo/inativo e vínculo de usuários. Exclusão pede transferência dos chamados abertos.
- **Protocolo**: `ANS + AAAAMMDD + sequencial diário (6 dígitos)`. Prefixo ANS vem das opções ou da tabela operadora; sequencial reinicia por dia, pode ser definido manualmente ou zerado via painel.
- **Permissões**: roles `ans_agent` (responde) e `ans_supervisor` (gestão). Administradores/editores recebem as capacidades na ativação.

## Instalação
1. Suba a pasta `ans-tickets` para `wp-content/plugins/` ou instale o zip.
2. Ative o plugin.
3. O instalador cria as páginas SAC e Controle de Chamados; use-as ou insira os shortcodes nas páginas desejadas.

## Shortcodes
- `[ans_ticket_form]` – Formulário de abertura.
- `[ans_ticket_track]` – Acompanhar/recuperar chamados.
- `[ans_ticket_dashboard]` – Dashboard para atendentes (login WP obrigatório).
- `[ans_ticket_kanban]` – Kanban de tickets (login WP + permissão de atendente).

## Configurações (wp-admin → ANS Tickets → Configurações)
- Número ANS (prefixo do protocolo).
- Sequencial diário: definir o próximo número do dia ou zerar todos os dias salvos.
- Departamentos: criar/editar/excluir, ordem/SLA/cor/ativo e associação de usuários para atendimento.
- Shortcodes destacados para copiar.
- Listas nativas WP para Departamentos, Assuntos e Status custom (menu ANS Tickets) com busca e ações de editar/ativar/excluir.

## Painel de chamados (wp-admin → ANS Tickets → Chamados)
- Tabela com busca (protocolo/cliente/documento), ordenação, paginação e exclusão (bulk). Apenas administradores.
- Dashboard interno com cards e gráficos simples de status, departamentos, assuntos, SLA médio e top atendentes.

## Dashboard atendentes (fora do wp-admin)
- Login via usuário/senha do WordPress; exige `ans_answer_tickets` (mesmo role de editor pode receber as caps).
- Filtros rápidos + chips de filtros ativos e salvos; suportam status, departamento, responsável, prioridade, protocolo e documento. Cards dos tickets exibem protocolo, cliente, assunto, status, prioridade e “Sem atendente” em destaque para assumir.
- Ações administrativas: alterar status, departamento, prioridade e responsável; transferir departamento; barra de SLA por ticket.
- Respostas rápidas (globais/depto/pessoais + vinculadas a assunto/status) com sugestões “/”; envio de resposta pública ou nota interna; prévia do texto; auto-save de rascunho.
- Upload de anexos do atendente (pdf/jpg/png/doc/docx, até 5MB) vinculado ao ticket e mostrado na linha do histórico.

## Fluxo do beneficiário
1. Abrir chamado: escolhe departamento, informa dados e recebe protocolo.
2. Acompanhar: protocolo + CPF/CNPJ → histórico completo com anexos na ordem e envio de mensagens (token válido por 1h).
3. Recuperar: CPF + data de nascimento → lista todos os chamados e abre cada um.

## Campos e regras
- **Ouvidoria**: campo “Protocolo anterior” obrigatório ao escolher assunto ouvidoria + aviso com prazos/contato.
- **Assistencial/Atendimento**: bloco adicional com tipo de procedimento, prestador, data do evento e número de guia.
- **Cliente Uniodonto**: ao marcar “Sim”, exige data de nascimento.
- **Prioridade**: padrão média; pode ser alterada no dashboard de atendentes.

## Status disponíveis
`aberto`, `em_triagem`, `aguardando_informacoes_solicitante`, `em_analise`, `em_execucao`, `aguardando_terceiros`, `aguardando_aprovacao`, `solucao_proposta`, `resolvido`, `fechado`, `aguardando_acao` (+ legados aceitos: `novo`, `atendimento`, `financeiro`, `comercial`, `assistencial`, `ouvidoria`, `concluido`, `arquivado`, `pendente_cliente`).

## REST (resumo)
- Público: `POST /tickets`, `POST /login`, `GET /tickets/{protocolo}`, `POST /tickets/{protocolo}/messages`, `POST /tickets/recover`, `GET /departamentos`, `GET /departamentos/{id}/assuntos`.
- Admin/atendente: `GET/POST /admin/departamentos`, `GET /admin/departamentos/{id}`, `PUT/DELETE /admin/departamentos/{id}`, `GET /admin/tickets`, `GET/PATCH /admin/tickets/{id}`, `POST /admin/tickets/{id}/reply`, `POST /admin/tickets/{id}/transfer`, `POST /admin/upload`, `GET/POST /admin/settings`, `GET /admin/stats`, `GET /admin/agents`, `GET/POST /admin/respostas-rapidas`, `PUT/DELETE /admin/respostas-rapidas/{id}`, `GET/POST /admin/filtros-salvos`, `PUT/DELETE /admin/filtros-salvos/{id}`, `GET/POST /admin/kanban/filters`, `GET /admin/kanban/tickets`, `GET /admin/reports/v2`, `GET/POST /admin/assuntos`, `PUT/DELETE /admin/assuntos/{id}`, `GET/POST /admin/status-custom` (com flags inicial/final resolvido/final não resolvido), `PUT/DELETE /admin/status-custom/{id}`.
- Autenticação: beneficiário via token emitido em `/login`; atendente via login WP + nonce para rotas admin.

## Banco de dados
Tabelas personalizadas: `ans_operadora`, `ans_departamentos`, `ans_clientes` (whatsapp, data_nascimento, cliente_uniodonto), `ans_tickets` (prioridade, departamento_id, responsavel_id, ticket_origem, campos assistenciais), `ans_interacoes`, `ans_anexos`, `ans_departamento_users`, `ans_respostas_rapidas` (+ vinculações em `ans_respostas_rapidas_links`), `ans_filtros_salvos`, `ans_assuntos`, `ans_status_custom`. Migrações: `inc/class-installer.php`.

## Estilo e design system
- Paleta Uniodonto/Collos (vinho, roxos, ciano, lima, goiaba). Tokens em `assets/design-system.md`.
- Layout Monday-like: cards, badges de status, grids responsivos, filtros com chips, sombras suaves.

## Permissões e segurança
- Roles `ans_agent` e `ans_supervisor`; admins/editores recebem caps na ativação.
- Dashboard público exige login WP e `ans_answer_tickets`.
- Tokens de cliente expiram em 1h. Upload restrito a mimes permitidos e limite de 5MB.
- Cron de SLA a cada 5 minutos: muda status para `aguardando_acao`, cria nota interna e notifica responsável.

## Versão
- Atual: **0.7.7**
