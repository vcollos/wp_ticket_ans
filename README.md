# ANS Tickets (Uniodonto) – v0.4.1

Sistema de chamados inspirado em Monday/Material Design para WordPress. Front-end para beneficiários e painel (WP Admin ou página pública) para atendentes.

## Funcionalidades
- **Formulário público** `[ans_ticket_form]`: abre chamados com dados do beneficiário, assunto/departamento, descrição, campos extras de Ouvidoria (protocolo anterior) e Assistencial (tipo de procedimento etc.). Exibe protocolo gerado com prefixo ANS configurável.
- **Acompanhar/recuperar** `[ans_ticket_track]`: 
  - Com protocolo: login rápido (protocolo + CPF/CNPJ), ver detalhes, histórico e responder.
  - Sem protocolo: CPF + data de nascimento → lista todos os chamados e permite abrir cada um.
- **Dashboard para atendentes** `[ans_ticket_dashboard]`: painel fora do wp-admin, acessível por login (usuário WP com `ans_answer_tickets`/`ans_manage_tickets`).
- **Painel admin (wp-admin)**:
  - **Configurações**: número ANS (prefixo do protocolo), sequencial diário (definir/zerar), departamentos e designação de usuários, shortcodes destacados.
  - **Chamados (lista)**: tabela estilo posts com busca, ordenação, paginação e exclusão (individual ou em massa). Apenas administradores (`manage_options`).
  - **Dashboard interno**: cards de estatísticas e gestão de departamentos.
- **Departamentos**: CRUD com cores, ordem de fluxo, SLA, ativo/inativo e associação de usuários.
- **Permissões**:
  - `ans_answer_tickets` (atendente)
  - `ans_manage_tickets` (gestão)
  - Administradores/editores recebem acesso na ativação.
- **REST API** (`ans-tickets/v1`): criar/atualizar/listar tickets, interações, upload de anexos, login de cliente por protocolo/CPF, recuperar chamados por CPF+data nascimento, gestão de departamentos e configurações (apenas admins).
- **Protocolo**: ANS + data + sequencial diário; ANS configurável nas opções; sequencial pode ser zerado ou definido.
- **Ouvidoria**: ao escolher assunto “ouvidoria” no formulário, aparece bloco extra com “Protocolo anterior” e aviso informativo com prazos e contato.

## Instalação
1. Suba a pasta `ans-tickets` para `wp-content/plugins/` ou instale o zip.
2. Ative o plugin.
3. Verifique páginas criadas (SAC e Controle de Chamados) ou use shortcodes nas páginas desejadas.

## Shortcodes
- `[ans_ticket_form]` – Formulário de abertura.
- `[ans_ticket_track]` – Acompanhar/recuperar chamados.
- `[ans_ticket_dashboard]` – Dashboard para atendentes (requer login WP com permissão).

## Configurações (wp-admin → ANS Tickets → Configurações)
- Número ANS (prefixo do protocolo).
- Sequencial diário: definir próximo número ou zerar todos.
- Departamentos: criar/editar/excluir, definir ordem/SLA/cor/ativo e associar usuários.
- Shortcodes destacados para copiar.

## Painel de chamados (wp-admin → ANS Tickets → Chamados)
- Tabela com busca (protocolo/cliente/documento), ordenação, paginação e exclusão (bulk). Apenas administradores.

## Dashboard atendentes (fora do wp-admin)
- Acesso por `[ans_ticket_dashboard]`. Se não logado ou sem permissão, mostra login (usuário/senha WP). Depois exibe painel com filtros, lista e detalhe do chamado, respostas e upload de anexos (via API).

## Fluxo do beneficiário
1. Abrir chamado: preenche dados, escolhe departamento (carregado via API), envia e recebe protocolo.
2. Acompanhar: digita protocolo + CPF/CNPJ e vê histórico; pode responder.
3. Recuperar: CPF + data de nascimento → lista todos os chamados e permite abrir cada um.

## Campos especiais
- **Ouvidoria**: mostra bloco extra “Protocolo anterior (Ouvidoria)” e aviso de prazos/contato quando assunto = ouvidoria.
- **Assistencial**: campo “assist-block” existe, mas hoje só exibe em ouvidoria conforme regra atual.

## REST (resumo)
- Público: `POST /tickets`, `POST /login`, `GET /tickets/{protocolo}`, `POST /tickets/{protocolo}/messages`, `POST /tickets/recover`, `GET /departamentos`.
- Admin/atendente: `GET/POST /admin/departamentos`, `GET /admin/departamentos/{id}`, `PUT/DELETE /admin/departamentos/{id}`, `GET /admin/tickets`, `GET/PATCH /admin/tickets/{id}`, `POST /admin/tickets/{id}/reply`, `POST /admin/upload`, `GET/POST /admin/settings`.
- Autenticação: beneficiário via token retornado em `/login`; atendente via login WP + nonce para rotas admin.

## Banco de dados
Tabelas personalizadas: `ans_operadora`, `ans_departamentos`, `ans_clientes`, `ans_tickets`, `ans_interacoes`, `ans_anexos`, `ans_departamento_users`. Migradas em `inc/class-installer.php`.

## Estilo e design system
- Paleta Uniodonto: Vinho `#a60069` (primário), Vinho escuro `#810e56`, Roxo `#bf9cff`, Pêssego `#ff9fad`, Ciano `#60ebff`, Lima `#e1ff7b`, Goiaba `#ff637e`.
- Layout inspirado em Material/Monday: cards, badges de status, grids responsivos, abas para separar abrir/acompanhar, spacing/padding consistente, avisos contextuais.

## Permissões e segurança
- Ativação cria roles `ans_agent` e `ans_supervisor` com caps (`ans_answer_tickets`, `ans_manage_tickets`); admins/editores recebem caps.
- Dashboard público exige login WP e capacidade `ans_answer_tickets`.
- Upload restrito a mimes permitidos e limite de 5MB.

## Versão
- Atual: **0.4.1**
