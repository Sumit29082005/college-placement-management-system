const STATUSES = ['applied', 'shortlisted', 'interviewed', 'selected', 'rejected'];
const $ = id => document.getElementById(id);
const formData = f => Object.fromEntries(new FormData(f));

async function loadStats() {
  const s = await api('admin', 'stats');
  const cards = [
    [s.students, 'Registered students'], [s.companies, 'Companies'], [s.open_drives, 'Open drives'],
    [s.applications, 'Applications'], [s.placed, 'Students placed'], [s.placement_pct + '%', 'Placement rate'],
    ['₹' + s.highest_lpa, 'Highest package (LPA)'], ['₹' + s.average_lpa, 'Average package (LPA)'],
  ];
  $('stats').innerHTML = cards.map(([v, l]) => `<div class="card stat"><b>${esc(v)}</b><span>${l}</span></div>`).join('');
  $('deptBars').innerHTML = s.departments.length ? s.departments.map(d => {
    const pct = d.total ? Math.round(d.placed * 100 / d.total) : 0;
    return `<div class="row"><span>${esc(d.department)}</span><span class="muted">${d.placed}/${d.total} (${pct}%)</span></div>
            <div class="bar"><i style="width:${pct}%"></i></div>`;
  }).join('') : '<p class="muted">No student data yet.</p>';
}

async function loadStudents() {
  const { students } = await api('admin', 'students');
  $('studentsBody').innerHTML = students.map(s => `<tr>
    <td>${esc(s.roll_no)}</td><td>${esc(s.name)}</td><td>${esc(s.department)}</td><td>${esc(s.cgpa)}</td>
    <td>${esc(s.backlogs)}</td><td>${esc(s.graduation_year)}</td><td>${esc(s.email)}</td>
    <td>${Number(s.placed) ? badge('selected') : '-'}</td>
    <td><button class="btn sm danger" data-del-student="${s.id}">Delete</button></td></tr>`).join('')
    || '<tr><td colspan="9" class="muted">No students registered.</td></tr>';
}

async function loadCompanies() {
  const { companies } = await api('admin', 'companies');
  $('companiesBody').innerHTML = companies.map(c => `<tr>
    <td>${esc(c.name)}</td><td>${esc(c.industry)}</td><td>${esc(c.location)}</td><td>${esc(c.website)}</td>
    <td><button class="btn sm danger" data-del-company="${c.id}">Delete</button></td></tr>`).join('')
    || '<tr><td colspan="5" class="muted">No companies yet.</td></tr>';
  $('driveCompany').innerHTML = companies.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
}

async function loadDrives() {
  await loadCompanies();
  const { drives } = await api('admin', 'drives');
  $('drivesBody').innerHTML = drives.map(d => `<tr>
    <td>${esc(d.company)}</td><td>${esc(d.job_title)}</td><td>${esc(d.package_lpa)}</td>
    <td>CGPA ≥ ${esc(d.min_cgpa)}, ≤${esc(d.max_backlogs)} BL, ${esc(d.allowed_departments)}</td>
    <td>${fmtDate(d.deadline)}</td><td>${esc(d.applicants)}</td><td>${badge(d.status)}</td>
    <td><button class="btn sm ghost" data-toggle-drive="${d.id}">${d.status === 'open' ? 'Close' : 'Reopen'}</button>
        <button class="btn sm danger" data-del-drive="${d.id}">Delete</button></td></tr>`).join('')
    || '<tr><td colspan="8" class="muted">No drives yet.</td></tr>';
}

async function loadApplications() {
  const { drives } = await api('admin', 'drives');
  const current = $('appDrive').value;
  $('appDrive').innerHTML = '<option value="">All drives</option>' +
    drives.map(d => `<option value="${d.id}">${esc(d.company)} - ${esc(d.job_title)}</option>`).join('');
  $('appDrive').value = current;
  const { applications } = await api('admin', 'applications', null, current ? { drive_id: current } : {});
  $('appsBody').innerHTML = applications.map(a => `<tr>
    <td>${esc(a.name)}</td><td>${esc(a.roll_no)}</td><td>${esc(a.department)}</td><td>${esc(a.cgpa)}</td>
    <td>${esc(a.company)}</td><td>${esc(a.job_title)}</td><td>${fmtDate(a.applied_at)}</td>
    <td><select data-status="${a.id}">${STATUSES.map(s => `<option ${s === a.status ? 'selected' : ''}>${s}</option>`).join('')}</select></td></tr>`).join('')
    || '<tr><td colspan="8" class="muted">No applications.</td></tr>';
}

// ---- Event delegation for buttons / selects ----
document.addEventListener('click', safe(async e => {
  const t = e.target, ds = t.dataset || {};
  if (ds.delStudent && confirm('Delete this student and all their applications?')) {
    await api('admin', 'delete_student', { id: +ds.delStudent }); toast('Student removed'); loadStudents();
  } else if (ds.delCompany && confirm('Delete company and all its drives?')) {
    await api('admin', 'delete_company', { id: +ds.delCompany }); toast('Company deleted'); loadCompanies();
  } else if (ds.delDrive && confirm('Delete this drive and its applications?')) {
    await api('admin', 'delete_drive', { id: +ds.delDrive }); toast('Drive deleted'); loadDrives();
  } else if (ds.toggleDrive) {
    await api('admin', 'toggle_drive', { id: +ds.toggleDrive }); loadDrives();
  }
}));

document.addEventListener('change', safe(async e => {
  if (e.target.dataset?.status) {
    await api('admin', 'update_status', { id: +e.target.dataset.status, status: e.target.value });
    toast('Status updated');
  } else if (e.target.id === 'appDrive') loadApplications();
}));

$('exportBtn').addEventListener('click', () => {
  const id = $('appDrive').value;
  location.href = `${API}admin.php?action=export_csv` + (id ? `&drive_id=${encodeURIComponent(id)}` : '');
});

$('companyForm').addEventListener('submit', safe(async e => {
  e.preventDefault();
  const r = await api('admin', 'add_company', formData(e.target));
  toast(r.message); e.target.reset(); loadCompanies();
}));

$('driveForm').addEventListener('submit', safe(async e => {
  e.preventDefault();
  const r = await api('admin', 'add_drive', formData(e.target));
  toast(r.message); e.target.reset(); loadDrives();
}));

document.addEventListener('tabchange', safe(e => ({
  overview: loadStats, students: loadStudents, companies: loadCompanies,
  drives: loadDrives, applications: loadApplications,
}[e.detail]?.())));

(async () => {
  initTabs();
  if (await guard('admin')) safe(loadStats)();
})();
