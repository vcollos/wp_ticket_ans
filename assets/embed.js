(function(){
  const apiBase = (window.ANS_TICKETS && window.ANS_TICKETS.api) || '';

  function serialize(form){
    const data = {};
    new FormData(form).forEach((v,k)=>{data[k]=v});
    return data;
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
        result.innerHTML = 'Protocolo: <strong>'+json.protocolo+'</strong>';
        result.style.display='block';
        form.reset();
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
      }catch(err){ alert(err.message); }
    });
  }

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
    ticketForm();
    trackForm();
    toggleAssistFields();
    const select = document.getElementById('ans-assunto');
    if (select) {
        select.addEventListener('change', toggleAssistFields);
    }
  });
})();
