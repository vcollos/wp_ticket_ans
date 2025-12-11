(function(){
  const api = (window.ANS_TICKETS_KANBAN && ANS_TICKETS_KANBAN.api) || '';
  const nonce = ANS_TICKETS_KANBAN?.nonce || '';
  const defaultStatuses = (ANS_TICKETS_KANBAN?.status || []).map(s=>({slug:s,name:labelFromSlug(s)}));
  let statuses = [...defaultStatuses];
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

  function slaPct(created, slaHours){
    if(!slaHours) return 0;
    const diffH = (Date.now() - new Date(created.replace(' ','T')).getTime())/36e5;
    return Math.min(100, (diffH/parseFloat(slaHours))*100);
  }

  function priorityClass(p){ return p==='alta'?'pri-alta':(p==='baixa'?'pri-baixa':'pri-media'); }

  function renderCard(item){
    const pct = slaPct(item.created_at, item.sla_hours||0);
    const card = document.createElement('article');
    card.className='kanban-card';
    card.draggable = true;
    card.dataset.id = item.id;
    card.dataset.status = item.status;
    card.innerHTML = `
      <div class="kanban-top">
        <div class="proto">#${item.protocolo}</div>
        <div class="priority ${priorityClass(item.prioridade)}">${item.prioridade||'média'}</div>
      </div>
      <div class="client">${item.nome_completo||''}</div>
      <div class="meta">${item.departamento_nome||'-'} • ${humanTime(item.created_at)}</div>
      <div class="sla"><span style="width:${pct}%"></span></div>
    `;
    card.addEventListener('dragstart', (ev)=>{
      ev.dataTransfer.setData('text/plain', String(item.id));
      ev.dataTransfer.effectAllowed='move';
      setTimeout(()=>card.classList.add('dragging'),0);
    });
    card.addEventListener('dragend', ()=>card.classList.remove('dragging'));
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
    }catch(err){ alert(err.message); }
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
      if(filters.dep){
        filters.dep.innerHTML='<option value=\"\">Departamento</option>'+deps.map(d=>`<option value=\"${d.id}\">${d.nome}</option>`).join('');
      }
    }catch(e){}
    try{
      const agents = await fetchJSON(`${api}/admin/agents`,{headers: headers(false)});
      if(filters.resp){
        filters.resp.innerHTML='<option value=\"\">Responsável</option>'+agents.map(a=>`<option value=\"${a.id}\">${a.name}</option>`).join('');
      }
    }catch(e){}
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
      statuses = arr.length ? arr : [...defaultStatuses];
    }catch(e){
      statuses = [...defaultStatuses];
    }
    updateStatusFilter();
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

  document.addEventListener('DOMContentLoaded', init);
})();
