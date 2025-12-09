(function(){
  const apiBase = (window.ANS_TICKETS && window.ANS_TICKETS.api) || '';
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
  const tokens = {};

  function serialize(form){
    const data = {};
    new FormData(form).forEach((v,k)=>{data[k]=v});
    return data;
  }

  function statusLabel(key){
    return STATUS_LABELS[key] || key || '';
  }

  function statusBadge(key){
    const cls = key ? key.toString().toLowerCase().replace(/[^a-z0-9_]/g,'-') : 'na';
    return '<span class="ans-badge ans-status ans-status-'+cls+'">'+statusLabel(key)+'</span>';
  }

  async function loadDepartamentos(){
    try{
      const res = await fetch(apiBase+'/departamentos');
      const depts = await res.json();
      const select = document.getElementById('ans-assunto');
      if(select){
        select.innerHTML = '<option value="">Selecione um assunto</option>';
        depts.forEach(d=>{
          const option = document.createElement('option');
          option.value = d.slug;
          option.textContent = d.nome;
          select.appendChild(option);
        });
      }
    }catch(err){
      console.error('Erro ao carregar departamentos:', err);
    }
  }

  function ticketForm(){
    const wrap = document.getElementById('ans-ticket-form');
    if(!wrap) return;
    const form = wrap.querySelector('form');
    const result = wrap.querySelector('.ans-ticket-result');
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      result.style.display='none';
      const payload = serialize(form);
      try{
        const res = await fetch(apiBase+'/tickets',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if(!res.ok){ throw new Error(json.error||'Erro ao enviar'); }
        result.innerHTML = '<div class="ans-alert success">Protocolo: <strong>'+json.protocolo+'</strong><br>Seu chamado foi criado com sucesso!</div>';
        result.style.display='block';
        form.reset();
        loadDepartamentos();
      }catch(err){ alert(err.message); }
    });
  }

  function toggleAssistFields(){
    const select = document.getElementById('ans-assunto');
    const block = document.querySelector('#ans-ticket-form .assist-block');
    if (!select || !block) return;
    const show = select.value === 'assistencial';
    block.style.display = show ? 'grid' : 'none';
  }

  async function fetchTicket(protocol, token){
    const ticketRes = await fetch(apiBase+'/tickets/'+protocol,{
      headers:{'Authorization':'Bearer '+token}
    });
    const ticket = await ticketRes.json();
    if(!ticketRes.ok){ throw new Error(ticket.error||'Erro ao carregar ticket'); }
    return ticket;
  }

  async function sendTicketMessage(protocol, message, container){
    const token = tokens[protocol];
    if(!token){ alert('Faça login no chamado para responder.'); return; }
    if(!message){ alert('Mensagem obrigatória'); return; }
    const res = await fetch(apiBase+'/tickets/'+protocol+'/messages',{
      method:'POST',
      headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},
      body: JSON.stringify({mensagem: message})
    });
    const json = await res.json();
    if(!res.ok){ throw new Error(json.error||'Erro ao enviar mensagem'); }
    const ticket = await fetchTicket(protocol, token);
    renderTicket(container, ticket, token);
  }

  function renderTicket(el, ticket, token){
    if(token){ tokens[ticket.protocolo] = token; }
    let html = '<div class="ans-ticket-head">';
    html += '<div><h4>Protocolo '+ticket.protocolo+'</h4>';
    html += '<div class="ans-ticket-meta">'+(ticket.departamento_nome||'')+' • '+(ticket.created_at||'')+'</div>';
    html += '</div><div>'+statusBadge(ticket.status)+'</div></div>';
    html += '<div class="ans-ticket-desc">'+ticket.descricao+'</div>';
    html += '<div class="ans-thread-wrap">';
    html += '<div class="ans-thread-title">Histórico</div>';
    html += '<ul class="ans-thread">';
    const interactions = ticket.interacoes||[];
    if(!interactions.length){
      html += '<li class="ans-thread-item"><div class="ans-thread-body">Sem interações ainda.</div></li>';
    }
    interactions.forEach(i=>{
      const whoClass = i.autor_tipo==='cliente' ? 'beneficiario' : 'atendente';
      const whoLabel = i.autor_tipo==='cliente' ? 'Beneficiário' : 'Atendente';
      html += '<li class="ans-thread-item">';
      html += '<div class="ans-thread-top"><span class="ans-badge ans-pill-'+whoClass+'">'+whoLabel+'</span><span class="ans-thread-date">'+i.created_at+'</span></div>';
      html += '<div class="ans-thread-body">'+i.mensagem+'</div>';
      html += '</li>';
    });
    html += '</ul></div>';
    html += '<div class="ans-reply-box">';
    html += '<label>Responder ao chamado</label>';
    html += '<textarea class="ans-reply-text" data-protocolo="'+ticket.protocolo+'" placeholder="Digite sua resposta"></textarea>';
    html += '<button type="button" class="ans-btn ans-send-reply" data-protocolo="'+ticket.protocolo+'">Enviar resposta</button>';
    html += '<p class="ans-reply-hint">Sua mensagem ficará visível para a equipe de atendimento.</p>';
    html += '</div>';
    el.innerHTML = html;
    el.style.display='block';
    const btn = el.querySelector('.ans-send-reply');
    const textarea = el.querySelector('.ans-reply-text');
    if(btn && textarea){
      btn.addEventListener('click', async ()=>{
        try{
          await sendTicketMessage(ticket.protocolo, textarea.value.trim(), el);
          textarea.value='';
        }catch(err){ alert(err.message); }
      });
    }
  }

  function trackForm(){
    const wrap = document.getElementById('ans-ticket-track');
    if(!wrap) return;
    const form = wrap.querySelector('.track-form');
    const detail = wrap.querySelector('.ans-ticket-details');
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      detail.style.display='none';
      const payload = serialize(form);
      try{
        if(payload.protocolo){
          const loginRes = await fetch(apiBase+'/login',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
          });
          const loginJson = await loginRes.json();
          if(!loginRes.ok){ throw new Error(loginJson.error||'Erro de login'); }
          const token = loginJson.token;
          tokens[payload.protocolo] = token;
          const ticket = await fetchTicket(payload.protocolo, token);
          renderTicket(detail, ticket, token);
        }else if(payload.documento && payload.data_nascimento){
          const recoverRes = await fetch(apiBase+'/tickets/recover',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
          });
          const recoverJson = await recoverRes.json();
          if(!recoverRes.ok){ throw new Error(recoverJson.error||'Erro ao recuperar chamados'); }
          renderRecoveredTickets(detail, recoverJson);
        }else{
          throw new Error('Informe o protocolo ou CPF + Data de Nascimento');
        }
        detail.style.display='block';
      }catch(err){ alert(err.message); }
    });
  }

  function recoverForm(){
    const wrap = document.getElementById('ans-ticket-recover');
    if(!wrap) return;
    const form = wrap.querySelector('.recover-form');
    const results = wrap.querySelector('.ans-ticket-recover-results');
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      results.style.display='none';
      const payload = serialize(form);
      try{
        const res = await fetch(apiBase+'/tickets/recover',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if(!res.ok){ throw new Error(json.error||'Erro ao recuperar chamados'); }
        renderRecoveredTickets(results, json);
        results.style.display='block';
      }catch(err){ alert(err.message); }
    });
  }

  function renderRecoveredTickets(el, data){
    let html = '<h4>Seus Chamados</h4>';
    if(data.tickets && data.tickets.length > 0){
      html += '<div class="ans-recovered-list">';
      data.tickets.forEach(t=>{
        html += '<div class="ans-recovered-card">';
        html += '<div class="ans-recovered-top"><span class="ans-proto">'+t.protocolo+'</span>'+statusBadge(t.status)+'</div>';
        html += '<div class="ans-recovered-meta">'+statusLabel(t.status)+'</div>';
        html += '<div class="ans-recovered-meta">'+(t.departamento_nome||'')+'</div>';
        html += '<button type="button" class="ans-btn ans-btn-ghost" onclick="viewTicket(\''+t.protocolo+'\', \''+data.cliente.documento+'\')">Abrir e responder</button>';
        html += '</div>';
      });
      html += '</div>';
    }else{
      html += '<p>Nenhum chamado encontrado.</p>';
    }
    el.innerHTML = html;
  }

  window.viewTicket = function(protocolo, documento){
    const form = document.querySelector('#ans-ticket-track .track-form');
    if(form){
      form.querySelector('[name="protocolo"]').value = protocolo;
      form.querySelector('[name="documento"]').value = documento;
      form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    }
  };

  function setupTabs(){
    const formCard = document.getElementById('ans-ticket-form');
    const trackCard = document.getElementById('ans-ticket-track');
    if(!formCard || !trackCard || formCard.dataset.tabsInitialized){
      return;
    }
    const recoverCard = document.getElementById('ans-ticket-recover');
    const parent = formCard.parentNode;
    const nav = document.createElement('div');
    nav.className = 'ans-tabs-nav';
    nav.innerHTML = '<button class="ans-tab-btn active" data-target="abrir">Abrir chamado</button><button class="ans-tab-btn" data-target="meus">Meus chamados</button>';
    parent.insertBefore(nav, formCard);

    const openGroup = [formCard];
    const trackGroup = [trackCard];
    if(recoverCard){ trackGroup.push(recoverCard); }

    openGroup.forEach(el=>{ el.classList.add('ans-tab-panel', 'active'); });
    trackGroup.forEach(el=>{ el.classList.add('ans-tab-panel'); el.style.display='none'; });

    function showTab(target){
      if(target==='abrir'){
        openGroup.forEach(el=>{ el.style.display=''; el.classList.add('active'); });
        trackGroup.forEach(el=>{ el.style.display='none'; el.classList.remove('active'); });
      }else{
        openGroup.forEach(el=>{ el.style.display='none'; el.classList.remove('active'); });
        trackGroup.forEach(el=>{ el.style.display=''; el.classList.add('active'); });
      }
    }

    nav.addEventListener('click', (ev)=>{
      const btn = ev.target.closest('.ans-tab-btn');
      if(!btn) return;
      nav.querySelectorAll('.ans-tab-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      showTab(btn.dataset.target);
    });

    formCard.dataset.tabsInitialized = '1';
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    loadDepartamentos();
    ticketForm();
    trackForm();
    recoverForm();
    toggleAssistFields();
    setupTabs();
    const select = document.getElementById('ans-assunto');
    if (select) {
        select.addEventListener('change', toggleAssistFields);
    }
  });
})();
