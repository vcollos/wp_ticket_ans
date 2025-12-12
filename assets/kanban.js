(function(){
  const api = (window.ANS_TICKETS_KANBAN && ANS_TICKETS_KANBAN.api) || '';
  const nonce = ANS_TICKETS_KANBAN?.nonce || '';
  const baseFallback = ['novo','em_andamento','resolvido','nao_resolvido'].map(s=>({slug:s,name:labelFromSlug(s)}));
  const defaultStatuses = (ANS_TICKETS_KANBAN?.status || []).map(s=>({slug:s,name:labelFromSlug(s)}));
  let statuses = [];
  let agentsCache = [];
  let depCache = [];
  const board = document.getElementById('kanban-board');
  const filters = {
    status: document.getElementById('kanban-filter-status'),
    dep: document.getElementById('kanban-filter-dep'),
    resp: document.getElementById('kanban-filter-resp'),
    pri: document.getElementById('kanban-filter-pri'),
    proto: document.getElementById('kanban-filter-proto'),
    doc: document.getElementById('kanban-filter-doc'),
  };
  const state = {};

  function labelFromSlug(s){
    return (s||'').replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());
  }

  function headers(json=true){
    const h = {'X-WP-Nonce': nonce};
    if(json) h['Content-Type']='application/json';
    return h;
  }

  async function fetchJSON(url, opts={}){
    const res = await fetch(url, opts);
    const json = await res.json();
    if(!res.ok) throw new Error(json.error||'Erro');
    return json;
  }

  function humanTime(dateStr){
    if(!dateStr) return '-';
    const diffMs = Date.now() - new Date(dateStr.replace(' ','T')).getTime();
    const diffH = Math.floor(diffMs/36e5);
    if(diffH < 1) return 'agora';
    if(diffH < 24) return diffH+'h';
    const d = Math.floor(diffH/24);
    return d+'d';
  }
  function formatDate(val){
    if(!val) return '-';
    const date = new Date(val.replace(' ','T'));
    if(Number.isNaN(date.getTime())) return val;
    return date.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
  }

  function slaPct(created, slaHours){
    if(!slaHours) return 0;
    const diffH = (Date.now() - new Date(created.replace(' ','T')).getTime())/36e5;
    return Math.min(100, (diffH/parseFloat(slaHours))*100);
  }

  function priorityClass(p){ return p==='alta'?'pri-alta':(p==='baixa'?'pri-baixa':'pri-media'); }

  function renderCard(item){
    const pct = slaPct(item.created_at, item.sla_hours||0);
    const resp = item.responsavel_nome || 'Sem responsável';
    const card = document.createElement('article');
    card.className='kanban-card';
    card.draggable = true;
    card.dataset.id = item.id;
    card.dataset.status = item.status;
    const label = item.status_label || labelFromSlug(item.status);
    card.innerHTML = `
      <div class="kanban-top">
        <div class="proto">#${item.protocolo}</div>
        <div class="priority ${priorityClass(item.prioridade)}">${item.prioridade||'média'}</div>
      </div>
      <div class="client">${item.nome_completo||''}</div>
      <div class="meta">${item.departamento_nome||'-'} • ${humanTime(item.created_at)}</div>
      <div class="meta resp">${resp}</div>
      <div class="status-pill">${label}</div>
      <div class="sla"><span style="width:${pct}%"></span></div>
    `;
    card.addEventListener('dragstart', (ev)=>{
      ev.dataTransfer.setData('text/plain', String(item.id));
      ev.dataTransfer.effectAllowed='move';
      setTimeout(()=>card.classList.add('dragging'),0);
    });
    card.addEventListener('dragend', ()=>card.classList.remove('dragging'));
    card.addEventListener('click', ()=>openDetail(item.id));
    return card;
  }

  function renderColumns(){
    if(!board) return;
    board.innerHTML='';
    statuses.forEach(statusObj=>{
      const status = statusObj.slug;
      const col = document.createElement('section');
      col.className='kanban-col';
      col.dataset.status = status;
      col.innerHTML = `
        <header>
          <div class="title">${statusObj.name}</div>
          <div class="count" id="count-${status}">0</div>
        </header>
        <div class="kanban-list" data-status="${status}"></div>
        <button class="kanban-load" data-status="${status}">Carregar mais</button>
      `;
      const list = col.querySelector('.kanban-list');
      list.addEventListener('dragover',(ev)=>{
        ev.preventDefault();
        list.classList.add('drag-over');
      });
      list.addEventListener('dragleave',()=>list.classList.remove('drag-over'));
      list.addEventListener('drop',(ev)=>{
        ev.preventDefault();
        list.classList.remove('drag-over');
        const id = parseInt(ev.dataTransfer.getData('text/plain'),10);
        if(!id) return;
        updateStatus(id, status);
      });
      col.querySelector('.kanban-load').addEventListener('click', ()=>loadColumn(status,true));
      board.appendChild(col);
    });
  }

  async function updateStatus(id, status){
    try{
      await fetchJSON(`${api}/admin/tickets/${id}`,{
        method:'PATCH',
        headers: headers(),
        body: JSON.stringify({status})
      });
      reloadBoard();
    }catch(err){
      alert(err.message||'Erro ao atualizar status');
    }
  }

  function currentFilters(){
    return {
      departamento_id: filters.dep?.value || '',
      responsavel_id: filters.resp?.value || '',
      prioridade: filters.pri?.value || '',
      protocolo: filters.proto?.value || '',
      documento: filters.doc?.value || '',
    };
  }

  async function saveFilters(){
    try{
      await fetchJSON(`${api}/admin/kanban/filters`,{
        method:'POST',
        headers: headers(),
        body: JSON.stringify({filters: currentFilters()})
      });
    }catch(e){}
  }

  async function restoreFilters(){
    try{
      const saved = await fetchJSON(`${api}/admin/kanban/filters`,{headers: headers(false)});
      if(saved.departamento_id && filters.dep) filters.dep.value = saved.departamento_id;
      if(saved.responsavel_id && filters.resp) filters.resp.value = saved.responsavel_id;
      if(saved.prioridade && filters.pri) filters.pri.value = saved.prioridade;
      if(saved.protocolo && filters.proto) filters.proto.value = saved.protocolo;
      if(saved.documento && filters.doc) filters.doc.value = saved.documento;
    }catch(e){}
  }

  async function loadColumn(status, append=false){
    const list = board.querySelector(`.kanban-list[data-status="${status}"]`);
    if(!list) return;
    const offset = append ? (state[status]?.offset||0) + (state[status]?.items||0) : 0;
    const params = new URLSearchParams({status, offset, per_page:30});
    const f = currentFilters();
    Object.entries(f).forEach(([k,v])=>{ if(v) params.set(k,v); });
    const data = await fetchJSON(`${api}/admin/kanban/tickets?${params.toString()}`,{headers: headers(false)});
    const items = data.items||[];
    if(!append) list.innerHTML='';
    items.forEach(item=>list.appendChild(renderCard(item)));
    const countEl = document.getElementById(`count-${status}`);
    if(countEl && data.counts){
      const found = data.counts.find(c=>c.status===status);
      countEl.textContent = found ? found.total : items.length;
    }
    state[status] = {offset, items: items.length};
  }

  async function reloadBoard(){
    statuses.forEach(s=>loadColumn(s.slug,false));
  }

  async function populateSelects(){
    try{
      const deps = await fetchJSON(`${api}/admin/departamentos`,{headers: headers(false)});
      depCache = deps||[];
      if(filters.dep){
        filters.dep.innerHTML='<option value=\"\">Departamento</option>'+depCache.map(d=>`<option value=\"${d.id}\">${d.nome}</option>`).join('');
      }
    }catch(e){ depCache=[]; }
    try{
      const agents = await fetchJSON(`${api}/admin/agents`,{headers: headers(false)});
      agentsCache = agents||[];
      if(filters.resp){
        filters.resp.innerHTML='<option value=\"\">Responsável</option>'+agentsCache.map(a=>`<option value=\"${a.id}\">${a.name}</option>`).join('');
      }
    }catch(e){ agentsCache=[]; }
  }

  function updateStatusFilter(){
    if(!filters.status) return;
    filters.status.innerHTML = '<option value=\"\">Status</option>'+statuses.map(s=>`<option value=\"${s.slug}\">${s.name}</option>`).join('');
  }

  async function loadStatuses(){
    const depId = filters.dep?.value || '';
    try{
      const qs = depId ? `?departamento_id=${depId}` : '';
      const res = await fetchJSON(`${api}/admin/status-custom${qs}`,{headers: headers(false)});
      const arr = (res||[]).map(r=>({slug:r.slug,name:r.nome||labelFromSlug(r.slug), cor:r.cor}));
      if(arr.length){
        statuses = arr;
      }else{
        // tenta globais sem depto
        const global = await fetchJSON(`${api}/admin/status-custom`,{headers: headers(false)}).catch(()=>[]);
        const arrGlobal = (global||[]).map(r=>({slug:r.slug,name:r.nome||labelFromSlug(r.slug), cor:r.cor}));
        statuses = arrGlobal.length ? arrGlobal : (defaultStatuses.length ? defaultStatuses : baseFallback);
      }
    }catch(e){
      statuses = defaultStatuses.length ? defaultStatuses : baseFallback;
    }
    updateStatusFilter();
  }

  let detailHost = null;
  let currentTicketId = null;

  function ensureDetailHost(){
    if(detailHost) return detailHost;
    detailHost = document.createElement('div');
    detailHost.id = 'kanban-detail';
    const container = document.getElementById('ans-kanban');
    if(container){
      container.appendChild(detailHost);
    }else{
      document.body.appendChild(detailHost);
    }
    return detailHost;
  }

  function renderHistory(interacoes, anexos){
    if(!interacoes || !interacoes.length) return '<p class="muted">Sem histórico.</p>';
    const map = {};
    (anexos||[]).forEach(a=>{
      const key = a.interacao_id || 'ticket';
      if(!map[key]) map[key]=[];
      map[key].push(a);
    });
    let lastDate='';
    return '<div class="ans-chat-timeline">'+interacoes.map(i=>{
      const day = (i.created_at||'').split(' ')[0];
      let header = '';
      if(day && day!==lastDate){
        lastDate = day;
        header = `<div class="ans-date-sep">${day}</div>`;
      }
      const authorName = i.usuario_nome || (i.autor_tipo==='cliente' ? 'Beneficiário' : 'Atendente');
      const who = i.autor_tipo==='cliente' ? 'Beneficiário' : `Atendente · ${authorName}`;
      const cls = i.interno ? 'internal' : (i.autor_tipo==='cliente'?'client':'agent');
      const attach = map[i.id]||[];
      const attachHtml = attach.length ? `<div class="attach-row">${attach.map(a=>`<a href="${a.url}" target="_blank" rel="noopener">${a.mime_type||'Arquivo'}</a>`).join('<br>')}</div>` : '';
      return `${header}<div class="chat-bubble ${cls}"><div class="chat-head"><span>${who}</span><span>${formatDate(i.created_at)}</span></div><div class="chat-body">${i.mensagem||''}</div>${attachHtml}</div>`;
    }).join('') + '</div>';
  }

  async function openDetail(id){
    currentTicketId = id;
    const host = ensureDetailHost();
    host.innerHTML = '<div class="detail-card">Carregando chamado...</div>';
    try{
      const t = await fetchJSON(`${api}/admin/tickets/${id}`,{headers: headers(false)});
      const statusOptions = statuses.map(s=>`<option value="${s.slug}" ${s.slug===t.status?'selected':''}>${s.name}</option>`).join('');
      const depOptions = depCache.map(d=>`<option value="${d.id}" ${d.id===t.departamento_id?'selected':''}>${d.nome}</option>`).join('');
      const agentOptions = ['<option value="">Sem responsável</option>'].concat(agentsCache.map(a=>`<option value="${a.id}" ${a.id===t.responsavel_id?'selected':''}>${a.name}</option>`)).join('');
      const priOptions = ['baixa','media','alta'].map(p=>`<option value="${p}" ${p===t.prioridade?'selected':''}>${p}</option>`).join('');
      const history = renderHistory(t.interacoes||[], t.anexos||[]);
      host.innerHTML = `
        <div class="detail-card">
          <div class="detail-head">
            <div>
              <div class="title">[#${t.protocolo}] ${t.assunto||''}</div>
              <div class="meta">${t.nome_completo||''} • ${t.departamento_nome||'-'} • Atualizado ${formatDate(t.updated_at)}</div>
            </div>
            <button class="btn ghost" id="detail-close">Fechar</button>
          </div>
          <div class="detail-grid">
            <div class="left">
              <div class="card">
                <div class="card-title">Histórico</div>
                <div class="timeline">${history}</div>
              </div>
              <div class="card">
                <div class="card-title">Responder</div>
                <textarea id="detail-reply" placeholder="Digite sua resposta..."></textarea>
                <div class="actions-row">
                  <button class="btn success" id="detail-send">Enviar ao cliente</button>
                  <button class="btn warn" id="detail-note">Nota interna</button>
                </div>
              </div>
            </div>
            <div class="right">
              <div class="card">
                <div class="card-title">Ações</div>
                <label>Status<select id="detail-status">${statusOptions}</select></label>
                <label>Departamento<select id="detail-dep">${depOptions}</select></label>
                <label>Prioridade<select id="detail-pri">${priOptions}</select></label>
                <label>Responsável<select id="detail-resp">${agentOptions}</select></label>
                <div class="actions-row">
                  <button class="btn primary" id="detail-save">Aplicar</button>
                  <button class="btn info" id="detail-transfer">Transferir depto</button>
                </div>
              </div>
              <div class="card">
                <div class="card-title">Anexos do atendente</div>
                <input type="file" id="detail-upload">
                <button class="btn" id="detail-upload-btn">Anexar</button>
              </div>
            </div>
          </div>
        </div>
      `;
      host.querySelector('#detail-close').onclick = ()=>{ host.innerHTML=''; };
      host.querySelector('#detail-send').onclick = ()=>sendReply(t.id,false);
      host.querySelector('#detail-note').onclick = ()=>sendReply(t.id,true);
      host.querySelector('#detail-save').onclick = ()=>saveCombined(t.id);
      host.querySelector('#detail-transfer').onclick = ()=>transferTicket(t.id);
      host.querySelector('#detail-upload-btn').onclick = (ev)=>{ev.preventDefault();uploadFile(t.id);};
    }catch(e){
      host.innerHTML = `<div class="detail-card error">Erro ao carregar: ${e.message||''}</div>`;
    }
  }

  async function sendReply(id, interno=false, opts={}){
    const msgEl = detailHost?.querySelector('#detail-reply');
    if(!msgEl) return;
    const msg = msgEl.value.trim();
    if(!msg){ alert('Mensagem obrigatória'); return; }
    try{
      await fetchJSON(`${api}/admin/tickets/${id}/reply`,{method:'POST',headers: headers(),body: JSON.stringify({mensagem:msg, interno, assume: opts.assume ? 1 : 0})});
      msgEl.value='';
      await openDetail(id);
      reloadBoard();
    }catch(e){
      const text = (e.message||'').toLowerCase();
      if(!interno && text.includes('respons')){
        const assume = confirm('Este chamado tem outro responsável. Deseja assumir e responder? Cancelar enviará como nota interna.');
        if(assume){
          return sendReply(id,false,{assume:true});
        }
        return sendReply(id,true);
      }
      alert(e.message||'Erro ao responder');
    }
  }

  async function saveCombined(id){
    const status = detailHost?.querySelector('#detail-status')?.value;
    const dep = detailHost?.querySelector('#detail-dep')?.value;
    const pri = detailHost?.querySelector('#detail-pri')?.value;
    const resp = detailHost?.querySelector('#detail-resp')?.value;
    const payload={};
    if(status) payload.status=status;
    if(dep) payload.departamento_id=parseInt(dep,10);
    if(pri) payload.prioridade=pri;
    if(resp) payload.responsavel_id=parseInt(resp,10);
    if(!Object.keys(payload).length){ alert('Nada para salvar'); return; }
    try{
      await fetchJSON(`${api}/admin/tickets/${id}`,{method:'PATCH',headers: headers(),body: JSON.stringify(payload)});
      await openDetail(id);
      reloadBoard();
    }catch(e){ alert(e.message||'Erro ao salvar'); }
  }

  async function transferTicket(id){
    const dep = detailHost?.querySelector('#detail-dep')?.value;
    if(!dep){ alert('Selecione o departamento destino'); return; }
    try{
      await fetchJSON(`${api}/admin/tickets/${id}/transfer`,{method:'POST',headers: headers(),body: JSON.stringify({departamento_id:parseInt(dep,10)})});
      await openDetail(id);
      reloadBoard();
    }catch(e){ alert(e.message||'Erro ao transferir'); }
  }

  async function uploadFile(id){
    const input = detailHost?.querySelector('#detail-upload');
    if(!input || !input.files.length){ alert('Selecione um arquivo'); return; }
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('ticket_id', id);
    const res = await fetch(`${api}/admin/upload`,{method:'POST',headers:{'X-WP-Nonce':nonce},body:fd});
    const json = await res.json();
    if(!res.ok){ alert(json.error||'Erro no upload'); return; }
    await openDetail(id);
  }

  async function init(){
    if(!board) return;
    await populateSelects();
    await restoreFilters();
    await loadStatuses();
    renderColumns();
    updateStatusFilter();
    await reloadBoard();
    const apply = document.getElementById('kanban-apply');
    if(apply){
      apply.addEventListener('click', ()=>{
        saveFilters();
        reloadBoard();
      });
    }
    if(filters.dep){
      filters.dep.addEventListener('change', async ()=>{
        await loadStatuses();
        renderColumns();
        updateStatusFilter();
        reloadBoard();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    init();
    window.ansKanbanReload = reloadBoard;
  });
})();
