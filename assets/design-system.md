# Design System – Collos/Uniodonto (Monday-inspired)

## Foundations
- **Cores (tokens)**
  - Primary: `primary-500 #7A003C`, `primary-400 #A10054`, `primary-300 #C63674`, `primary-200 #ECA7C6`, `primary-100 #F7DDEB`
  - Secondary: `secondary-500 #0047FF`, `secondary-400 #3C6AFF`, `secondary-300 #7E96FF`, `secondary-200 #C9D3FF`, `secondary-100 #EEF1FF`
  - Greys: `grey-900 #1C1C1C`, `grey-700 #4A4A4A`, `grey-500 #8B8B8B`, `grey-300 #D0D0D0`, `grey-100 #F5F5F5`
  - Feedback: Success `#3AC15B`, Warning `#FFB000`, Danger `#E84855`, Info `#1DA1F2`
- **Tipografia**: Inter — Display 32/26/22, Heading 18/16, Body 14/12; weights 400/500/600.
- **Espaçamentos**: base 8px (8/16/24). 
- **Grid & Layout**: cards e boards com padding 12–16px; grids responsivos `minmax(240px,1fr)`.
- **Elevation / Shadows**: `0 12px 30px rgba(26,39,68,0.06~0.08)` para cartões.
- **Radius**: 8px (botões/inputs), 10px (cards/contêineres), 24px (badges pílula).
- **Ícones**: estilo linear simples (Lucide/Feather) 16–20px.

## Components
- **Buttons**: variantes Primary, Secondary, Ghost, Danger, Neutral; estados default/hover (-5%)/pressed (-10%)/disabled (grey-300 + grey-500); padding 16x8, radius 8px.
- **Inputs**: altura 40px, radius 8px, borda `grey-300`, foco `primary-400`; variantes default/with-icon/error/disabled.
- **Select / Dropdown**: campo base de input, seta 16px; popover radius 8px, shadow leve, item 36px, hover `grey-100`, selecionado `primary-100` + borda esquerda `primary-500`.
- **Tags / Badges**: pílula radius 24px, padding 12px; cores por status/urgência/cooperativa.
- **Cards**: fundo branco, borda `grey-300/200`, shadow leve, radius 10px; header/body/footer opcionais.
- **Tables**: header 48px (Inter 12/600), linhas 44px (Inter 14/400), zebra opcional `grey-100`, hover `grey-100`, divisória vertical `grey-300`.
- **Nav / App Bar**: altura 64px, fundo branco, shadow `0 1px 2px rgba(0,0,0,0.05)`, logo+título+ações.
- **Modals**: radius 12px, padding 32px, header fixo, footer ações à direita.
- **Toasts**: canto superior direito, 4s, radius 8px, background de estado, texto branco 14px.

## Patterns
- **Formulários**: layout limpo, poucos campos obrigatórios, foco visível; suporte a upload.
- **Tabelas densas (Monday style)**: colunas de status/prioridade/SLA/última atualização com chips coloridos e hovers claros.
- **Painéis / Boards**: colunas configuráveis, linhas 44px, inline actions, badges de status.
- **Filtros**: chips removíveis, múltipla seleção, “Salvar filtro”.
- **Cards de status (pulses)**: pílula 24px, cor por status (novo/triagem/aguardando cliente/análise/execução/terceiros/aprovação/solução/resultado/fechado).

## Templates
- **Dashboard**: cards KPI (radius 10), gráfico simples (status, categoria, SLA), lista recente.
- **Board de cadastro**: tabela/board para tickets novos com edição inline.
- **Board de análises (operadoras)**: colunas de SLA, financeiro, ANS, responsáveis.
- **Workspace Uniodonto**: identidade roxo Uniodonto + azul Collos (co-branded).

## Brand
- **Identidade Collos**: azuis (secondary), tipografia Inter, foco em clareza.
- **Identidade Uniodonto**: roxos (primary), badges/status com variações primárias.
- **Uso combinado (co-branded)**: primário roxo para ações principais, azul Collos em ações secundárias e highlights; manter greys neutros do Monday.

## Notas técnicas (plugin)
- CSS público (`assets/embed.css`) e admin (`assets/admin.css`) usam tokens acima (cores, radius, sombras, badges) e estados de botão/inputs no padrão Monday adaptado.
- Estados de status mapeados em classes `.ans-status-*` para chips/badges (front e admin).
