(function(){
  const api = ANS_TICKETS_ADMIN.api.replace(/\/$/, '');
  const nonce = ANS_TICKETS_ADMIN.nonce;
  const headers = {'X-WP-Nonce': nonce, 'Content-Type':'application/json'};

  const listEl = document.getElementById('ticket-list');
  const detailEl = document.getElementById('ticket-detail');
  const statusSelect = document.getElementById('filter-status');
  const protoInput = document.getElementById('filter-protocolo');
  const docInput = document.getElementById('filter-documento');

  async function fetchJSON(url, opts={}){
    const res = await fetch(url, opts);
    const json = await res.json();
    if(!res.ok) throw new Error(json.error||'Erro');
    return json;
  }

  async function loadTickets(){
    const params = new URLSearchParams();
    if(statusSelect.value) params.set('status', statusSelect.value);
    if(protoInput.value) params.set('protocolo', protoInput.value);
    if(docInput.value) params.set('documento', docInput.value);
    const data = await fetchJSON(`${api}/admin/tickets?${params.toString()}`,{headers});
    renderList(data);
  }

  function renderList(items){
    listEl.innerHTML='';
    items.forEach(t=>{
      const li=document.createElement('li');
      li.innerHTML=`<div class="proto">${t.protocolo}</div><div>${t.nome_completo||''}</div><div class="meta">${t.assunto} • ${t.status} • ${t.departamento_nome||''}</div>`;
      li.addEventListener('click', ()=>loadTicket(t.id));
      listEl.appendChild(li);
    });
  }

  async function loadTicket(id){
    const t = await fetchJSON(`${api}/admin/tickets/${id}`,{headers});
    renderDetail(t);
  }

  function renderDetail(t){
    const statusOptions=['novo','atendimento','financeiro','comercial','assistencial','ouvidoria','pendente_cliente','concluido','arquivado'];
    let html=`<div class="head"><h3>${t.protocolo}</h3><div><span class="badge">${t.status}</span> • ${t.departamento_nome||''}</div></div>`;
    html+=`<div><strong>Cliente:</strong> ${t.nome_completo} (${t.documento||''})</div>`;
    html+=`<div><strong>Assunto:</strong> ${t.assunto}</div>`;
    html+=`<div><strong>Descrição:</strong><br>${t.descricao}</div>`;
    html+='<h4>Histórico</h4><ul class="timeline">';
    (t.interacoes||[]).forEach(i=>{
      html+=`<li><div class="who">${i.autor_tipo==='cliente'?'Cliente':(i.usuario_nome||'Atendente')}</div><div>${i.mensagem}</div><div class="meta">${i.created_at}</div></li>`;
    });
    html+='</ul>';
    html+='<h4>Responder</h4>';
    html+='<div class="form-row"><textarea id="reply-msg"></textarea></div>';
    html+='<div class="form-row"><label><input type="checkbox" id="reply-interno"> Nota interna</label></div>';
    html+=`<div class="form-row"><select id="update-status"><option value="">Status</option>${statusOptions.map(s=>`<option value="${s}" ${s===t.status?'selected':''}>${s}</option>`).join('')}</select><input id="update-dep" placeholder="Departamento ID" value="${t.departamento_id||''}"><select id="update-pri"><option value="">Prioridade</option><option value="baixa">baixa</option><option value="media" ${t.prioridade==='media'?'selected':''}>media</option><option value="alta">alta</option></select></div>`;
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

  document.getElementById('apply-filters').addEventListener('click',()=>loadTickets());
  loadTickets();
})();
