/* UCSI static QA correction layer. Loaded after app.js so existing views remain reusable. */
(function () {
  const originalNavigate = window.navigate;
  const originalRenderAssessments = window.renderAssessments;

  window.renderBeneficiaries = async function () {
    const params = new URLSearchParams((location.hash.split('?')[1] || ''));
    const q = params.get('q') || '';
    const status = params.get('status') || '';
    const query = new URLSearchParams({ per_page: '50' });
    if (q) query.set('q', q);
    if (status) query.set('status', status);

    const d = await api(`api/beneficiaries?${query.toString()}`);
    state.beneficiaries = d.data;
    const selected = (value) => value === status ? ' selected' : '';

    $('#pageContent').innerHTML = `<div class="page-heading"><div><p class="eyebrow">CASE MANAGEMENT</p><h2>Beneficiaries</h2><p>Maintain a complete, consent-aware profile for every participant.</p></div><button class="btn btn-primary" onclick="openBeneficiary()"><i class="bi bi-person-plus me-2"></i>Register beneficiary</button></div><div class="panel"><div class="panel-header"><div class="toolbar"><input id="beneficiarySearch" class="form-control" placeholder="Search name, code, phone or community" value="${esc(q)}"><select id="beneficiaryStatus" class="form-select"><option value=""${selected('')}>All statuses</option><option value="active"${selected('active')}>Active</option><option value="inactive"${selected('inactive')}>Inactive</option><option value="graduated"${selected('graduated')}>Graduated</option><option value="deceased"${selected('deceased')}>Deceased</option></select><button class="btn btn-light" onclick="searchBeneficiaries()"><i class="bi bi-search"></i></button></div><span class="text-muted small">${d.pagination.total} records</span></div><div class="table-wrap"><table class="table"><thead><tr><th>Beneficiary</th><th>Code</th><th>Gender</th><th>Location</th><th>Vulnerability</th><th>Status</th><th></th></tr></thead><tbody>${state.beneficiaries.map(b=>`<tr><td><strong>${esc(b.first_name)} ${esc(b.last_name)}</strong><div class="text-muted" style="font-size:10px">${esc(b.phone||'No phone')}</div></td><td>${esc(b.beneficiary_code)}</td><td>${esc(b.gender)}</td><td>${esc(b.community)}, ${esc(b.state)}</td><td>${badge(b.vulnerability_status)}</td><td>${badge(b.status)}</td><td><button class="btn btn-sm btn-light" onclick="viewBeneficiary(${Number(b.id)})"><i class="bi bi-eye"></i></button></td></tr>`).join('')||emptyRow(7,'No beneficiaries match the current filter')}</tbody></table></div></div>`;
  };

  window.searchBeneficiaries = function () {
    const q = $('#beneficiarySearch').value.trim();
    const status = $('#beneficiaryStatus').value;
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (status) params.set('status', status);
    location.hash = params.toString() ? `beneficiaries?${params.toString()}` : 'beneficiaries';
    navigate();
  };

  window.renderAssessments = async function () {
    if (!state.beneficiaries.length) {
      const d = await api('api/beneficiaries?per_page=100');
      state.beneficiaries = d.data;
    }
    return originalRenderAssessments();
  };

  async function renderIndicators() {
    const [indicatorResponse, programmeResponse] = await Promise.all([
      api('api/indicators'),
      api('api/programmes')
    ]);
    state.indicators = indicatorResponse.data;
    state.programmes = programmeResponse.data;

    $('#pageContent').innerHTML = `<div class="page-heading"><div><p class="eyebrow">IMPACT MEASUREMENT</p><h2>Indicators</h2><p>Define programme indicators and record evidence-backed reporting periods.</p></div><button class="btn btn-primary" onclick="openIndicatorForm()"><i class="bi bi-plus-lg me-2"></i>New indicator</button></div><div class="panel"><div class="table-wrap"><table class="table"><thead><tr><th>Indicator</th><th>Programme</th><th>Type</th><th>Unit</th><th>Baseline</th><th>Target</th><th>Latest</th><th></th></tr></thead><tbody>${state.indicators.map(i=>`<tr><td><strong>${esc(i.name)}</strong><div class="text-muted" style="font-size:10px">${esc(i.description||'')}</div></td><td>${esc(i.programme_name)}</td><td>${badge(i.indicator_type)}</td><td>${esc(i.unit)}</td><td>${i.baseline===null?'—':esc(i.baseline)}</td><td>${i.target===null?'—':esc(i.target)}</td><td>${i.latest_value===null?'—':esc(i.latest_value)}</td><td><button class="btn btn-sm btn-light" onclick="openIndicatorValueForm(${Number(i.id)})" title="Record value"><i class="bi bi-pencil-square"></i></button></td></tr>`).join('')||emptyRow(8,'No indicators have been created')}</tbody></table></div></div>`;
  }

  window.renderIndicators = renderIndicators;

  window.navigate = async function () {
    const page = (location.hash.replace('#','') || 'dashboard').split('?')[0];
    if (page === 'indicators') {
      state.page = page;
      setActive(page);
      $('#sidebar').classList.remove('open');
      $('#pageContent').innerHTML = '<div class="spinner-page"><div class="spinner-border"></div></div>';
      try { await renderIndicators(); } catch (e) { $('#pageContent').innerHTML = `<div class="panel"><div class="empty-state"><i class="bi bi-exclamation-triangle"></i><h4>Unable to load indicators</h4><p>${esc(e.message)}</p><button class="btn btn-primary btn-sm" onclick="navigate()">Try again</button></div></div>`; }
      return;
    }
    return originalNavigate();
  };

  function openModal(title, body, footer) {
    let el = $('#qaModal');
    if (!el) {
      el = document.createElement('div');
      el.id = 'qaModal';
      el.className = 'modal fade';
      el.innerHTML = '<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div><div class="modal-footer"></div></div></div>';
      document.body.appendChild(el);
    }
    $('.modal-title', el).textContent = title;
    $('.modal-body', el).innerHTML = body;
    $('.modal-footer', el).innerHTML = footer || '<button class="btn btn-light" data-bs-dismiss="modal">Close</button>';
    return bootstrap.Modal.getOrCreateInstance(el);
  }

  window.openIndicatorForm = function () {
    const body = `<form id="qaIndicatorForm"><div class="row g-3"><div class="col-md-8"><label class="form-label">Programme</label><select name="programme_id" class="form-select" required>${state.programmes.map(p=>`<option value="${Number(p.id)}">${esc(p.name)}</option>`).join('')}</select></div><div class="col-md-4"><label class="form-label">Type</label><select name="indicator_type" class="form-select" required><option value="output">Output</option><option value="outcome">Outcome</option><option value="impact">Impact</option></select></div><div class="col-12"><label class="form-label">Indicator name</label><input name="name" class="form-control" required maxlength="180"></div><div class="col-md-6"><label class="form-label">Unit</label><input name="unit" class="form-control" placeholder="% / people / households" required maxlength="50"></div><div class="col-md-3"><label class="form-label">Baseline</label><input name="baseline" type="number" step="0.0001" class="form-control"></div><div class="col-md-3"><label class="form-label">Target</label><input name="target" type="number" step="0.0001" class="form-control"></div><div class="col-md-6"><label class="form-label">Frequency</label><select name="frequency" class="form-select"><option value="monthly">Monthly</option><option value="quarterly" selected>Quarterly</option><option value="biannual">Biannual</option><option value="annual">Annual</option><option value="event">Event</option></select></div><div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div></div></form>`;
    const modal = openModal('Create impact indicator', body, '<button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="saveIndicator()">Create indicator</button>');
    modal.show();
  };

  window.saveIndicator = async function () {
    const form = $('#qaIndicatorForm');
    const data = Object.fromEntries(new FormData(form).entries());
    data.active = 1;
    try {
      await api('api/indicators', { method:'POST', body:JSON.stringify(data) });
      bootstrap.Modal.getInstance($('#qaModal')).hide();
      toast('Indicator created successfully.');
      await renderIndicators();
    } catch (e) { toast(e.message, 'error'); }
  };

  window.openIndicatorValueForm = function (indicatorId) {
    const indicator = state.indicators.find(i => Number(i.id) === Number(indicatorId));
    const body = `<form id="qaIndicatorValueForm"><input type="hidden" name="indicator_id" value="${Number(indicatorId)}"><div class="mb-3"><label class="form-label">Indicator</label><input class="form-control" value="${esc(indicator ? indicator.name : '')}" disabled></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Reporting period</label><input name="reporting_period" type="date" class="form-control" value="${dateToday()}" required></div><div class="col-md-6"><label class="form-label">Value</label><input name="value" type="number" step="0.0001" class="form-control" required></div><div class="col-12"><label class="form-label">Evidence note</label><textarea name="evidence_note" class="form-control" rows="3"></textarea></div></div></form>`;
    const modal = openModal('Record indicator value', body, '<button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="saveIndicatorValue()">Save value</button>');
    modal.show();
  };

  window.saveIndicatorValue = async function () {
    const form = $('#qaIndicatorValueForm');
    const data = Object.fromEntries(new FormData(form).entries());
    try {
      await api('api/indicator-values', { method:'POST', body:JSON.stringify(data) });
      bootstrap.Modal.getInstance($('#qaModal')).hide();
      toast('Indicator value recorded.');
      await renderIndicators();
    } catch (e) { toast(e.message, 'error'); }
  };
})();
