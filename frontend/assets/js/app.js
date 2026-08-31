const API_BASE = '../backend';
const state = {
  user: null,
  csrf: null,
  dashboard: null,
  beneficiaries: [],
  programmes: [],
  interventions: [],
  assessments: [],
  indicators: [],
  reports: [],
  page: 'dashboard'
};

const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];
const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[c]));
const fmtNum = v => Number(v || 0).toLocaleString();
const money = v => `₦${Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
const dateToday = () => new Date().toISOString().slice(0, 10);
const can = roles => state.user && roles.includes(state.user.role);

async function api(path, options = {}) {
  const opts = { ...options, headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, credentials: 'include' };
  if (!['GET', 'HEAD'].includes((opts.method || 'GET').toUpperCase()) && state.csrf) opts.headers['X-CSRF-Token'] = state.csrf;
  const res = await fetch(`${API_BASE}/${path.replace(/^\//, '')}`, opts);
  const data = await res.json().catch(() => ({ success: false, message: 'The server returned an invalid response.' }));
  if (res.status === 401) {
    state.user = null;
    state.csrf = null;
    showLogin();
    throw new Error(data.message || 'Session expired.');
  }
  if (!res.ok || data.success === false) throw new Error(data.message || 'Request failed.');
  if (data.csrf_token) state.csrf = data.csrf_token;
  return data;
}

function toast(message, type = 'success') {
  const el = document.createElement('div');
  el.className = `toast align-items-center border-0 text-bg-${type === 'error' ? 'danger' : type}`;
  el.setAttribute('role', 'alert');
  el.innerHTML = `<div class="d-flex"><div class="toast-body"><i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-info-circle'} me-2"></i>${esc(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  $('#toastStack').appendChild(el);
  new bootstrap.Toast(el, { delay: 3600 }).show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}

function showLogin() { $('#appView').classList.add('d-none'); $('#loginView').classList.remove('d-none'); }
function showApp() {
  $('#loginView').classList.add('d-none');
  $('#appView').classList.remove('d-none');
  $('#userName').textContent = state.user.name;
  $('#userRole').textContent = state.user.role.replaceAll('_', ' ');
  $('#userAvatar').textContent = state.user.name.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
  applyRoleNavigation();
}
function applyRoleNavigation() {
  const rules = {
    programmes: ['admin', 'manager', 'field_officer', 'analyst', 'viewer'],
    interventions: ['admin', 'manager', 'field_officer', 'analyst', 'viewer'],
    assessments: ['admin', 'manager', 'field_officer', 'analyst', 'viewer'],
    indicators: ['admin', 'manager', 'analyst', 'viewer'],
    reports: ['admin', 'manager', 'field_officer', 'analyst', 'viewer']
  };
  $$('.nav-link[data-page]').forEach(link => {
    const page = link.dataset.page;
    link.classList.toggle('d-none', !!rules[page] && !rules[page].includes(state.user.role));
  });
}

async function login(e) {
  e.preventDefault();
  const btn = e.target.querySelector('button');
  btn.disabled = true;
  btn.querySelector('.btn-label')?.classList.add('d-none');
  btn.querySelector('.spinner-border')?.classList.remove('d-none');
  try {
    const d = await api('api/auth/login', { method: 'POST', body: JSON.stringify({ email: $('#loginEmail').value.trim(), password: $('#loginPassword').value }) });
    state.user = d.user;
    state.csrf = d.csrf_token;
    showApp();
    location.hash = 'dashboard';
    await navigate();
  } catch (err) { toast(err.message, 'error'); }
  finally {
    btn.disabled = false;
    btn.querySelector('.btn-label')?.classList.remove('d-none');
    btn.querySelector('.spinner-border')?.classList.add('d-none');
  }
}

async function logout() {
  try { await api('api/auth/logout', { method: 'POST', body: '{}' }); } catch (_) {}
  state.user = null; state.csrf = null; showLogin();
}

function setActive(page) {
  $$('.nav-link').forEach(a => a.classList.toggle('active', a.dataset.page === page));
  const titles = { dashboard:'Dashboard', beneficiaries:'Beneficiaries', programmes:'Programmes', interventions:'Interventions', assessments:'Assessments', indicators:'Indicators', reports:'Impact reports' };
  $('#pageTitle').textContent = titles[page] || 'Dashboard';
}

async function navigate() {
  const page = (location.hash.replace('#', '') || 'dashboard').split('?')[0];
  const allowed = ['dashboard','beneficiaries','programmes','interventions','assessments','indicators','reports'];
  if (!allowed.includes(page)) { location.hash = 'dashboard'; return; }
  state.page = page;
  setActive(page);
  $('#sidebar').classList.remove('open');
  const content = $('#pageContent');
  content.innerHTML = '<div class="spinner-page"><div class="spinner-border"></div></div>';
  try {
    if (page === 'dashboard') await renderDashboard();
    else if (page === 'beneficiaries') await renderBeneficiaries();
    else if (page === 'programmes') await renderProgrammes();
    else if (page === 'interventions') await renderInterventions();
    else if (page === 'assessments') await renderAssessments();
    else if (page === 'indicators') await renderIndicators();
    else if (page === 'reports') await renderReports();
  } catch (e) {
    content.innerHTML = `<div class="panel"><div class="empty-state"><i class="bi bi-exclamation-triangle"></i><h4>Unable to load this view</h4><p>${esc(e.message)}</p><button class="btn btn-primary btn-sm" onclick="navigate()">Try again</button></div></div>`;
  }
}

function metric(icon, label, value, meta) {
  return `<div class="metric-card"><div class="metric-top"><span class="metric-label">${esc(label)}</span><span class="metric-icon"><i class="bi ${icon}"></i></span></div><div class="metric-value">${esc(value)}</div><div class="metric-meta">${esc(meta)}</div></div>`;
}
function badge(v) {
  const c = String(v || '').toLowerCase();
  const cls = ['active','completed','low'].includes(c) ? 'badge-active' : ['planned','medium','enrolled'].includes(c) ? 'badge-planned' : ['high','withdrawn'].includes(c) ? 'badge-high' : ['critical','cancelled','deceased'].includes(c) ? 'badge-critical' : 'badge-completed';
  return `<span class="badge-soft ${cls}">${esc(String(v || '—').replaceAll('_', ' '))}</span>`;
}
function emptyRow(cols, msg) { return `<tr><td colspan="${cols}"><div class="empty-state py-5"><i class="bi bi-inbox"></i><h4>${esc(msg)}</h4></div></td></tr>`; }
function field(name, label, type = 'text', required = false, value = '', extra = '') { return `<div class="mb-3"><label class="form-label">${esc(label)}${required ? ' *' : ''}</label><input name="${esc(name)}" type="${type}" class="form-control" value="${esc(value)}" ${required ? 'required' : ''} ${extra}></div>`; }
function selectField(name, label, options, required = false) { return `<div class="mb-3"><label class="form-label">${esc(label)}${required ? ' *' : ''}</label><select name="${esc(name)}" class="form-select" ${required ? 'required' : ''}>${options.map(([v,t]) => `<option value="${esc(v)}">${esc(t)}</option>`).join('')}</select></div>`; }
function showModal(id, title, body, submitText, onSubmit) {
  let el = document.getElementById(id);
  if (!el) { el = document.createElement('div'); el.id = id; el.className = 'modal fade'; document.body.appendChild(el); }
  el.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form><div class="modal-header"><h5 class="modal-title">${esc(title)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">${esc(submitText)}</button></div></form></div></div>`;
  el.querySelector('form').addEventListener('submit', onSubmit);
  bootstrap.Modal.getOrCreateInstance(el).show();
  return el;
}

async function renderDashboard() {
  const d = await api('api/dashboard');
  state.dashboard = d.data;
  const recent = await api('api/beneficiaries?per_page=6');
  state.beneficiaries = recent.data;
  const x = d.data;
  $('#pageContent').innerHTML = `<div class="page-heading"><div><p class="eyebrow">OPERATIONS OVERVIEW</p><h2>Community impact at a glance</h2><p>Monitor reach, delivery and measured outcomes across UCSI programmes.</p></div><button class="btn btn-primary" onclick="location.hash='beneficiaries'" ${can(['admin','manager','field_officer']) ? '' : 'disabled'}><i class="bi bi-person-plus me-2"></i>Register beneficiary</button></div><div class="metric-grid">${metric('bi-people','Total beneficiaries',fmtNum(x.beneficiaries),`${fmtNum(x.active_beneficiaries)} currently active`)}${metric('bi-diagram-3','Programmes',fmtNum(x.programmes),'Across the portfolio')}${metric('bi-bullseye','Interventions',fmtNum(x.interventions),'Delivery activities tracked')}${metric('bi-graph-up-arrow','Average impact',Number(x.average_impact_score || 0).toFixed(2),'Average assessment score')}</div><div class="dashboard-grid"><div class="panel"><div class="panel-header"><h3>Recent beneficiary registrations</h3><a href="#beneficiaries" class="small text-decoration-none">View all</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Beneficiary</th><th>Code</th><th>Community</th><th>Vulnerability</th><th>Status</th></tr></thead><tbody>${state.beneficiaries.map(b => `<tr><td><strong>${esc(b.first_name)} ${esc(b.last_name)}</strong></td><td>${esc(b.beneficiary_code)}</td><td>${esc(b.community)}, ${esc(b.state)}</td><td>${badge(b.vulnerability_status)}</td><td>${badge(b.status)}</td></tr>`).join('') || emptyRow(5,'No beneficiary registrations yet')}</tbody></table></div></div><div class="panel"><div class="panel-header"><h3>Delivery pulse</h3></div><div class="panel-body"><div class="d-flex justify-content-between mb-2"><span class="text-muted small">Completed interventions</span><strong>${fmtNum(x.completed_interventions)}</strong></div><div class="progress-thin mb-4"><div class="progress-bar bg-success" style="width:${Math.min(100,(x.completed_interventions/Math.max(1,x.interventions))*100)}%"></div></div><div class="d-flex justify-content-between mb-2"><span class="text-muted small">Active beneficiaries</span><strong>${fmtNum(x.active_beneficiaries)}</strong></div><div class="progress-thin mb-4"><div class="progress-bar" style="width:${Math.min(100,(x.active_beneficiaries/Math.max(1,x.beneficiaries))*100)}%"></div></div><div class="p-3 rounded-3" style="background:#f7faff"><div class="d-flex gap-2"><i class="bi bi-shield-check text-primary"></i><div><strong class="d-block small">Data governance</strong><span class="text-muted" style="font-size:11px">Role-based access, CSRF protection and audit logging are active.</span></div></div></div></div></div></div>`;
}

async function renderBeneficiaries() {
  const params = new URLSearchParams(location.hash.split('?')[1] || '');
  const q = params.get('q') || ''; const status = params.get('status') || '';
  const d = await api(`api/beneficiaries?per_page=50${q ? '&q=' + encodeURIComponent(q) : ''}${status ? '&status=' + encodeURIComponent(status) : ''}`);
  state.beneficiaries = d.data;
  $('#pageContent').innerHTML = `<div class="page-heading"><div><p class="eyebrow">CASE MANAGEMENT</p><h2>Beneficiaries</h2><p>Maintain complete participant profiles and case status.</p></div>${can(['admin','manager','field_officer']) ? '<button class="btn btn-primary" onclick="openBeneficiary()"><i class="bi bi-person-plus me-2"></i>Register beneficiary</button>' : ''}</div><div class="panel"><div class="panel-header"><div class="toolbar"><input id="beneficiarySearch" class="form-control" placeholder="Search name, code, phone or community" value="${esc(q)}"><select id="beneficiaryStatus" class="form-select"><option value="">All statuses</option><option value="active" ${status==='active'?'selected':''}>Active</option><option value="inactive" ${status==='inactive'?'selected':''}>Inactive</option><option value="graduated" ${status==='graduated'?'selected':''}>Graduated</option><option value="deceased" ${status==='deceased'?'selected':''}>Deceased</option></select><button class="btn btn-light" onclick="searchBeneficiaries()"><i class="bi bi-search"></i></button></div><span class="text-muted small">${fmtNum(d.pagination.total)} records</span></div><div class="table-wrap"><table class="table"><thead><tr><th>Beneficiary</th><th>Code</th><th>Gender</th><th>Location</th><th>Vulnerability</th><th>Status</th><th>Actions</th></tr></thead><tbody>${state.beneficiaries.map(b => `<tr><td><strong>${esc(b.first_name)} ${esc(b.last_name)}</strong><div class="text-muted" style="font-size:10px">${esc(b.phone || 'No phone')}</div></td><td>${esc(b.beneficiary_code)}</td><td>${esc(b.gender)}</td><td>${esc(b.community)}, ${esc(b.state)}</td><td>${badge(b.vulnerability_status)}</td><td>${badge(b.status)}</td><td class="text-nowrap"><button class="btn btn-sm btn-light" onclick="viewBeneficiary(${b.id})"><i class="bi bi-eye"></i></button>${can(['admin','manager','field_officer']) ? ` <button class="btn btn-sm btn-light" onclick="editBeneficiary(${b.id})"><i class="bi bi-pencil"></i></button>` : ''}${can(['admin','manager']) && b.status==='active' ? ` <button class="btn btn-sm btn-outline-danger" onclick="deactivateBeneficiary(${b.id})"><i class="bi bi-person-x"></i></button>` : ''}</td></tr>`).join('') || emptyRow(7,'No beneficiaries match the current filter')}</tbody></table></div></div>`;
}
function searchBeneficiaries() { const q = $('#beneficiarySearch').value.trim(); const status = $('#beneficiaryStatus').value; location.hash = `beneficiaries${q || status ? '?' : ''}${q ? 'q='+encodeURIComponent(q) : ''}${q && status ? '&' : ''}${status ? 'status='+encodeURIComponent(status) : ''}`; navigate(); }
function openBeneficiary() {
  const body = `<div class="row g-3">${field('first_name','First name','text',true)}${field('middle_name','Middle name')}${field('last_name','Last name','text',true)}${selectField('gender','Gender',[['','Select'],['female','Female'],['male','Male'],['other','Other']],true)}${field('date_of_birth','Date of birth','date')}${field('phone','Phone')}${field('email','Email','email')}${field('household_size','Household size','number',false,'1','min="1"')}${field('community','Community','text',true)}${field('lga','LGA')}${field('state','State','text',true)}${field('registration_date','Registration date','date',true,dateToday())}${selectField('vulnerability_status','Vulnerability',[['low','Low'],['medium','Medium'],['high','High'],['critical','Critical']])}${selectField('employment_status','Employment',[['','Select'],['employed','Employed'],['self_employed','Self-employed'],['unemployed','Unemployed'],['student','Student'],['retired','Retired'],['other','Other']])}<div class="col-12">${field('address','Address')}</div><div class="col-12 form-check"><input name="consent_given" value="1" class="form-check-input" type="checkbox" id="newConsent"><label class="form-check-label" for="newConsent">Beneficiary consent has been obtained.</label></div></div>`;
  showModal('beneficiaryModalDynamic','Register beneficiary',body,'Save beneficiary',saveBeneficiary);
}
async function saveBeneficiary(e) { e.preventDefault(); const data = Object.fromEntries(new FormData(e.target).entries()); data.consent_given = e.target.consent_given.checked ? 1 : 0; try { await api('api/beneficiaries',{method:'POST',body:JSON.stringify(data)}); bootstrap.Modal.getInstance(e.target.closest('.modal')).hide(); toast('Beneficiary registered successfully.'); await renderBeneficiaries(); } catch (err) { toast(err.message,'error'); } }
async function viewBeneficiary(id) { try { const d = await api(`api/beneficiaries/${id}`), b = d.data; showInfoModal('Beneficiary profile', `<div class="row g-3"><div class="col-md-8"><p class="eyebrow">${esc(b.beneficiary_code)}</p><h3>${esc([b.first_name,b.middle_name,b.last_name].filter(Boolean).join(' '))}</h3><p class="text-muted">${esc([b.community,b.lga,b.state].filter(Boolean).join(', '))}</p></div><div class="col-md-4 text-md-end">${badge(b.status)} ${badge(b.vulnerability_status)}</div>${[['Gender',b.gender],['Phone',b.phone||'Not provided'],['Email',b.email||'Not provided'],['Household',`${b.household_size} members`],['Employment',b.employment_status||'Not recorded'],['Consent',Number(b.consent_given)?'Obtained':'Not recorded'],['Registration date',b.registration_date]].map(([l,v])=>`<div class="col-md-4"><strong>${esc(l)}</strong><div>${esc(v)}</div></div>`).join('')}<div class="col-12"><hr><strong>Address</strong><div>${esc(b.address||'Not provided')}</div></div></div>`); } catch (e) { toast(e.message,'error'); } }
function showInfoModal(title,body) { let el=$('#infoModal'); if(!el){el=document.createElement('div');el.id='infoModal';el.className='modal fade';document.body.appendChild(el);} el.innerHTML=`<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">${esc(title)}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">${body}</div></div></div>`; bootstrap.Modal.getOrCreateInstance(el).show(); }
async function editBeneficiary(id) {
  const b = (await api(`api/beneficiaries/${id}`)).data;
  const body = `<div class="row g-3">${field('first_name','First name','text',true,b.first_name)}${field('middle_name','Middle name','text',false,b.middle_name||'')}${field('last_name','Last name','text',true,b.last_name)}${selectField('gender','Gender',[['male','Male'],['female','Female'],['other','Other']],true)}${field('date_of_birth','Date of birth','date',false,b.date_of_birth||'')}${field('phone','Phone','text',false,b.phone||'')}${field('email','Email','email',false,b.email||'')}${field('household_size','Household size','number',false,b.household_size,'min="1"')}${field('community','Community','text',true,b.community)}${field('lga','LGA','text',false,b.lga||'')}${field('state','State','text',true,b.state)}${selectField('vulnerability_status','Vulnerability',[['low','Low'],['medium','Medium'],['high','High'],['critical','Critical']],false)}${selectField('employment_status','Employment',[['','Select'],['employed','Employed'],['self_employed','Self-employed'],['unemployed','Unemployed'],['student','Student'],['retired','Retired'],['other','Other']],false)}${selectField('status','Status',[['active','Active'],['inactive','Inactive'],['graduated','Graduated'],['deceased','Deceased']])}${field('registration_date','Registration date','date',true,b.registration_date)}</div>`;
  const el=showModal('beneficiaryEditModal',`Edit ${b.first_name} ${b.last_name}`,body,'Save changes',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());data.consent_given=Number(b.consent_given||0);try{await api(`api/beneficiaries/${id}`,{method:'PUT',body:JSON.stringify(data)});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Beneficiary updated.');await renderBeneficiaries();}catch(err){toast(err.message,'error');}});
  ['gender','vulnerability_status','employment_status','status'].forEach(n=>{const el2=el.querySelector(`[name="${n}"]`);if(el2)el2.value=b[n]??'';});
}
async function deactivateBeneficiary(id) { if(!confirm('Deactivate this beneficiary record?')) return; try { await api(`api/beneficiaries/${id}`,{method:'DELETE',body:'{}'}); toast('Beneficiary deactivated.'); await renderBeneficiaries(); } catch(e){toast(e.message,'error');} }

async function renderProgrammes() { const d=await api('api/programmes'); state.programmes=d.data; $('#pageContent').innerHTML=`<div class="page-heading"><div><p class="eyebrow">PROGRAMME PORTFOLIO</p><h2>Programmes</h2><p>Organise strategic initiatives and track their delivery footprint.</p></div>${can(['admin','manager'])?'<button class="btn btn-primary" onclick="openProgramme()"><i class="bi bi-plus-lg me-2"></i>New programme</button>':''}</div><div class="report-grid">${state.programmes.map(p=>`<div class="panel report-card"><div class="d-flex justify-content-between align-items-start mb-3"><span class="badge-soft badge-completed">${esc(p.code)}</span>${badge(p.status)}</div><h4>${esc(p.name)}</h4><p class="text-muted small" style="min-height:36px">${esc(p.description||'No description provided.')}</p><div class="d-flex justify-content-between border-top pt-3 mt-3"><span class="text-muted small">Interventions</span><strong>${fmtNum(p.intervention_count)}</strong></div><div class="d-flex justify-content-between mt-2"><span class="text-muted small">Budget</span><strong>${money(p.budget)}</strong></div><div class="d-flex justify-content-between mt-2"><span class="text-muted small">Period</span><span>${esc(p.start_date)}${p.end_date?' → '+esc(p.end_date):''}</span></div>${can(['admin','manager'])?`<div class="mt-3 d-flex gap-2"><button class="btn btn-sm btn-light" onclick="editProgramme(${p.id})">Edit</button><button class="btn btn-sm btn-outline-danger" onclick="archiveProgramme(${p.id})">Archive</button></div>`:''}</div>`).join('')||'<div class="panel"><div class="empty-state">No programmes have been created.</div></div>'}</div>`; }
function openProgramme(){const body=`${field('code','Programme code','text',true)}${field('name','Programme name','text',true)}<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="row g-3"><div class="col-md-6">${field('start_date','Start date','date',true,dateToday())}</div><div class="col-md-6">${field('end_date','End date','date')}</div><div class="col-md-6">${field('budget','Budget','number',false,'0','min="0" step="0.01"')}</div><div class="col-md-6">${selectField('status','Status',[['planned','Planned'],['active','Active'],['completed','Completed'],['suspended','Suspended']])}</div></div>`;showModal('programmeModalDynamic','Create programme',body,'Create programme',saveProgramme);}
async function saveProgramme(e){e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());try{await api('api/programmes',{method:'POST',body:JSON.stringify(data)});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Programme created successfully.');await renderProgrammes();}catch(err){toast(err.message,'error');}}

async function renderInterventions(){const [i,p]=await Promise.all([api('api/interventions'),api('api/programmes')]);state.interventions=i.data;state.programmes=p.data;$('#pageContent').innerHTML=`<div class="page-heading"><div><p class="eyebrow">DELIVERY MANAGEMENT</p><h2>Interventions</h2><p>Track activities, enrol participants and monitor delivery.</p></div>${can(['admin','manager'])?'<button class="btn btn-primary" onclick="openIntervention()"><i class="bi bi-plus-lg me-2"></i>New intervention</button>':''}</div><div class="panel"><div class="table-wrap"><table class="table"><thead><tr><th>Intervention</th><th>Programme</th><th>Type</th><th>Target</th><th>Enrolled</th><th>Status</th><th>Start</th><th></th></tr></thead><tbody>${state.interventions.map(i=>`<tr><td><strong>${esc(i.name)}</strong><div class="text-muted" style="font-size:10px">${esc(i.description||'')}</div></td><td>${esc(i.programme_name)}</td><td>${esc(i.intervention_type)}</td><td>${fmtNum(i.target_count)}</td><td>${fmtNum(i.enrolled_count)}</td><td>${badge(i.status)}</td><td>${esc(i.start_date)}</td><td>${can(['admin','manager','field_officer'])?`<button class="btn btn-sm btn-light" onclick="openEnrollment(${i.id})"><i class="bi bi-person-plus"></i></button>`:''} ${can(['admin','manager'])?`<button class="btn btn-sm btn-light" onclick="editIntervention(${i.id})">Edit</button>`:''} ${can(['admin','manager'])?`<button class="btn btn-sm btn-outline-danger" onclick="cancelIntervention(${i.id})">Cancel</button>`:''}</td></tr>`).join('')||emptyRow(8,'No interventions have been created')}</tbody></table></div></div>`;}
function openIntervention(){const options=state.programmes.map(p=>[p.id,p.code+' — '+p.name]);const body=`${selectField('programme_id','Programme',options,true)}${field('name','Intervention name','text',true)}<div class="row g-3"><div class="col-md-6">${field('intervention_type','Type','text',true)}</div><div class="col-md-6">${field('target_count','Target count','number',false,'0','min="0"')}</div><div class="col-md-6">${field('start_date','Start date','date',true,dateToday())}</div><div class="col-md-6">${field('end_date','End date','date')}</div><div class="col-md-6">${field('unit_cost','Unit cost','number',false,'0','min="0" step="0.01"')}</div><div class="col-md-6">${selectField('status','Status',[['planned','Planned'],['active','Active'],['completed','Completed'],['cancelled','Cancelled']])}</div></div><div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>`;showModal('interventionModalDynamic','Create intervention',body,'Create intervention',saveIntervention);}
async function saveIntervention(e){e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());try{await api('api/interventions',{method:'POST',body:JSON.stringify(data)});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Intervention created successfully.');await renderInterventions();}catch(err){toast(err.message,'error');}}
async function openEnrollment(interventionId){const [b,i]=await Promise.all([api('api/beneficiaries?per_page=100&status=active'),api('api/interventions')]);const intervention=i.data.find(x=>Number(x.id)===Number(interventionId));const body=`${selectField('beneficiary_id','Beneficiary',b.data.map(x=>[x.id,`${x.beneficiary_code} — ${x.first_name} ${x.last_name}`]),true)}${field('enrollment_date','Enrollment date','date',true,dateToday())}${field('benefit_value','Benefit value','number',false,'0','min="0" step="0.01"')}<div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div><input type="hidden" name="intervention_id" value="${esc(interventionId)}"><div class="alert alert-light">Intervention: <strong>${esc(intervention?.name||'Selected intervention')}</strong></div>`;showModal('enrollmentModal','Enroll beneficiary',body,'Enroll beneficiary',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());try{await api('api/enrollments',{method:'POST',body:JSON.stringify(data)});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Beneficiary enrolled successfully.');await renderInterventions();}catch(err){toast(err.message,'error');}});}

async function renderAssessments(){const [a,b,i]=await Promise.all([api('api/assessments'),api('api/beneficiaries?per_page=100'),api('api/interventions')]);state.assessments=a.data;state.beneficiaries=b.data;state.interventions=i.data;$('#pageContent').innerHTML=`<div class="page-heading"><div><p class="eyebrow">OUTCOME MEASUREMENT</p><h2>Assessments</h2><p>Capture household-level outcome scores and field observations.</p></div>${can(['admin','manager','field_officer','analyst'])?'<button class="btn btn-primary" onclick="openAssessment()"><i class="bi bi-clipboard-plus me-2"></i>New assessment</button>':''}</div><div class="panel"><div class="table-wrap"><table class="table"><thead><tr><th>Beneficiary</th><th>Assessment date</th><th>Assessor</th><th>Food</th><th>Education</th><th>Health</th><th>Livelihood</th><th>Overall</th></tr></thead><tbody>${state.assessments.map(a=>`<tr><td><strong>${esc(a.beneficiary_name)}</strong></td><td>${esc(a.assessment_date)}</td><td>${esc(a.assessor_name)}</td><td>${esc(a.food_security_score??'—')}</td><td>${esc(a.education_score??'—')}</td><td>${esc(a.health_score??'—')}</td><td>${esc(a.livelihood_score??'—')}</td><td><strong>${esc(a.overall_score??'—')}</strong></td></tr>`).join('')||emptyRow(8,'No assessments have been recorded')}</tbody></table></div></div>`;}
function openAssessment(){const body=`${selectField('beneficiary_id','Beneficiary',state.beneficiaries.map(b=>[b.id,`${b.beneficiary_code} — ${b.first_name} ${b.last_name}`]),true)}${selectField('intervention_id','Intervention',[['','Not linked'],...state.interventions.map(i=>[i.id,i.name])])}${field('assessment_date','Assessment date','date',true,dateToday())}<div class="row g-3"><div class="col-md-6">${field('household_income','Household income','number',false,'0','min="0" step="0.01"')}</div>${['food_security_score','education_score','health_score','livelihood_score'].map(x=>`<div class="col-md-6">${field(x,x.replaceAll('_',' ').replace(/\b\w/g,m=>m.toUpperCase()),'number',false,'','min="0" step="0.01"')}</div>`).join('')}</div><div class="mb-3"><label class="form-label">Narrative observation</label><textarea name="narrative" class="form-control" rows="4"></textarea></div>`;showModal('assessmentModal','Record assessment',body,'Save assessment',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());if(!data.intervention_id)delete data.intervention_id;try{const d=await api('api/assessments',{method:'POST',body:JSON.stringify(data)});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast(`Assessment saved. Overall score: ${Number(d.overall_score||0).toFixed(2)}`);await renderAssessments();}catch(err){toast(err.message,'error');}});}

async function renderIndicators(){const d=await api('api/indicators');state.indicators=d.data;$('#pageContent').innerHTML=`<div class="page-heading"><div><p class="eyebrow">IMPACT FRAMEWORK</p><h2>Indicators</h2><p>Monitor programme outputs, outcomes and impact against the latest reporting values.</p></div>${can(['admin','manager','analyst'])?'<button class="btn btn-primary" onclick="openIndicator()"><i class="bi bi-plus-lg me-2"></i>New indicator</button>':''}</div><div class="panel"><div class="table-wrap"><table class="table"><thead><tr><th>Indicator</th><th>Programme</th><th>Type</th><th>Unit</th><th>Baseline</th><th>Target</th><th>Latest</th><th>Frequency</th><th></th></tr></thead><tbody>${state.indicators.map(i=>`<tr><td><strong>${esc(i.name)}</strong><div class="text-muted" style="font-size:10px">${esc(i.description||'')}</div></td><td>${esc(i.programme_name)}</td><td>${badge(i.indicator_type)}</td><td>${esc(i.unit)}</td><td>${esc(i.baseline??'—')}</td><td>${esc(i.target??'—')}</td><td><strong>${esc(i.latest_value??'—')}</strong></td><td>${esc(i.frequency)}</td><td>${can(['admin','manager','analyst'])?`<button class="btn btn-sm btn-light" onclick="openIndicatorValue(${i.id},'${esc(i.name).replace(/'/g,'\\\'')}')"><i class="bi bi-pencil-square"></i></button>`:''}</td></tr>`).join('')||emptyRow(9,'No indicators have been configured')}</tbody></table></div></div>`;}
function openIndicatorValue(id,name){const body=`<div class="alert alert-light">Indicator: <strong>${esc(name)}</strong></div>${field('reporting_period','Reporting period','date',true,dateToday())}${field('value','Value','number',true,'0','step="0.0001" min="0"')}<div class="mb-3"><label class="form-label">Evidence note</label><textarea name="evidence_note" class="form-control" rows="3"></textarea></div>`;showModal('indicatorValueModal','Record indicator value',body,'Save value',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target).entries());data.indicator_id=id;try{await api('api/indicator-values',{method:'POST',body:JSON.stringify(data)});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Indicator value recorded.');await renderIndicators();}catch(err){toast(err.message,'error');}});}

async function renderReports(){const d=await api('api/reports/impact');state.reports=d.data;const totalBeneficiaries=state.reports.reduce((n,r)=>n+Number(r.beneficiaries||0),0);const totalCompleted=state.reports.reduce((n,r)=>n+Number(r.interventions_completed||0),0);const scores=state.reports.filter(r=>r.average_impact!==null).map(r=>Number(r.average_impact));const avg=scores.length?scores.reduce((a,b)=>a+b,0)/scores.length:0;$('#pageContent').innerHTML=`<div class="page-heading"><div><p class="eyebrow">EVIDENCE & REPORTING</p><h2>Impact reports</h2><p>Portfolio-level reach, completed delivery and measured outcome performance.</p></div><button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print report</button></div><div class="metric-grid">${metric('bi-people','Beneficiaries reached',fmtNum(totalBeneficiaries),'Across programme delivery')}${metric('bi-check2-circle','Completed interventions',fmtNum(totalCompleted),'Recorded as completed')}${metric('bi-graph-up-arrow','Portfolio average impact',avg.toFixed(2),'Average of programme scores')}${metric('bi-diagram-3','Programmes reporting',fmtNum(state.reports.length),'Programmes in report')}</div><div class="panel"><div class="panel-header"><h3>Programme impact summary</h3><span class="text-muted small">Generated ${esc(dateToday())}</span></div><div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Programme</th><th>Beneficiaries</th><th>Completed interventions</th><th>Average impact</th></tr></thead><tbody>${state.reports.map(r=>`<tr><td>${esc(r.code)}</td><td><strong>${esc(r.name)}</strong></td><td>${fmtNum(r.beneficiaries)}</td><td>${fmtNum(r.interventions_completed)}</td><td>${r.average_impact===null?'—':Number(r.average_impact).toFixed(2)}</td></tr>`).join('')||emptyRow(5,'No programme impact data is available')}</tbody></table></div></div>`;}

async function bootstrapApp(){
  try { const d=await api('api/auth/me'); state.user=d.user; state.csrf=d.csrf_token; showApp(); await navigate(); }
  catch (_) { showLogin(); }
}

window.addEventListener('hashchange', () => { if (state.user) navigate(); });
window.addEventListener('DOMContentLoaded', () => {
  $('#loginForm')?.addEventListener('submit', login);
  $('#logoutBtn')?.addEventListener('click', logout);
  $('#menuToggle')?.addEventListener('click', () => $('#sidebar').classList.add('open'));
  $('#closeSidebar')?.addEventListener('click', () => $('#sidebar').classList.remove('open'));
  $('#refreshBtn')?.addEventListener('click', () => state.user && navigate());
  bootstrapApp();
});


// Phase 1 core-completeness actions
async function editProgramme(id){
 const p=(await api(`api/programmes/${id}`)).data;
 const body=`${field('code','Programme code','text',true,p.code)}${field('name','Programme name','text',true,p.name)}<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">${esc(p.description||'')}</textarea></div><div class="row g-3"><div class="col-md-6">${field('start_date','Start date','date',true,p.start_date)}</div><div class="col-md-6">${field('end_date','End date','date',false,p.end_date||'')}</div><div class="col-md-6">${field('budget','Budget','number',false,p.budget||0,'min="0" step="0.01"')}</div><div class="col-md-6">${selectField('status','Status',[['planned','Planned'],['active','Active'],['completed','Completed'],['suspended','Suspended']])}</div></div>`;
 const el=showModal('programmeEdit','Edit programme',body,'Save changes',async e=>{e.preventDefault();try{await api(`api/programmes/${id}`,{method:'PUT',body:JSON.stringify(Object.fromEntries(new FormData(e.target).entries()))});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Programme updated.');navigate();}catch(x){toast(x.message,'error');}});
 el.querySelector('[name=status]').value=p.status;
}
async function archiveProgramme(id){if(!confirm('Archive this programme?'))return;try{await api(`api/programmes/${id}`,{method:'DELETE',body:'{}'});toast('Programme archived.');navigate();}catch(e){toast(e.message,'error');}}
async function editIntervention(id){
 const x=(await api(`api/interventions/${id}`)).data;
 const body=`${selectField('programme_id','Programme',state.programmes.map(p=>[p.id,p.code+' — '+p.name]),true)}${field('name','Name','text',true,x.name)}${field('intervention_type','Type','text',true,x.intervention_type)}${field('target_count','Target count','number',false,x.target_count,'min="0"')}<div class="row g-3"><div class="col-md-6">${field('start_date','Start date','date',true,x.start_date)}</div><div class="col-md-6">${field('end_date','End date','date',false,x.end_date||'')}</div></div>${selectField('status','Status',[['planned','Planned'],['active','Active'],['completed','Completed'],['cancelled','Cancelled']])}<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">${esc(x.description||'')}</textarea></div>`;
 const el=showModal('interventionEdit','Edit intervention',body,'Save changes',async e=>{e.preventDefault();try{await api(`api/interventions/${id}`,{method:'PUT',body:JSON.stringify(Object.fromEntries(new FormData(e.target).entries()))});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Intervention updated.');navigate();}catch(err){toast(err.message,'error');}});
 el.querySelector('[name=programme_id]').value=x.programme_id;el.querySelector('[name=status]').value=x.status;
}
async function cancelIntervention(id){if(!confirm('Cancel this intervention?'))return;try{await api(`api/interventions/${id}`,{method:'DELETE',body:'{}'});toast('Intervention cancelled.');navigate();}catch(e){toast(e.message,'error');}}
async function openIndicator(){
 const body=`${selectField('programme_id','Programme',state.programmes.map(p=>[p.id,p.code+' — '+p.name]),true)}${field('name','Indicator name','text',true)}<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="row g-3"><div class="col-md-6">${selectField('indicator_type','Type',[['output','Output'],['outcome','Outcome'],['impact','Impact']],true)}</div><div class="col-md-6">${field('unit','Unit','text',true)}</div><div class="col-md-6">${field('baseline','Baseline','number',false,'','step="0.0001"')}</div><div class="col-md-6">${field('target','Target','number',false,'','step="0.0001"')}</div></div>${selectField('frequency','Frequency',[['monthly','Monthly'],['quarterly','Quarterly'],['biannual','Biannual'],['annual','Annual'],['event','Event']],true)}`;
 showModal('indicatorCreate','Create indicator',body,'Create indicator',async e=>{e.preventDefault();try{await api('api/indicators',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(e.target).entries()))});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Indicator created.');navigate();}catch(err){toast(err.message,'error');}});
}
async function manageEnrollment(id){
 const x=(await api(`api/enrollments/${id}`)).data;
 const body=`<p><strong>${esc(x.beneficiary_name)}</strong> — ${esc(x.intervention_name)}</p>${selectField('status','Status',[['enrolled','Enrolled'],['completed','Completed'],['withdrawn','Withdrawn'],['referred','Referred']],true)}${field('exit_date','Exit date','date',false,x.exit_date||'')}${field('benefit_value','Benefit value','number',false,x.benefit_value||0,'min="0" step="0.01"')}<div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">${esc(x.notes||'')}</textarea></div>`;
 const el=showModal('enrollmentManage','Manage enrolment',body,'Save changes',async e=>{e.preventDefault();try{await api(`api/enrollments/${id}`,{method:'PUT',body:JSON.stringify(Object.fromEntries(new FormData(e.target).entries()))});bootstrap.Modal.getInstance(e.target.closest('.modal')).hide();toast('Enrolment updated.');}catch(err){toast(err.message,'error');}});
 el.querySelector('[name=status]').value=x.status;
}
