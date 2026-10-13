let profile;

async function loadDrives() {
  const { drives } = await api('student', 'drives');
  const box = document.getElementById('drives');
  if (!drives.length) { box.innerHTML = '<p class="muted">No drives announced yet.</p>'; return; }
  box.innerHTML = drives.map(d => {
    let action;
    if (d.applied) action = '<button class="btn sm" disabled>Applied ✓</button>';
    else if (d.status !== 'open' || d.expired) action = '<button class="btn sm" disabled>Closed</button>';
    else if (!d.eligible) action = `<button class="btn sm" disabled>Not eligible</button><div class="muted">${esc(d.reason)}</div>`;
    else action = `<button class="btn sm" data-apply="${d.id}">Apply now</button>`;
    return `<div class="card">
      <div class="row"><h3>${esc(d.company)}</h3>${badge(d.status)}</div>
      <div><b>${esc(d.job_title)}</b> · ₹${esc(d.package_lpa)} LPA</div>
      <p class="muted">${esc(d.description)}</p>
      <div class="muted">📍 ${esc(d.location)} · Min CGPA ${esc(d.min_cgpa)} · Backlogs ≤ ${esc(d.max_backlogs)}<br>
      Depts: ${esc(d.allowed_departments)}<br>Apply by ${fmtDate(d.deadline)} · Drive on ${fmtDate(d.drive_date)}</div>
      <div style="margin-top:.6rem">${action}</div></div>`;
  }).join('');
}

async function loadApplications() {
  const { applications } = await api('student', 'applications');
  document.getElementById('appsBody').innerHTML = applications.length
    ? applications.map(a => `<tr><td>${esc(a.company)}</td><td>${esc(a.job_title)}</td><td>₹${esc(a.package_lpa)} LPA</td>
        <td>${fmtDate(a.drive_date)}</td><td>${fmtDate(a.applied_at)}</td><td>${badge(a.status)}</td></tr>`).join('')
    : '<tr><td colspan="6" class="muted">You have not applied to any drive yet.</td></tr>';
}

async function loadProfile() {
  ({ profile } = await api('student', 'profile'));
  document.getElementById('profileInfo').innerHTML = `
    <h2>${esc(profile.name)}</h2>
    <div class="grid">
      <div class="stat"><b>${esc(profile.cgpa)}</b><span>CGPA</span></div>
      <div class="stat"><b>${esc(profile.backlogs)}</b><span>Backlogs</span></div>
      <div class="stat"><b>${esc(profile.roll_no)}</b><span>Roll no · ${esc(profile.department)}</span></div>
      <div class="stat"><b>${esc(profile.graduation_year)}</b><span>Graduation year</span></div>
    </div><p class="muted">${esc(profile.email)}</p>`;
  const f = document.getElementById('profileForm');
  f.phone.value = profile.phone || '';
  f.skills.value = profile.skills || '';
  f.resume_link.value = profile.resume_link || '';
}

document.addEventListener('click', safe(async e => {
  const id = e.target.dataset?.apply;
  if (!id) return;
  const r = await api('student', 'apply', { drive_id: Number(id) });
  toast(r.message);
  loadDrives();
}));

document.getElementById('profileForm').addEventListener('submit', safe(async e => {
  e.preventDefault();
  const r = await api('student', 'update_profile', Object.fromEntries(new FormData(e.target)));
  toast(r.message);
  loadProfile();
}));

document.addEventListener('tabchange', safe(e => {
  if (e.detail === 'drives') return loadDrives();
  if (e.detail === 'applications') return loadApplications();
  if (e.detail === 'profile') return loadProfile();
}));

(async () => {
  initTabs();
  if (await guard('student')) safe(loadDrives)();
})();
