(function(){
  const api = ANS_TICKETS_ADMIN.api.replace(/\/$/, '');
  const nonce = ANS_TICKETS_ADMIN.nonce;
  const headers = {'X-WP-Nonce': nonce, 'Content-Type':'application/json'};

  const listEl = document.getElementById('ticket-list');
  const detailEl = document.getElementById('ticket-detail');
  const statusSelect = document.getElementById('filter-status');
  const protoInput = document.getElementById('filter-protocolo');
  const docInput = document.getElementById('filter-documento');
  const filtersEl = document.querySelector('.ans-dash-filters');
  let activeChipsEl, savedChipsEl, saveBtn;
  const SAVED_KEY = 'ans_saved_filters';
  const STATUS_OPTIONS = [
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
    // legados
    'novo',
    'atendimento',
    'pendente_cliente',
    'concluido',
    'arquivado',
    'financeiro',
    'comercial',
    'assistencial',
    'ouvidoria'
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

  const statusLabel = (s)=>STATUS_LABELS[s]||s;

  async function fetchJSON(url, opts={}){
    const res = await fetch(url, opts);
    const json = await res.json();
    if(!res.ok) throw new Error(json.error||'Erro');
    return json;
  }

  function loadSavedFilters(){
    try{
      const raw = localStorage.getItem(SAVED_KEY);
      return raw ? JSON.parse(raw) : [];
    }catch(e){
      return [];
    }
  }

  function storeSavedFilters(filters){
    localStorage.setItem(SAVED_KEY, JSON.stringify(filters));
  }

  function currentFilters(){
    const filters={};
    if(statusSelect.value) filters.status=statusSelect.value;
    if(protoInput.value) filters.protocolo=protoInput.value;
    if(docInput.value) filters.documento=docInput.value;
    return filters;
  }

  function applyFiltersFromObj(obj){
    statusSelect.value = obj.status||'';
    protoInput.value = obj.protocolo||'';
    docInput.value = obj.documento||'';
  }

  async function loadTickets(){
    const params = new URLSearchParams();
    if(statusSelect.value) params.set('status', statusSelect.value);
    if(protoInput.value) params.set('protocolo', protoInput.value);
    if(docInput.value) params.set('documento', docInput.value);
    const data = await fetchJSON(`${api}/admin/tickets?${params.toString()}`,{headers});
    renderList(data);
    renderActiveChips();
  }

  function renderList(items){
    listEl.innerHTML='';
    items.forEach(t=>{
      const li=document.createElement('li');
      const statusCls = (t.status||'').toLowerCase().replace(/[^a-z0-9_]/g,'-');
      li.innerHTML=`<div class="proto">${t.protocolo}</div><div>${t.nome_completo||''}</div><div class="meta">${t.assunto} • <span class="ans-badge ans-status ans-status-${statusCls}">${statusLabel(t.status)}</span> • ${t.departamento_nome||''}</div>`;
      li.addEventListener('click', ()=>loadTicket(t.id));
      listEl.appendChild(li);
    });
  }

  async function loadTicket(id){
    const t = await fetchJSON(`${api}/admin/tickets/${id}`,{headers});
    renderDetail(t);
  }

  function renderDetail(t){
    const statusCls = (t.status||'').toLowerCase().replace(/[^a-z0-9_]/g,'-');
    let html=`<div class="ans-detail-head"><div><h3>${t.protocolo}</h3><div class="meta">${t.departamento_nome||''}</div></div><div><span class="ans-badge ans-status ans-status-${statusCls}">${statusLabel(t.status)}</span></div></div>`;
    html+='<div class="ans-detail-grid">';
    html+=`<div class="ans-detail-card"><div class="meta">Cliente</div><div><strong>${t.nome_completo}</strong><br>${t.documento||''}</div></div>`;
    html+=`<div class="ans-detail-card"><div class="meta">Assunto</div><div>${t.assunto}</div></div>`;
    html+=`<div class="ans-detail-card"><div class="meta">Prioridade</div><div>${t.prioridade||'-'}</div></div>`;
    html+=`<div class="ans-detail-card"><div class="meta">Criado em</div><div>${t.created_at||'-'}</div></div>`;
    html+='</div>';
    html+=`<div class="ans-detail-card"><div class="meta">Descrição</div><div>${t.descricao}</div></div>`;
    html+='<h4>Histórico</h4><ul class="timeline">';
    (t.interacoes||[]).forEach(i=>{
      html+=`<li><div class="who">${i.autor_tipo==='cliente'?'Cliente':(i.usuario_nome||'Atendente')}</div><div>${i.mensagem}</div><div class="meta">${i.created_at}</div></li>`;
    });
    html+='</ul>';
    html+='<h4>Responder</h4>';
    html+='<div class="form-row"><textarea id="reply-msg"></textarea></div>';
    html+='<div class="form-row"><label><input type="checkbox" id="reply-interno"> Nota interna</label></div>';
    html+=`<div class="form-row"><select id="update-status"><option value="">Status</option>${STATUS_OPTIONS.map(s=>`<option value="${s}" ${s===t.status?'selected':''}>${statusLabel(s)}</option>`).join('')}</select><input id="update-dep" placeholder="Departamento ID" value="${t.departamento_id||''}"><select id="update-pri"><option value="">Prioridade</option><option value="baixa">baixa</option><option value="media" ${t.prioridade==='media'?'selected':''}>media</option><option value="alta">alta</option></select></div>`;
    html+='<div class="form-row"><input type="file" id="upload-file"><button class="btn btn-secondary" id="btn-upload">Anexar</button></div>';
    html+='<div class="form-row"><button class="btn btn-primary" id="btn-reply">Enviar resposta</button><button class="btn btn-secondary" id="btn-save">Salvar status/departamento</button></div>';
    detailEl.innerHTML=html;

    document.getElementById('btn-reply').onclick=()=>sendReply(t.id);
    document.getElementById('btn-save').onclick=()=>saveStatus(t.id);
    document.getElementById('btn-upload').onclick=(ev)=>{ev.preventDefault();uploadFile(t.id);};
  }

  async function sendReply(id){
    const msg = document.getElementById('reply-msg').value.trim();
    const interno = document.getElementById('reply-interno').checked;
    if(!msg){alert('Mensagem obrigatória');return;}
    await fetchJSON(`${api}/admin/tickets/${id}/reply`,{method:'POST',headers,body:JSON.stringify({mensagem:msg, interno})});
    await loadTicket(id);
  }

  async function saveStatus(id){
    const status = document.getElementById('update-status').value;
    const dep = document.getElementById('update-dep').value;
    const pri = document.getElementById('update-pri').value;
    const payload={};
    if(status) payload.status=status;
    if(dep) payload.departamento_id=parseInt(dep,10);
    if(pri) payload.prioridade=pri;
    if(!Object.keys(payload).length){alert('Nada para salvar');return;}
    await fetchJSON(`${api}/admin/tickets/${id}`,{method:'PATCH',headers,body:JSON.stringify(payload)});
    await loadTicket(id);
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
    alert('Anexo enviado');
    await loadTicket(ticketId);
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
    entries.forEach(([key,val])=>{
      const chip=document.createElement('span');
      chip.className='ans-chip';
      chip.textContent=`${key}: ${val}`;
      const close=document.createElement('button');
      close.className='ans-chip-remove';
      close.textContent='×';
      close.onclick=()=>{
        if(key==='status') statusSelect.value='';
        if(key==='protocolo') protoInput.value='';
        if(key==='documento') docInput.value='';
        loadTickets();
      };
      chip.appendChild(close);
      activeChipsEl.appendChild(chip);
    });
  }

  function renderSavedChips(){
    if(!savedChipsEl) return;
    savedChipsEl.innerHTML='';
    const saved=loadSavedFilters();
    if(!saved.length){
      savedChipsEl.innerHTML='<span class="ans-chip ghost">Nenhum filtro salvo</span>';
      return;
    }
    saved.forEach((item,idx)=>{
      const chip=document.createElement('span');
      chip.className='ans-chip secondary';
      chip.innerHTML=`${item.name}`;
      chip.onclick=()=>{
        applyFiltersFromObj(item.filters||{});
        loadTickets();
      };
      const close=document.createElement('button');
      close.className='ans-chip-remove';
      close.textContent='×';
      close.onclick=(ev)=>{
        ev.stopPropagation();
        const filtered=saved.filter((_,i)=>i!==idx);
        storeSavedFilters(filtered);
        renderSavedChips();
      };
      chip.appendChild(close);
      savedChipsEl.appendChild(chip);
    });
  }

  function setupFilterUI(){
    if(!filtersEl || saveBtn) return;
    activeChipsEl=document.createElement('div');
    activeChipsEl.className='ans-chip-row';
    savedChipsEl=document.createElement('div');
    savedChipsEl.className='ans-chip-row';
    saveBtn=document.createElement('button');
    saveBtn.type='button';
    saveBtn.className='btn btn-secondary';
    saveBtn.textContent='Salvar filtro';
    saveBtn.onclick=()=>{
      const filters=currentFilters();
      if(!Object.keys(filters).length){alert('Nenhum filtro para salvar');return;}
      const name=prompt('Nome do filtro');
      if(!name) return;
      const saved=loadSavedFilters();
      saved.push({name,filters});
      storeSavedFilters(saved);
      renderSavedChips();
    };
    filtersEl.appendChild(saveBtn);
    const activeLabel=document.createElement('div');
    activeLabel.className='ans-chip-label';
    activeLabel.textContent='Filtros ativos:';
    filtersEl.parentNode.insertBefore(activeLabel, filtersEl.nextSibling);
    filtersEl.parentNode.insertBefore(activeChipsEl, activeLabel.nextSibling);
    const savedLabel=document.createElement('div');
    savedLabel.className='ans-chip-label';
    savedLabel.textContent='Filtros salvos:';
    filtersEl.parentNode.insertBefore(savedLabel, activeChipsEl.nextSibling);
    filtersEl.parentNode.insertBefore(savedChipsEl, savedLabel.nextSibling);
    renderActiveChips();
    renderSavedChips();
  }

  document.getElementById('apply-filters').addEventListener('click',()=>loadTickets());
  setupFilterUI();
  loadTickets();
})();
