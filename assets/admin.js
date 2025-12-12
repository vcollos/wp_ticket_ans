(function(){
  const api = ANS_TICKETS_ADMIN.api.replace(/\/$/, '');
  const nonce = ANS_TICKETS_ADMIN.nonce;
  const headers = {'X-WP-Nonce': nonce, 'Content-Type':'application/json'};

  const listEl = document.getElementById('ticket-list');
  const detailEl = document.getElementById('ticket-detail');
  const statusSelect = document.getElementById('filter-status');
  const depFilter = document.getElementById('filter-departamento');
  const agentFilter = document.getElementById('filter-responsavel');
  const priFilter = document.getElementById('filter-prioridade');
  const protoInput = document.getElementById('filter-protocolo');
  const docInput = document.getElementById('filter-documento');
  const saveFiltersBtn = document.getElementById('save-filters');
  const activeChipsEl = document.getElementById('ans-active-chips');
  const savedChipsEl = document.getElementById('ans-saved-chips');
  const filtersEl = document.querySelector('.ans-dash-filters');
  let savedFiltersCache = [];
  let agentsCache = [];
  let depCache = [];
  let selectedTicketId = null;
  const QUICK_KEY = 'ans_quick_replies';
  const statsCards = document.getElementById('ans-stats-cards');
  const chartStatus = document.getElementById('ans-chart-status');
  const chartDept = document.getElementById('ans-chart-dept');
  const chartSubjects = document.getElementById('ans-chart-subjects');
  const chartSla = document.getElementById('ans-chart-sla');
  const chartAgents = document.getElementById('ans-chart-agents');
  const refreshBtn = document.getElementById('ans-refresh-btn');
  const tabBtns = document.querySelectorAll('.ans-tab-btn');
  const tabTable = document.getElementById('tab-table');
  const tabKanban = document.getElementById('tab-kanban');
  const BASE_STATUS = [
    'aberto',
    'em_triagem',
    'aguardando_informacoes_solicitante',
    'em_analise',
    'em_execucao',
    'aguardando_terceiros',
    'aguardando_aprovacao',
    'solucao_proposta',
    'resolvido',
    'fechado',
    'aguardando_acao',
    'novo',
    'atendimento',
    'financeiro',
    'comercial',
    'assistencial',
    'ouvidoria',
    'concluido',
    'arquivado',
    'pendente_cliente'
  ];
  const STATUS_LABELS = {
    aberto: 'Aberto',
    em_triagem: 'Em Triagem',
    aguardando_informacoes_solicitante: 'Aguardando Informações do Solicitante',
    em_analise: 'Em Análise',
    em_execucao: 'Em Atendimento / Execução',
    aguardando_terceiros: 'Aguardando Terceiros',
    aguardando_aprovacao: 'Aguardando Aprovação',
    solucao_proposta: 'Solução Proposta',
    resolvido: 'Resolvido',
    fechado: 'Fechado',
    aguardando_acao: 'Aguardando Ação',
    // legados
    novo: 'Aberto (legado)',
    atendimento: 'Em Atendimento (legado)',
    pendente_cliente: 'Aguardando Cliente (legado)',
    concluido: 'Concluído (legado)',
    arquivado: 'Arquivado (legado)',
    financeiro: 'Financeiro',
    comercial: 'Comercial',
    assistencial: 'Assistencial',
    ouvidoria: 'Ouvidoria'
  };
  let statusOptions = BASE_STATUS.map(slug=>({slug,name:statusLabel(slug)}));
  let autoRefresh = null;

  const statusLabel = (s)=>STATUS_LABELS[s]||labelFromSlug(s);
  function labelFromSlug(s){ return (s||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); }
  const formatDate = (val)=>{
    if(!val) return '-';
    const date = new Date(val.replace(' ','T'));
    if(Number.isNaN(date.getTime())) return val;
    return date.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
  };
  const priorityLabel = (p)=>{
    if(!p) return 'Sem prioridade';
    if(p==='alta') return 'Alta';
    if(p==='baixa') return 'Baixa';
    return 'Média';
  };
  function debounce(fn, wait){
    let t;
    return (...args)=>{
      clearTimeout(t);
      t = setTimeout(()=>fn.apply(null,args), wait);
    };
  }
  function announce(msg, type='success'){
    const host = document.getElementById('ans-feedback');
    if(!host) return;
    host.innerHTML = `<div class="ans-banner ${type}">${msg}</div>`;
    setTimeout(()=>{ if(host){ host.innerHTML=''; } }, 3200);
  }

  async function fetchJSON(url, opts={}){
    const res = await fetch(url, opts);
    const json = await res.json();
    if(!res.ok) throw new Error(json.error||'Erro');
    return json;
  }

  async function getQuickReplies(departamentoId){
    try{
      const qs = departamentoId ? `?departamento_id=${departamentoId}` : '';
      return await fetchJSON(`${api}/admin/respostas-rapidas${qs}`,{headers});
    }catch(e){
      return {globais:[],departamento:[],pessoais:[]};
    }
  }

  async function loadSavedFilters(){
    try{
      savedFiltersCache = await fetchJSON(`${api}/admin/filtros-salvos`,{headers});
    }catch(e){
      savedFiltersCache = [];
    }
  }

  function currentFilters(){
    const filters={};
    if(statusSelect && statusSelect.value) filters.status=statusSelect.value;
    if(depFilter && depFilter.value) filters.departamento_id=parseInt(depFilter.value,10);
    if(agentFilter && agentFilter.value) filters.responsavel_id=parseInt(agentFilter.value,10);
    if(priFilter && priFilter.value) filters.prioridade=priFilter.value;
    if(protoInput && protoInput.value) filters.protocolo=protoInput.value;
    if(docInput && docInput.value) filters.documento=docInput.value;
    return filters;
  }

  function applyFiltersFromObj(obj){
    if(statusSelect) statusSelect.value = obj.status||'';
    if(depFilter) depFilter.value = obj.departamento_id||'';
    if(agentFilter) agentFilter.value = obj.responsavel_id||'';
    if(priFilter) priFilter.value = obj.prioridade||'';
    if(protoInput) protoInput.value = obj.protocolo||'';
    if(docInput) docInput.value = obj.documento||'';
  }

  async function ensureStatuses(departamentoId=null){
    try{
      const qs = departamentoId ? `?departamento_id=${departamentoId}` : '';
      const custom = await fetchJSON(`${api}/admin/status-custom${qs}`,{headers});
      const mapped = (custom||[]).map(s=>({slug:s.slug,name:s.nome||statusLabel(s.slug)}));
      const baseSet = new Set(BASE_STATUS);
      const merged = [
        ...BASE_STATUS.map(slug=>({slug,name:statusLabel(slug)})),
        ...mapped.filter(m=>!baseSet.has(m.slug))
      ];
      statusOptions = merged;
      renderStatusSelects();
    }catch(e){
      statusOptions = BASE_STATUS.map(slug=>({slug,name:statusLabel(slug)}));
      renderStatusSelects();
    }
  }

  function renderStatusSelects(){
    if(statusSelect){
      statusSelect.innerHTML = '<option value=\"\">Todos</option>' + statusOptions.map(s=>`<option value=\"${s.slug}\">${s.name}</option>`).join('');
    }
    const updateStatus = document.getElementById('update-status');
    if(updateStatus){
      updateStatus.innerHTML = '<option value=\"\">Status</option>' + statusOptions.map(s=>`<option value=\"${s.slug}\">${s.name}</option>`).join('');
    }
  }

  async function loadTickets(){
    if(!listEl) return;
    const params = new URLSearchParams();
    if(statusSelect && statusSelect.value) params.set('status', statusSelect.value);
    if(depFilter && depFilter.value) params.set('departamento_id', depFilter.value);
    if(agentFilter && agentFilter.value) params.set('responsavel_id', agentFilter.value);
    if(priFilter && priFilter.value) params.set('prioridade', priFilter.value);
    if(protoInput.value) params.set('protocolo', protoInput.value);
    if(docInput.value) params.set('documento', docInput.value);
    try{
      const data = await fetchJSON(`${api}/admin/tickets?${params.toString()}`,{headers});
      renderList(data);
      renderActiveChips();
    }catch(e){
      if(listEl){ listEl.innerHTML = `<li class=\"empty-row\">Erro ao carregar chamados: ${e.message||''}</li>`; }
    }
  }

  function startAutoRefresh(){
    stopAutoRefresh();
    autoRefresh = setInterval(()=>{ loadTickets(); }, 30000);
  }
  function stopAutoRefresh(){
    if(autoRefresh){ clearInterval(autoRefresh); autoRefresh=null; }
  }

  function renderList(items){
    if(!listEl) return;
    listEl.innerHTML='';
    if(!items.length){
      listEl.innerHTML='<li class="empty-row">Nenhum chamado encontrado.</li>';
      return;
    }
    items.sort((a,b)=>{
      const aUn = a.responsavel_id ? 0 : 1;
      const bUn = b.responsavel_id ? 0 : 1;
      if(aUn !== bUn) return bUn - aUn;
      return new Date(b.created_at) - new Date(a.created_at);
    });
    items.forEach(t=>{
      const li=document.createElement('li');
      li.dataset.id = t.id;
      li.className='ans-ticket-card';
      const statusCls = (t.status||'').toLowerCase().replace(/[^a-z0-9_]/g,'-');
      const priClass = t.prioridade==='alta'?'pri-alta':(t.prioridade==='baixa'?'pri-baixa':'pri-media');
      const unassigned = !t.responsavel_id;
      const respLabel = t.responsavel_nome || 'Sem atendente';
      const updatedText = formatDate(t.updated_at);
      const label = t.status_label || statusLabel(t.status);
      li.innerHTML=`
        <div class="card-top">
          <div class="proto-block">
            <div class="proto-code">#${t.protocolo}</div>
            <div class="proto-time">${formatDate(t.created_at)}</div>
          </div>
          <div class="status-block">
            <span class="ans-badge ans-status ans-status-${statusCls}">${label}</span>
            <span class="ans-priority-badge ${priClass}">${priorityLabel(t.prioridade)}</span>
          </div>
        </div>
        <div class="card-body">
          <div class="client"><strong>${t.nome_completo||''}</strong></div>
          <div class="subject">${t.assunto||''}</div>
          <div class="meta">${t.departamento_nome||'-'} • Atualizado ${updatedText}</div>
        </div>
        <div class="card-actions ${unassigned?'is-unassigned':''}">
          <div class="resp">${unassigned ? 'Sem atendente' : 'Responsável: '+respLabel}</div>
          ${unassigned ? '<button class="btn btn-primary btn-small btn-assume">Assumir</button>' : ''}
        </div>
      `;
      li.addEventListener('click', (ev)=>{
        if(ev.target && ev.target.classList.contains('btn-assume')){
          assumeTicket(t.id);
          ev.stopPropagation();
          return;
        }
        selectedTicketId = t.id;
        highlightList();
        loadTicket(t.id);
      });
      listEl.appendChild(li);
    });
    highlightList();
    const exists = items.find(it=>it.id===selectedTicketId);
    if(items.length && !exists){
      selectedTicketId = items[0].id;
      loadTicket(items[0].id);
    }
  }

  function highlightList(){
    if(!listEl) return;
    const nodes = listEl.querySelectorAll('li');
    nodes.forEach(li=>{
      const id = parseInt(li.dataset.id||0,10);
      if(selectedTicketId && id===selectedTicketId){
        li.classList.add('is-active');
      }else{
        li.classList.remove('is-active');
      }
    });
  }

  async function loadTicket(id){
    selectedTicketId = id;
    highlightList();
    if(!detailEl) return;
    const t = await fetchJSON(`${api}/admin/tickets/${id}`,{headers});
    await ensureStatuses(t.departamento_id||null);
    const replies = await getQuickReplies(t.departamento_id);
    renderDetail(t, replies);
  }

  function renderAttachmentsList(list){
    if(!list || !list.length){
      return '<p class="ans-muted">Sem anexos no momento.</p>';
    }
    return '<ul class="ans-attachment-list">'+list.map(a=>{
      const label = a.mime_type ? a.mime_type.split('/').pop() : 'Arquivo';
      const created = formatDate(a.created_at);
      const link = a.url ? `<a href="${a.url}" target="_blank" rel="noopener">Arquivo ${a.id||''}</a>` : `<span class="ans-muted">Arquivo ${a.id||''}</span>`;
      return `<li>${link}<span>${label}</span><span>${created}</span></li>`;
    }).join('')+'</ul>';
  }

  function renderBubble(i){
    const cls = i.interno ? 'chat-internal' : (i.autor_tipo==='cliente'?'chat-client':'chat-agent');
    const who = i.autor_tipo==='cliente'?'Cliente':(i.usuario_nome||'Atendente');
    const icon = i.interno ? '📝' : (i.autor_tipo==='cliente'?'👤':'🎧');
    const tag = i.interno ? '<span class="ans-tag note">Nota interna 🔒</span>' : (i.autor_tipo==='cliente'?'<span class="ans-tag client">Cliente</span>':'<span class="ans-tag agent">Atendente</span>');
    const safeMsg = i.mensagem || '';
    return `<div class="ans-chat-bubble ${cls}"><div class="ans-chat-top">${tag}<span class="bubble-author">${icon} ${who}</span><span class="bubble-date">${formatDate(i.created_at)}</span></div><div class="bubble-body">${safeMsg}</div></div>`;
  }

  function renderHistory(list, attachments){
    const orderedAttachments = [...(attachments||[])].sort((a,b)=>{
      const da = new Date((a.created_at||'').replace(' ','T'));
      const db = new Date((b.created_at||'').replace(' ','T'));
      return da - db;
    });
    if(!list || !list.length){
      return '<div class="ans-empty-history">Sem interações por aqui.</div>';
    }
    const map = {};
    orderedAttachments.forEach(a=>{
      const key = a.interacao_id || 'ticket';
      if(!map[key]) map[key]=[];
      map[key].push(a);
    });
    const byDay = {};
    list.forEach(item=>{
      const day = (item.created_at||'').split(' ')[0] || 'Sem data';
      if(!byDay[day]) byDay[day] = [];
      byDay[day].push(item);
    });
    const days = Object.keys(byDay).sort();
    let htmlDays = days.map(day=>{
      const items = byDay[day].map(i=>{
        const isInternal = String(i.interno) === '1' || i.interno === true;
        const isClient = (i.autor_tipo || '') === 'cliente';
        const authorName = i.usuario_nome || (isClient ? 'Beneficiário' : 'Atendente');
        const role = isInternal ? `Nota interna · ${authorName}` : (isClient ? `Beneficiário` : `Atendente · ${authorName}`);
        const roleClass = isInternal ? 'pill-note' : (isClient ? 'pill-client' : 'pill-agent');
        const attach = map[i.id]||[];
        const attachHtml = attach.length ? `<div class="ans-history-attach">${attach.map(a=>`<a href="${a.url}" target="_blank" rel="noopener">${a.mime_type||'Arquivo'}</a>`).join('<br>')}</div>` : '';
        return `
          <div class="ans-history-item">
            <div class="ans-history-top">
              <span class="pill ${roleClass}">${role}</span>
                <span class="hist-date">${formatDate(i.created_at)}</span>
              </div>
              <div class="ans-history-body">${i.mensagem||''}</div>
              ${attachHtml}
            </div>
          `;
        }).join('');
        return `
          <div class="ans-history-day">
            <div class="ans-history-date">${day}</div>
          ${items}
        </div>
      `;
    }).join('');
    const ticketAttach = map['ticket']||[];
    if(ticketAttach.length){
      htmlDays += `<div class="ans-history-day"><div class="ans-history-date">Anexos do ticket</div><div class="ans-history-item"><div class="ans-history-attach">${ticketAttach.map(a=>`<a href="${a.url}" target="_blank" rel="noopener">${a.mime_type||'Arquivo'}</a>`).join('<br>')}</div></div></div>`;
    }
    return htmlDays;
  }

  function renderDetail(t, quickData){
    const statusCls = (t.status||'').toLowerCase().replace(/[^a-z0-9_]/g,'-');
    const statusText = t.status_label || statusLabel(t.status);
    const priClass = t.prioridade==='alta'?'pri-alta':(t.prioridade==='baixa'?'pri-baixa':'pri-media');
    const blocks = [
      {label:'Globais', items: quickData?.globais||[]},
      {label:'Departamento', items: quickData?.departamento||[]},
      {label:'Pessoais', items: quickData?.pessoais||[]}
    ];
    const local = loadQuickReplies().map(text=>({titulo:`Local: ${text.slice(0,30)}`, conteudo:text}));
    if(local.length){ blocks.push({label:'Locais', items: local}); }
    const quickOptions = blocks.map(block=>{
      if(!block.items.length) return '';
      const opts = block.items.map(q=>`<option value="${encodeURIComponent(q.conteudo)}">${block.label}: ${q.titulo}</option>`).join('');
      return `<optgroup label="${block.label}">${opts}</optgroup>`;
    }).join('');
    const depName = t.departamento_nome || '-';
    const priText = priorityLabel(t.prioridade);
    const respName = t.responsavel_nome || 'Não atribuído';
    const history = renderHistory(t.interacoes||[], t.anexos||[]);
    const draftKey = `ans_draft_${t.id}`;
    let html=`
      <div id="ans-feedback" class="ans-feedback" aria-live="polite"></div>
      <div class="ans-ticket-bar">
        <div>
          <div class="ans-ticket-title">[#${t.protocolo}] ${t.assunto||'-'}</div>
          <div class="ans-ticket-meta">
            <span class="ans-badge ans-status ans-status-${statusCls}">${statusText}</span>
            <span class="ans-priority-badge ${priClass}">${priText}</span>
            <span class="ans-badge ghost">Depto: ${depName}</span>
            <span class="ans-badge ghost">Atualizado ${formatDate(t.updated_at)}</span>
          </div>
          <div class="ans-ticket-meta secondary">Cliente: <strong>${t.nome_completo||''}</strong> • Doc: ${t.documento||'-'} • Criado em ${formatDate(t.created_at)}</div>
        </div>
        <div class="ans-ticket-sla">
          <div class="ans-sla-text">SLA do depto (${t.departamento_sla_hours||'—'}h)</div>
          <div class="ans-sla-bar"><span class="sla-ok" id="sla-bar-fill"></span></div>
        </div>
      </div>
      <div class="ans-ticket-columns">
        <section class="ans-col col-main">
          <div class="ans-card-title">Histórico</div>
          <div class="ans-chat-timeline" id="ans-chat-timeline">${history}</div>
          <div class="ans-reply-block">
            <div class="ans-reply-head">Responder como <strong>${ANS_TICKETS_ADMIN.user||'Atendente'}</strong></div>
            <div class="ans-template-row"><select id="quick-template"><option value="">Inserir resposta rápida</option>${quickOptions}</select><button type="button" class="btn btn-secondary btn-small" id="btn-add-quick">+ Resposta rápida</button></div>
            <div class="ans-quick-hint">Dica: digite "/" no campo para sugerir respostas rápidas.</div>
            <div class="ans-reply-wrapper"><textarea id="reply-msg" placeholder="Digite sua resposta..." autocomplete="off"></textarea><div id="quick-suggest" class="ans-quick-suggest" style="display:none;"></div></div>
            <div class="ans-reply-preview"><span>Pré-visualização</span><div id="reply-preview" class="ans-preview-box">Nada digitado ainda.</div></div>
            <div class="ans-reply-actions"><button class="btn btn-success" id="btn-reply">Enviar ao cliente</button><button class="btn btn-warning" id="btn-reply-internal">Adicionar nota interna</button></div>
          </div>
          <div class="ans-card action-card">
            <h4>🎯 Ações administrativas</h4>
            <div class="action-grid">
              <label>🏷 Status<select id="update-status"><option value="">Status</option>${(statusOptions||[]).map(s=>`<option value="${s.slug}" ${s.slug===t.status?'selected':''}>${s.name}</option>`).join('')}</select></label>
              <label>🧭 Depto<select id="update-dep"><option value="">Departamento</option>${depCache.map(d=>`<option value="${d.id}" ${d.id===t.departamento_id?'selected':''}>${d.nome}</option>`).join('')}</select></label>
              <label>⚡ Prioridade<select id="update-pri"><option value="">Prioridade</option><option value="baixa" ${t.prioridade==='baixa'?'selected':''}>Baixa</option><option value="media" ${t.prioridade==='media'?'selected':''}>Média</option><option value="alta" ${t.prioridade==='alta'?'selected':''}>Alta</option></select></label>
              <label>👤 Responsável<select id="assign-agent"><option value="">Responsável</option>${agentsCache.map(a=>`<option value="${a.id}">${a.name}</option>`).join('')}</select></label>
            </div>
            <div class="ans-action-buttons">
              <button class="btn btn-primary" id="btn-save">Aplicar alterações</button>
              <button class="btn btn-info" id="btn-transfer">Transferir depto</button>
            </div>
          </div>
          <div class="ans-card action-card">
            <h4>📎 Anexos do atendente</h4>
            <div class="ans-upload-row">
              <input type="file" id="upload-file">
              <button class="btn btn-secondary" id="btn-upload">Anexar</button>
            </div>
            <p class="ans-quick-hint">Até 5MB (pdf, jpg, png, doc, docx).</p>
          </div>
        </section>
      </div>
    `;

    detailEl.innerHTML=html;

    document.getElementById('btn-reply').onclick=()=>sendReply(t.id,false);
    document.getElementById('btn-reply-internal').onclick=()=>sendReply(t.id,true);
    document.getElementById('btn-save').onclick=()=>saveCombined(t.id);
    document.getElementById('btn-upload').onclick=(ev)=>{ev.preventDefault();uploadFile(t.id);};
    document.getElementById('btn-transfer').onclick=(ev)=>{ev.preventDefault();transferTicket(t.id);};
    document.getElementById('btn-add-quick').onclick=()=>addQuickReply();
    if(t.responsavel_id){
      const sel=document.getElementById('assign-agent');
      if(sel) sel.value = t.responsavel_id;
    }
    const tmpl=document.getElementById('quick-template');
    const textarea=document.getElementById('reply-msg');
    const suggest=document.getElementById('quick-suggest');
    if(tmpl && textarea){ tmpl.onchange=()=>{ if(tmpl.value){ textarea.value=decodeURIComponent(tmpl.value); updatePreview(); saveDraft(); } }; }
    function updatePreview(){
      const preview=document.getElementById('reply-preview');
      if(preview && textarea){
        preview.textContent = textarea.value.trim() || 'Nada digitado ainda.';
      }
    }
    if(textarea){
      const stored = localStorage.getItem(draftKey);
      if(stored){ textarea.value = stored; }
      textarea.addEventListener('input', updatePreview);
      textarea.addEventListener('input', debounce(saveDraft, 800));
    }
    if(textarea && suggest){
      textarea.addEventListener('keyup',(ev)=>{
        if(ev.key === '/' || textarea.value.includes('/')){
          const list = (quickData?.pessoais||[]).concat(quickData?.departamento||[]).concat(quickData?.globais||[]).map(q=>q.conteudo).filter(q=>q.toLowerCase().includes(textarea.value.replace('/','').toLowerCase()));
          if(list.length){
            suggest.innerHTML = list.map(q=>`<div class="quick-item">${q}</div>`).join('');
            suggest.style.display='block';
            suggest.querySelectorAll('.quick-item').forEach(node=>{
              node.onclick=()=>{ textarea.value=node.textContent; suggest.style.display='none'; updatePreview(); };
            });
          }else{ suggest.style.display='none'; }
        }else{
          suggest.style.display='none';
        }
      });
    }
    updatePreview();
    const timeline=document.getElementById('ans-chat-timeline');
    if(timeline){ timeline.scrollTop = timeline.scrollHeight; }
    renderSlaBar(t);

    function saveDraft(){
      if(textarea){ localStorage.setItem(draftKey, textarea.value); }
    }
  }

  async function sendReply(id, interno=false, opts={}){
    const msgEl = document.getElementById('reply-msg');
    const msg = msgEl ? msgEl.value.trim() : '';
    if(!msg){alert('Mensagem obrigatória');return;}
    try{
      await fetchJSON(`${api}/admin/tickets/${id}/reply`,{method:'POST',headers,body:JSON.stringify({mensagem:msg, interno, assume: opts.assume ? 1 : 0})});
      localStorage.removeItem(`ans_draft_${id}`);
      await loadTicket(id);
      announce(interno ? 'Nota interna adicionada.' : 'Resposta enviada ao cliente.');
    }catch(e){
      const text = (e.message||'').toLowerCase();
      if(!interno && text.includes('respons')) {
        const assume = confirm('Este chamado tem outro responsável. Deseja assumir e responder? Cancelar enviará como nota interna.');
        if(assume){
          return sendReply(id,false,{assume:true});
        }
        return sendReply(id,true);
      }
      alert(e.message||'Erro ao enviar resposta');
    }
  }

  async function saveCombined(id){
    const status = document.getElementById('update-status').value;
    const dep = document.getElementById('update-dep').value;
    const pri = document.getElementById('update-pri').value;
    const agent = document.getElementById('assign-agent').value;
    const payload={};
    if(status) payload.status=status;
    if(dep) payload.departamento_id=parseInt(dep,10);
    if(pri) payload.prioridade=pri;
    if(agent) payload.responsavel_id=parseInt(agent,10);
    if(!Object.keys(payload).length){alert('Nada para salvar');return;}
    try{
      await fetchJSON(`${api}/admin/tickets/${id}`,{method:'PATCH',headers,body:JSON.stringify(payload)});
      await loadTicket(id);
      announce('Alterações aplicadas.');
    }catch(e){
      alert(e.message||'Erro ao salvar');
    }
  }

  async function uploadFile(ticketId){
    const fileInput=document.getElementById('upload-file');
    if(!fileInput.files.length){alert('Selecione um arquivo');return;}
    const fd=new FormData();
    fd.append('file', fileInput.files[0]);
    fd.append('ticket_id', ticketId);
    const res = await fetch(`${api}/admin/upload`,{method:'POST',headers:{'X-WP-Nonce':nonce},body:fd});
    const json = await res.json();
    if(!res.ok){alert(json.error||'Erro no upload');return;}
    await loadTicket(ticketId);
    announce('Anexo enviado.');
  }

  async function transferTicket(id){
    const dep = document.getElementById('update-dep').value;
    if(!dep){alert('Selecione o departamento destino');return;}
    try{
      await fetchJSON(`${api}/admin/tickets/${id}/transfer`,{method:'POST',headers,body:JSON.stringify({departamento_id:parseInt(dep,10)})});
      await loadTicket(id);
      announce('Chamado transferido.', 'info');
    }catch(e){
      alert(e.message||'Erro ao transferir');
    }
  }

  function renderSlaBar(t){
    const bar=document.getElementById('sla-bar-fill');
    if(!bar) return;
    const sla = parseInt(t.departamento_sla_hours||0,10);
    if(!sla){ bar.style.width='0'; bar.className='sla-ok'; return; }
    const created = new Date(t.created_at.replace(' ','T'));
    const now = new Date();
    const diffH = (now - created)/36e5;
    const pct = Math.min(100, (diffH/sla)*100);
    bar.style.width = pct+'%';
    bar.className = pct>=100 ? 'sla-bad' : (pct>=70 ? 'sla-warn' : 'sla-ok');
  }

  function renderActiveChips(){
    if(!activeChipsEl) return;
    activeChipsEl.innerHTML='';
    const filters = currentFilters();
    const entries = Object.entries(filters);
    if(!entries.length){
      activeChipsEl.innerHTML='<span class="ans-chip ghost">Nenhum filtro ativo</span>';
      return;
    }
    const labelMap={status:'Status',protocolo:'Protocolo',documento:'Documento',departamento_id:'Departamento',responsavel_id:'Responsável',prioridade:'Prioridade'};
    entries.forEach(([key,val])=>{
      const chip=document.createElement('span');
      chip.className='ans-chip';
      let display = val;
      if(key==='status') display = statusLabel(val);
      if(key==='departamento_id'){
        const found = depCache.find(d=>d.id===parseInt(val,10));
        display = found ? found.nome : val;
      }
      if(key==='responsavel_id'){
        const found = agentsCache.find(a=>a.id===parseInt(val,10));
        display = found ? found.name : val;
      }
      if(key==='prioridade') display = priorityLabel(val);
      chip.textContent=`${labelMap[key]||key}: ${display}`;
      const close=document.createElement('button');
      close.className='ans-chip-remove';
      close.textContent='×';
      close.onclick=()=>{
        if(key==='status') statusSelect.value='';
        if(key==='protocolo') protoInput.value='';
        if(key==='documento') docInput.value='';
        if(key==='departamento_id' && depFilter) depFilter.value='';
        if(key==='responsavel_id' && agentFilter) agentFilter.value='';
        if(key==='prioridade' && priFilter) priFilter.value='';
        loadTickets();
      };
      chip.appendChild(close);
      activeChipsEl.appendChild(chip);
    });
  }

  function renderSavedChips(){
    if(!savedChipsEl) return;
    savedChipsEl.innerHTML='';
    if(!savedFiltersCache.length){
      savedChipsEl.innerHTML='<span class="ans-chip ghost">Nenhum filtro salvo</span>';
      return;
    }
    savedFiltersCache.forEach((item)=>{
      const chip=document.createElement('span');
      chip.className='ans-chip secondary';
      chip.draggable = true;
      chip.innerHTML=`${item.nome}`;
      chip.onclick=()=>{
        applyFiltersFromObj(item.filtros||{});
        loadTickets();
      };
      chip.addEventListener('dragstart',(ev)=>ev.dataTransfer.setData('text/plain',item.id));
      chip.addEventListener('drop',(ev)=>ev.preventDefault());
      const close=document.createElement('button');
      close.className='ans-chip-remove';
      close.textContent='×';
      close.onclick=(ev)=>{
        ev.stopPropagation();
        deleteSavedFilter(item.id);
      };
      chip.appendChild(close);
      savedChipsEl.appendChild(chip);
    });
  }

  function setupFilterUI(){
    if(!filtersEl) return;
    if(saveFiltersBtn){
      saveFiltersBtn.onclick=()=>{
        const filters=currentFilters();
        if(!Object.keys(filters).length){alert('Nenhum filtro para salvar');return;}
        const name=prompt('Nome do filtro');
        if(!name) return;
        saveFilter(name, filters);
      };
    }
    renderActiveChips();
    renderSavedChips();
  }

  async function saveFilter(name, filters){
    try{
      const existing = savedFiltersCache.find(f=>f.nome===name);
      if(existing){
        await fetchJSON(`${api}/admin/filtros-salvos/${existing.id}`,{method:'PUT',headers,body:JSON.stringify({nome:name,filtros:filters})});
      }else{
        await fetchJSON(`${api}/admin/filtros-salvos`,{method:'POST',headers,body:JSON.stringify({nome:name,filtros:filters})});
      }
      await loadSavedFilters();
      renderSavedChips();
    }catch(e){
      alert(e.message||'Erro ao salvar filtro');
    }
  }

  async function deleteSavedFilter(id){
    try{
      await fetchJSON(`${api}/admin/filtros-salvos/${id}`,{method:'DELETE',headers});
      savedFiltersCache = savedFiltersCache.filter(f=>f.id!==id);
      renderSavedChips();
    }catch(e){
      alert(e.message||'Erro ao excluir filtro');
    }
  }

  async function saveLastFilter(){
    const filters=currentFilters();
    if(!Object.keys(filters).length) return;
    await saveFilter('Auto', filters);
  }

  function populateFilterSelects(){
    if(depFilter){
      depFilter.innerHTML = '<option value=\"\">Todos</option>' + depCache.map(d=>`<option value=\"${d.id}\">${d.nome}</option>`).join('');
    }
    if(agentFilter){
      agentFilter.innerHTML = '<option value=\"\">Todos</option>' + agentsCache.map(a=>`<option value=\"${a.id}\">${a.name}</option>`).join('');
    }
    renderStatusSelects();
  }

  const applyBtn = document.getElementById('apply-filters');
  if(applyBtn){ applyBtn.addEventListener('click',()=>{ saveLastFilter(); loadTickets(); }); }
  const filterInputs=[statusSelect,depFilter,agentFilter,priFilter,protoInput,docInput];
  filterInputs.forEach(el=>{
    if(el){
      el.addEventListener('keydown',(ev)=>{
        if(ev.key === 'Enter'){
          ev.preventDefault();
          loadTickets();
        }
      });
    }
  });
  if(listEl){
    Promise.all([
      fetchJSON(`${api}/admin/agents`,{headers}).then(res=>{agentsCache=res||[];}).catch(()=>{agentsCache=[];}),
      fetchJSON(`${api}/admin/departamentos`,{headers}).then(res=>{depCache=res||[];}).catch(()=>{depCache=[];}),
      loadSavedFilters(),
      ensureStatuses()
    ]).finally(()=>{
      setupFilterUI();
      populateFilterSelects();
      renderActiveChips();
      renderSavedChips();
      loadTickets();
      startAutoRefresh();
    });
  }

  if(refreshBtn){
    refreshBtn.addEventListener('click', ()=>{ loadTickets(); startAutoRefresh(); });
  }

  if(tabBtns.length){
    tabBtns.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        tabBtns.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        if(tab==='kanban'){
          if(tabTable) tabTable.style.display='none';
          if(tabKanban) tabKanban.style.display='block';
          stopAutoRefresh();
          if(window.ansKanbanReload){ window.ansKanbanReload(); }
        }else{
          if(tabTable) tabTable.style.display='grid';
          if(tabKanban) tabKanban.style.display='none';
          startAutoRefresh();
        }
      });
    });
  }

  // Admin dashboard (wp-admin) stats
  if(statsCards){
    loadStats();
  }

  async function loadStats(){
    try{
      const data = await fetchJSON(`${api}/admin/stats`,{headers});
      renderStats(data);
    }catch(e){
      console.error(e);
    }
  }

  function renderStats(data){
    if(statsCards){
      const statusTotal = (data.status_counts||[]).reduce((sum,i)=>sum+parseInt(i.total||0,10),0);
      statsCards.innerHTML = `
        <div class="ans-stat-card"><h4>Total</h4><div class="number">${statusTotal}</div></div>
        <div class="ans-stat-card"><h4>Resolução média (h)</h4><div class="number">${(data.avg_resolution_hours||0).toFixed(1)}</div></div>
        <div class="ans-stat-card"><h4>1ª resposta (h)</h4><div class="number">${(data.avg_first_response_hours||0).toFixed(1)}</div></div>
      `;
    }
    renderBarList(chartStatus, data.status_counts||[], 'status');
    renderBarList(chartDept, data.department_counts||[], 'nome');
    renderBarList(chartSubjects, data.subject_counts||[], 'assunto');
    renderBarList(chartSla, data.dept_resolution||[], 'nome', 'avg_hours', true);
    renderBarList(chartAgents, data.top_agents||[], 'display_name', 'total');
  }

  function renderBarList(el, arr, labelKey, valueKey='total', fixed=false){
    if(!el) return;
    if(!arr || !arr.length){ el.innerHTML='<p>Sem dados.</p>'; return; }
    const max = Math.max(...arr.map(a=>parseFloat(a[valueKey]||0)));
    el.innerHTML = '<div class="ans-bar-list">'+arr.map(item=>{
      const label = item[labelKey] || 'N/A';
      const val = parseFloat(item[valueKey]||0);
      const pct = max > 0 ? (val/max*100) : 0;
      const displayVal = fixed ? val.toFixed(1) : val;
      return `<div class="ans-bar-row"><div class="ans-bar-label">${label}</div><div class="ans-bar"><span style="width:${pct}%;"></span></div><div class="ans-bar-value">${displayVal}</div></div>`;
    }).join('')+'</div>';
  }

  function loadQuickReplies(){
    try{
      const raw = localStorage.getItem(QUICK_KEY);
      const arr = raw ? JSON.parse(raw) : [];
      return arr.length ? arr : [
        'Estamos analisando seu chamado.',
        'Precisamos de mais informações para prosseguir.',
        'Encaminhamos ao setor responsável.'
      ];
    }catch(e){ return []; }
  }

  function saveQuickReplies(list){
    localStorage.setItem(QUICK_KEY, JSON.stringify(list));
  }

  async function addQuickReply(){
    const val = prompt('Digite a resposta rápida:');
    if(!val) return;
    let escopo = prompt('Escopo (pessoal/departamento/global)', 'pessoal');
    if(!escopo) escopo='pessoal';
    escopo = escopo.toLowerCase();
    let departamento_id = null;
    if(escopo === 'departamento'){
      departamento_id = depFilter?.value || prompt('Informe o ID do departamento');
    }
    try{
      await fetchJSON(`${api}/admin/respostas-rapidas`,{
        method:'POST',
        headers,
        body:JSON.stringify({titulo: val.slice(0,80), conteudo: val, escopo, departamento_id})
      });
      alert('Resposta rápida salva.');
      if(selectedTicketId){ loadTicket(selectedTicketId); }
    }catch(e){
      alert(e.message||'Erro ao salvar resposta rápida');
    }
  }

  async function assumeTicket(id){
    try{
      const userId = ANS_TICKETS_ADMIN.user_id;
      if(!userId){ alert('Usuário inválido'); return; }
      await fetchJSON(`${api}/admin/tickets/${id}`,{
        method:'PATCH',
        headers,
        body: JSON.stringify({responsavel_id: parseInt(userId,10)})
      });
      announce('Chamado assumido.');
      await loadTickets();
      await loadTicket(id);
    }catch(e){
      alert(e.message||'Erro ao assumir');
    }
  }
})();
