const API = 'api/';

async function api(file, action, data = null, params = {}) {
  const qs = new URLSearchParams({ action, ...params });
  const opts = { credentials: 'same-origin' };
  if (data) {
    opts.method = 'POST';
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(data);
  }
  const res = await fetch(`${API}${file}.php?${qs}`, opts);
  const json = await res.json().catch(() => ({ error: 'Invalid server response' }));
  if (!res.ok) {
    if (res.status === 401 && !location.pathname.endsWith('index.html')) location.href = 'index.html';
    throw new Error(json.error || 'Request failed');
  }
  return json;
}

// Escape text before putting it into innerHTML (prevents XSS)
function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function toast(msg, isError = false) {
  let t = document.getElementById('toast');
  if (!t) { t = document.createElement('div'); t.id = 'toast'; document.body.appendChild(t); }
  t.textContent = msg;
  t.className = 'show' + (isError ? ' err' : '');
  clearTimeout(t._h);
  t._h = setTimeout(() => (t.className = ''), 2800);
}

// Wrap async handlers so errors show as toasts
const safe = fn => async (...a) => { try { await fn(...a); } catch (e) { toast(e.message, true); } };

async function guard(role) {
  const { user } = await api('auth', 'me');
  if (!user || user.role !== role) { location.href = 'index.html'; return null; }
  document.getElementById('who').textContent = user.name;
  return user;
}

async function logout() {
  await api('auth', 'logout', {});
  location.href = 'index.html';
}

function initTabs() {
  const btns = document.querySelectorAll('.tabs button');
  btns.forEach(b => b.addEventListener('click', () => {
    btns.forEach(x => x.classList.toggle('active', x === b));
    document.querySelectorAll('.tab').forEach(s => s.classList.toggle('hidden', s.id !== 'tab-' + b.dataset.tab));
    document.dispatchEvent(new CustomEvent('tabchange', { detail: b.dataset.tab }));
  }));
}

const fmtDate = d => (d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-');
const badge = s => `<span class="badge ${esc(s)}">${esc(s)}</span>`;
