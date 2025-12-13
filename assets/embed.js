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
    aguardando_acao: 'Aguardando Ação',
    // legados (sem sufixo legado)
    novo: 'Aberto',
    atendimento: 'Em Atendimento',
    pendente_cliente: 'Aguardando Cliente',
    concluido: 'Concluído',
    arquivado: 'Arquivado',
    financeiro: 'Financeiro',
    comercial: 'Comercial',
    assistencial: 'Assistencial',
    ouvidoria: 'Ouvidoria'
  };
  const tokens = {};

  function maskPhone(input, mobile){
    const digits = (input.value || '').replace(/\D/g,'').slice(0, mobile ? 11 : 10);
    if(!digits){ input.value=''; return; }
    if(mobile){
      // (99) 9 9999-9999
      const d = digits.padEnd(11,' ');
      input.value = `(${d.slice(0,2)}) ${d.slice(2,3)} ${d.slice(3,7)}-${d.slice(7,11)}`.trim();
    }else{
      // (99) 9999-9999
      const d = digits.padEnd(10,' ');
      input.value = `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6,10)}`.trim();
    }
  }

  function serialize(form){
    const data = {};
    new FormData(form).forEach((v,k)=>{data[k]=v});
    return data;
  }

  function statusLabel(key, custom){
    if(custom) return custom;
    return STATUS_LABELS[key] || labelFromSlug(key);
  }
  function labelFromSlug(slug){
    if(!slug) return '';
    return slug.toString().replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
  }

  function statusBadge(key, custom){
    const cls = key ? key.toString().toLowerCase().replace(/[^a-z0-9_]/g,'-') : 'na';
    return '<span class="ans-badge ans-status ans-status-'+cls+'">'+statusLabel(key, custom)+'</span>';
  }

  async function loadDepartamentos(){
    try{
      const res = await fetch(apiBase+'/departamentos');
      const depts = await res.json();
      const depSelect = document.getElementById('ans-departamento');
      if(depSelect){
        depSelect.innerHTML = '<option value="">Selecione um departamento</option>' + depts.map(d=>`<option value="${d.id}" data-slug="${d.slug}">${d.nome}</option>`).join('');
      }
    }catch(err){
      console.error('Erro ao carregar departamentos:', err);
    }
  }

  async function loadAssuntosByDep(depId){
    const assuntoSelect = document.getElementById('ans-assunto');
    if(!assuntoSelect){ return; }
    if(!depId){
      assuntoSelect.innerHTML = '<option value="">Selecione um assunto</option>';
      return;
    }
    try{
      const res = await fetch(`${apiBase}/departamentos/${depId}/assuntos`);
      const data = await res.json();
      assuntoSelect.innerHTML = '<option value="">Selecione um assunto</option>' + data.map(a=>`<option value="${a.slug}">${a.nome}</option>`).join('');
    }catch(e){
      assuntoSelect.innerHTML = '<option value="">Selecione um assunto</option>';
    }
  }

  function ticketForm(){
    const wrap = document.getElementById('ans-ticket-form');
    if(!wrap) return;
    const form = wrap.querySelector('form');
    const result = wrap.querySelector('.ans-ticket-result');
    const clienteField = document.querySelector('#ans-ticket-form .cliente-uni');
    const clienteSelect = document.getElementById('ans-cliente-uniodonto');
    if(clienteSelect && clienteField){
      clienteSelect.addEventListener('change', ()=>{
        const isCliente = clienteSelect.value === 'true';
        clienteField.style.display = isCliente ? '' : 'none';
        const input = clienteField.querySelector('input');
        if(input){ input.required = isCliente; }
      });
    }
    const telInput = form.querySelector('input[name="telefone"]');
    const waInput = form.querySelector('input[name="whatsapp"]');
    if(telInput){ telInput.addEventListener('input', ()=>maskPhone(telInput,false)); }
    if(waInput){ waInput.required = true; waInput.addEventListener('input', ()=>maskPhone(waInput,true)); }
    const depSelect = document.getElementById('ans-departamento');
    if(depSelect){
      depSelect.addEventListener('change', ()=>loadAssuntosByDep(depSelect.value));
    }
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
    const grid = document.querySelector('#ans-ticket-form .ans-grid');
    const ouvidoriaField = document.querySelector('#ans-ticket-form .field-ouvidoria');
    const ouvidoriaInput = document.getElementById('ans-ticket-origem');
    const notice = document.getElementById('ans-ouvidoria-notice');
    if (!select || !block) return;
    const isOuvidoria = select.value === 'ouvidoria';
    const isAssist = select.value === 'assistencial' || select.value === 'atendimento';
    const assistFields = block.querySelectorAll('.field-assistencial');

    if (ouvidoriaField) {
      ouvidoriaField.style.display = isOuvidoria ? '' : 'none';
      if (ouvidoriaInput) {
        ouvidoriaInput.required = isOuvidoria;
      }
    }

    assistFields.forEach(el=>{ el.style.display = isAssist ? '' : 'none'; });
    block.style.display = (isAssist) ? 'grid' : 'none';

    let noticeBox = notice;
    if (!noticeBox) {
      noticeBox = document.createElement('div');
      noticeBox.id = 'ans-ouvidoria-notice';
      noticeBox.className = 'ans-ouvidoria-notice';
      if (grid) {
        grid.appendChild(noticeBox);
      }
    }
    if (isOuvidoria) {
      noticeBox.innerHTML = '<p><strong>Sobre a Ouvidoria</strong></p><p>A Ouvidoria tem como objetivo intervir em favor dos clientes que já recorreram à Central de Atendimento, Serviço de Apoio ao Cliente (SAC) ou contato por e-mail e não se sentiram satisfeitos ou desejam rever a solução dada por estes canais.</p><p>Outro método de contato é o Telefone: (53) 3232–1563</p><p>A Ouvidoria Uniodonto Rio Grande Litoral possui o prazo de resolução de até 7 (sete) dias úteis. Em casos excepcionais ou de maior complexidade em que não seja possível a resolução em até 7 (sete) dias úteis, o prazo poderá ser ajustado com o beneficiário para um período não superior a 30 (trinta) dias úteis, como prevê a Resolução Normativa nº 323/2013.</p>';
      noticeBox.style.display = 'block';
    } else {
      noticeBox.style.display = 'none';
    }
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
    html += '<div><h4>[#'+ticket.protocolo+'] '+(ticket.assunto||'')+'</h4>';
    html += '<div class="ans-ticket-meta">'+statusBadge(ticket.status, ticket.status_label)+' • Criado em '+(ticket.created_at||'')+' • '+(ticket.departamento_nome||'')+'</div>';
    html += '</div></div>';
    html += '<div class="ans-ticket-desc">'+ticket.descricao+'</div>';
    html += '<div class="ans-thread-wrap">';
    html += '<div class="ans-thread-title">Histórico</div>';
    html += '<ul class="ans-thread">';
    const interactions = ticket.interacoes||[];
    const anexos = (ticket.anexos||[]).sort((a,b)=>{
      const da = new Date((a.created_at||'').replace(' ','T'));
      const db = new Date((b.created_at||'').replace(' ','T'));
      return da - db;
    });
    const map = {};
    anexos.forEach(a=>{
      const key = a.interacao_id || 'ticket';
      if(!map[key]) map[key]=[];
      map[key].push(a);
    });
    let lastDate = '';
    if(!interactions.length){
      html += '<li class="ans-thread-item"><div class="ans-thread-body">Sem interações ainda.</div></li>';
    }
    interactions.forEach(i=>{
      const day = (i.created_at||'').split(' ')[0];
      if(day && day!==lastDate){
        html += '<li class="ans-thread-item ans-thread-sep">'+day+'</li>';
        lastDate = day;
      }
      const whoClass = i.autor_tipo==='cliente' ? 'beneficiario' : 'atendente';
      const whoLabel = i.autor_tipo==='cliente' ? 'Beneficiário' : 'Atendente';
      const attach = map[i.id]||[];
      html += '<li class="ans-thread-item">';
      html += '<div class="ans-thread-top"><span class="ans-badge ans-pill-'+whoClass+'">'+whoLabel+'</span><span class="ans-thread-date">'+i.created_at+'</span></div>';
      html += '<div class="ans-thread-body">'+i.mensagem+'</div>';
      if(attach.length){
        html += '<div class="ans-thread-attach">'+attach.map(a=>`<a href="${a.url}" target="_blank" rel="noopener">${a.mime_type||'Arquivo'} (${(a.tamanho_bytes/1024/1024).toFixed(2)}MB)</a>`).join('<br>')+'</div>';
      }
      html += '</li>';
    });
    const ticketAttach = map['ticket']||[];
    if(ticketAttach.length){
      html += '<li class="ans-thread-item"><div class="ans-thread-body">'+ticketAttach.map(a=>`<a href="${a.url}" target="_blank" rel="noopener">${a.mime_type||'Arquivo'} (${(a.tamanho_bytes/1024/1024).toFixed(2)}MB)</a>`).join('<br>')+'</div></li>';
    }
    html += '</ul></div>';
    html += '<div class="ans-reply-box">';
    html += '<label>Enviar nova mensagem</label>';
    html += '<textarea class="ans-reply-text" data-protocolo="'+ticket.protocolo+'" placeholder="Digite sua resposta..."></textarea>';
    html += '<div class="ans-actions" style="margin-top:8px;text-align:left;"><button type="button" class="ans-btn ans-send-reply" data-protocolo="'+ticket.protocolo+'">Enviar resposta</button></div>';
    html += '<p class="ans-reply-hint">Você receberá a resposta aqui e no e-mail informado.</p>';
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
        html += '<div class="ans-recovered-top"><span class="ans-proto">'+t.protocolo+'</span>'+statusBadge(t.status, t.status_label)+'</div>';
        html += '<div class="ans-recovered-meta">'+statusLabel(t.status, t.status_label)+'</div>';
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
      if (window.ansTicketPortal && typeof window.ansTicketPortal.select === 'function') {
        window.ansTicketPortal.select('ans-ticket-track');
      }
      form.querySelector('[name="protocolo"]').value = protocolo;
      form.querySelector('[name="documento"]').value = documento;
      form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    }
  };

  function setupTabs(){
    const form = document.getElementById('ans-ticket-form');
    const track = document.getElementById('ans-ticket-track');
    const recover = document.getElementById('ans-ticket-recover');
    const panels = [
      form ? {id: 'ans-ticket-form', label: 'Abrir novo chamado', el: form} : null,
      track ? {id: 'ans-ticket-track', label: 'Já tenho protocolo', el: track} : null,
      recover ? {id: 'ans-ticket-recover', label: 'Não sei o protocolo', el: recover} : null,
    ].filter(Boolean);

    if (panels.length <= 1) return;

    const first = panels[0].el;
    const parent = first.parentNode;
    if (!parent) return;
    if (parent.querySelector('.ans-portal-nav')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'ans-portal';
    const nav = document.createElement('div');
    nav.className = 'ans-portal-nav';
    nav.setAttribute('role', 'tablist');

    const panelsHost = document.createElement('div');
    panelsHost.className = 'ans-portal-panels';

    parent.insertBefore(wrapper, first);
    wrapper.appendChild(nav);
    wrapper.appendChild(panelsHost);

    function select(panelId) {
      panels.forEach((p) => {
        const active = p.id === panelId;
        p.el.hidden = !active;
        p.el.classList.toggle('is-active', active);
        const btn = nav.querySelector(`button[data-panel="${p.id}"]`);
        if (btn) {
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
          btn.tabIndex = active ? 0 : -1;
        }
      });
    }

    panels.forEach((p, idx) => {
      p.el.setAttribute('role', 'tabpanel');
      p.el.classList.add('ans-portal-panel');
      panelsHost.appendChild(p.el);

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ans-portal-tab';
      btn.textContent = p.label;
      btn.dataset.panel = p.id;
      btn.setAttribute('role', 'tab');
      btn.setAttribute('aria-controls', p.id);
      btn.setAttribute('aria-selected', 'false');
      btn.tabIndex = -1;
      btn.addEventListener('click', () => select(p.id));
      nav.appendChild(btn);

      if (idx === 0) {
        btn.tabIndex = 0;
      }
    });

    window.ansTicketPortal = { select };

    // Padrão: Abrir novo chamado (se existir), senão consultar.
    select(form ? 'ans-ticket-form' : 'ans-ticket-track');
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    loadDepartamentos();
    setupTabs();
    ticketForm();
    trackForm();
    recoverForm();
    toggleAssistFields();
    const select = document.getElementById('ans-assunto');
    if (select) {
        select.addEventListener('change', toggleAssistFields);
    }
    const depSelect = document.getElementById('ans-departamento');
    if(depSelect){
        depSelect.addEventListener('change', ()=>{ toggleAssistFields(); loadAssuntosByDep(depSelect.value); });
    }
  });
})();
