(function(){
  const apiBase = (window.ANS_TICKETS && window.ANS_TICKETS.api) || '';

  function serialize(form){
    const data = {};
    new FormData(form).forEach((v,k)=>{data[k]=v});
    return data;
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
        result.innerHTML = '<div style="padding:15px;background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;color:#155724;">Protocolo: <strong>'+json.protocolo+'</strong><br>Seu chamado foi criado com sucesso!</div>';
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
          const ticketRes = await fetch(apiBase+'/tickets/'+payload.protocolo,{
            headers:{'Authorization':'Bearer '+token}
          });
          const ticket = await ticketRes.json();
          if(!ticketRes.ok){ throw new Error(ticket.error||'Erro ao carregar ticket'); }
          renderTicket(detail, ticket);
          detail.style.display='block';
        }else if(payload.documento && payload.data_nascimento){
          const recoverRes = await fetch(apiBase+'/tickets/recover',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
          });
          const recoverJson = await recoverRes.json();
          if(!recoverRes.ok){ throw new Error(recoverJson.error||'Erro ao recuperar chamados'); }
          renderRecoveredTickets(detail, recoverJson);
          detail.style.display='block';
        }else{
          throw new Error('Informe o protocolo ou CPF + Data de Nascimento');
        }
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
      html += '<ul style="list-style:none;padding:0;">';
      data.tickets.forEach(t=>{
        html += '<li style="padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px;">';
        html += '<strong>Protocolo:</strong> '+t.protocolo+'<br>';
        html += '<strong>Status:</strong> '+t.status+'<br>';
        html += '<strong>Assunto:</strong> '+t.assunto+'<br>';
        html += '<strong>Criado em:</strong> '+t.created_at+'<br>';
        html += '<button onclick="viewTicket(\''+t.protocolo+'\', \''+data.cliente.documento+'\')" style="margin-top:5px;">Ver Detalhes</button>';
        html += '</li>';
      });
      html += '</ul>';
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

  function renderTicket(el, ticket){
    let html = '<div><strong>Protocolo:</strong> '+ticket.protocolo+'</div>';
    html += '<div><strong>Status:</strong> '+ticket.status+'</div>';
    html += '<div><strong>Departamento:</strong> '+(ticket.departamento_nome||'')+'</div>';
    html += '<div><strong>Descrição:</strong><br>'+ticket.descricao+'</div>';
    html += '<h4>Histórico</h4>';
    html += '<ul>';
    (ticket.interacoes||[]).forEach(i=>{
      html += '<li><em>'+i.created_at+'</em> - '+(i.autor_tipo==='cliente'?'Cliente':'Atendente')+': '+i.mensagem+'</li>';
    });
    html += '</ul>';
    el.innerHTML = html;
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    loadDepartamentos();
    ticketForm();
    trackForm();
    recoverForm();
    toggleAssistFields();
    const select = document.getElementById('ans-assunto');
    if (select) {
        select.addEventListener('change', toggleAssistFields);
    }
  });
})();
