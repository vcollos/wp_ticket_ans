(function(){
  const api = (window.ANS_TICKETS_KANBAN && ANS_TICKETS_KANBAN.api) || '';
  const nonce = ANS_TICKETS_KANBAN?.nonce || '';
  const currentUserId = parseInt(ANS_TICKETS_KANBAN?.user_id||0,10);
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

  function statusKey(s){
    return String(s||'').toLowerCase().replace(/-/g,'_');
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

  function stripHtml(html){
    const div = document.createElement('div');
    div.innerHTML = html || '';
    return div.textContent || '';
  }

  function openTextModal({title, value, okText='Salvar'}){
    return new Promise((resolve)=>{
      const backdrop = document.createElement('div');
      backdrop.className = 'kanban-modal-backdrop';
      const modal = document.createElement('div');
      modal.className = 'kanban-modal center';
      modal.innerHTML = `
        <div class="modal-head">
          <div>
            <div class="modal-title">${title||''}</div>
            <div class="modal-meta">Ctrl/⌘ + Enter para salvar</div>
          </div>
          <button type="button" class="ans-btn ghost" data-close>Fechar</button>
        </div>
        <label>Mensagem</label>
        <textarea class="modal-text" rows="8" style="width:100%;"></textarea>
        <div class="modal-actions">
          <button type="button" class="ans-btn ghost" data-cancel>Cancelar</button>
          <button type="button" class="ans-btn" data-ok>${okText}</button>
        </div>
      `;
      document.body.appendChild(backdrop);
      document.body.appendChild(modal);
      const textarea = modal.querySelector('.modal-text');
      textarea.value = value || '';
      textarea.focus();
      const cleanup = ()=>{ backdrop.remove(); modal.remove(); };
      const cancel = ()=>{ cleanup(); resolve(null); };
      backdrop.addEventListener('click', cancel);
      modal.querySelector('[data-close]').addEventListener('click', cancel);
      modal.querySelector('[data-cancel]').addEventListener('click', cancel);
      modal.querySelector('[data-ok]').addEventListener('click', ()=>{
        const next = textarea.value.trim();
        if(!next){ alert('Mensagem obrigatória'); return; }
        cleanup();
        resolve(next);
      });
      modal.addEventListener('keydown', (ev)=>{
        if(ev.key === 'Escape'){ cancel(); }
        if((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter'){
          modal.querySelector('[data-ok]').click();
        }
      });
    });
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
        body: JSON.stringify({
          status,
          // no modo global (sem depto selecionado), o backend remapeia para o grupo efetivo do ticket
          kanban_global: (!filters.dep || !filters.dep.value) ? 1 : 0,
        })
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

  function resetFilters(){
    if(filters.status) filters.status.value = '';
    if(filters.dep) filters.dep.value = '';
    if(filters.resp) filters.resp.value = '';
    if(filters.pri) filters.pri.value = '';
    if(filters.proto) filters.proto.value = '';
    if(filters.doc) filters.doc.value = '';
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
      const key = statusKey(status);
      const total = (data.counts||[]).reduce((sum, c)=>{
        if(statusKey(c.status) === key) return sum + parseInt(c.total||0,10);
        return sum;
      }, 0);
      countEl.textContent = total ? total : items.length;
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

      // Kanban só deve exibir status cadastrados no admin (por departamento).
      const byKey = new Map();
      (res||[]).forEach(r=>{
        const slug = r?.slug;
        if(!slug) return;
        const key = statusKey(slug);
        const existing = byKey.get(key);
        if(!existing){
          byKey.set(key, r);
          return;
        }
        // desempate: menor ordem, depois nome
        const ordA = parseInt(existing.ordem||0,10);
        const ordB = parseInt(r.ordem||0,10);
        if(ordB < ordA){
          byKey.set(key, r);
          return;
        }
        if(ordB === ordA && String(r.nome||'') < String(existing.nome||'')){
          byKey.set(key, r);
        }
      });

      statuses = Array.from(byKey.values())
        .map(r=>({slug:r.slug, name:r.nome||labelFromSlug(r.slug), cor:r.cor, ordem:r.ordem}))
        .sort((a,b)=> (parseInt(a.ordem||0,10) - parseInt(b.ordem||0,10)) || String(a.name).localeCompare(String(b.name)));
    }catch(e){
      statuses = [];
    }
    if(!statuses.length && board){
      board.innerHTML = '<div class="ans-ticket-card"><p>Nenhum grupo de status configurado para exibir no Kanban. Cadastre um grupo Global em Status custom (wp-admin) ou finalize o grupo do departamento.</p></div>';
      if(filters.status) filters.status.innerHTML = '<option value="">Status</option>';
      return;
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
      const isInternal = String(i.interno) === '1' || i.interno === true;
      const isClient = i.autor_tipo==='cliente';
      const isMine = (i.autor_tipo==='usuario') && parseInt(i.autor_id||0,10) === currentUserId;
      const isDeleted = !!i.deleted_at;
      const isEdited = !!i.edited_at;
      const authorName = i.usuario_nome || (isClient ? 'Beneficiário' : 'Atendente');
      const who = isInternal ? `Nota interna · ${authorName}` : (isClient ? 'Beneficiário' : `Atendente · ${authorName}`);
      const cls = isInternal ? 'internal' : (isClient?'client':'agent');
      const actions = isMine ? `<span class="chat-actions">
        ${!isDeleted ? `<button type="button" class="btn ghost" data-action="edit-interaction" data-id="${i.id}">Editar</button>` : ''}
        ${!isDeleted ? `<button type="button" class="btn warn" data-action="delete-interaction" data-id="${i.id}">Excluir</button>` : ''}
      </span>` : '';
      const auditParts = [];
      if(isEdited){
        const whoEdit = i.edited_by_nome || 'Atendente';
        auditParts.push(`Editada por ${whoEdit} em ${formatDate(i.edited_at)}`);
      }
      if(isDeleted){
        const whoDel = i.deleted_by_nome || 'Atendente';
        auditParts.push(`Excluída por ${whoDel} em ${formatDate(i.deleted_at)}`);
      }
      const audit = auditParts.length ? `<div class="chat-audit">${auditParts.join(' • ')}</div>` : '';
      const original = i.mensagem_original && i.mensagem_original !== i.mensagem
        ? `<details class="chat-original"><summary>Ver mensagem original</summary><div class="chat-original-body">${i.mensagem_original}</div></details>`
        : '';
      const attach = map[i.id]||[];
      const attachHtml = attach.length ? `<div class="attach-row">${attach.map(a=>`<a href="${a.url}" target="_blank" rel="noopener">${a.mime_type||'Arquivo'}</a>`).join('<br>')}</div>` : '';
      return `${header}<div class="chat-bubble ${cls} ${isDeleted?'is-deleted':''}">
        <div class="chat-head">
          <span>${who}</span>
          <span class="chat-right"><span>${formatDate(i.created_at)}</span>${actions}</span>
        </div>
        <div class="chat-body">${i.mensagem||''}</div>
        ${audit}
        ${original}
        ${attachHtml}
      </div>`;
    }).join('') + '</div>';
  }

  async function openDetail(id){
    currentTicketId = id;
    const host = ensureDetailHost();
    host.innerHTML = '<div class="detail-card">Carregando chamado...</div>';
    try{
      const t = await fetchJSON(`${api}/admin/tickets/${id}`,{headers: headers(false)});
      let deptStatuses = statuses;
      try{
        if(t.departamento_id){
          const list = await fetchJSON(`${api}/admin/status-custom?departamento_id=${t.departamento_id}`,{headers: headers(false)});
          deptStatuses = (list||[]).map(r=>({slug:r.slug, name:r.nome||labelFromSlug(r.slug), cor:r.cor}));
        }
      }catch(e){}
      const statusOptions = (deptStatuses||[]).map(s=>`<option value="${s.slug}" ${statusKey(s.slug)===statusKey(t.status)?'selected':''}>${s.name}</option>`).join('');
      const depOptions = depCache.map(d=>`<option value="${d.id}" ${d.id===t.departamento_id?'selected':''}>${d.nome}</option>`).join('');
      const agentOptions = ['<option value="">Sem responsável</option>'].concat(agentsCache.map(a=>`<option value="${a.id}" ${a.id===t.responsavel_id?'selected':''}>${a.name}</option>`)).join('');
      const priOptions = ['baixa','media','alta'].map(p=>`<option value="${p}" ${p===t.prioridade?'selected':''}>${p}</option>`).join('');
      const history = renderHistory(t.interacoes||[], t.anexos||[]);
	      const ticketOrigem = t.ticket_origem ? ` • Protocolo anterior: ${t.ticket_origem}` : '';
	      host.innerHTML = `
	        <div class="detail-card">
	          <div class="detail-head">
	            <div>
	              <div class="title">[#${t.protocolo}] ${t.assunto||''}</div>
	              <div class="meta">${t.nome_completo||''} • ${t.departamento_nome||'-'}${ticketOrigem} • Atualizado ${formatDate(t.updated_at)}</div>
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

      const interactionsById = new Map((t.interacoes||[]).map(i=>[String(i.id), i]));
      const timeline = host.querySelector('.timeline');
      if(timeline){
        timeline.onclick = async (ev)=>{
          const btn = ev.target?.closest?.('button[data-action][data-id]');
          if(!btn) return;
          ev.stopPropagation();
          const action = btn.dataset.action;
          const interId = btn.dataset.id;
          const inter = interactionsById.get(String(interId));
          if(!inter) return;
          if(action === 'edit-interaction'){
            const next = await openTextModal({title:'Editar mensagem', value: stripHtml(inter.mensagem||''), okText:'Salvar edição'});
            if(!next) return;
            try{
              await fetchJSON(`${api}/admin/interacoes/${interId}`,{method:'PUT',headers: headers(),body: JSON.stringify({mensagem: next})});
              await openDetail(id);
              reloadBoard();
            }catch(e){
              alert(e.message||'Erro ao editar');
            }
            return;
          }
          if(action === 'delete-interaction'){
            const ok = confirm('Excluir esta mensagem? Ela continuará aparecendo para auditoria na área do atendente, marcada como excluída.');
            if(!ok) return;
            try{
              await fetchJSON(`${api}/admin/interacoes/${interId}`,{method:'DELETE',headers: headers(false)});
              await openDetail(id);
              reloadBoard();
            }catch(e){
              alert(e.message||'Erro ao excluir');
            }
          }
        };
      }
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
    // Sempre iniciar com filtros zerados (não aplica o último estado salvo).
    resetFilters();
    await loadStatuses();
    if(!statuses.length) return;
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
        if(!statuses.length) return;
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
