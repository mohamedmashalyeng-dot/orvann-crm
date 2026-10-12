(() => {
  const $ = (id) => document.getElementById(id);
  const esc = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  const FACET_TITLES = {
    priority: 'الأولوية',
    status: 'الحالة',
    zone: 'المنطقة',
    region: 'المحافظة / الدولة',
    job_title: 'المنصب',
    service: 'الخدمة المطلوبة',
  };
  const CARRIERS = { '010': 'فودافون', '011': 'اتصالات', '012': 'أورانج', '015': 'WE' };

  // Every call goes to api/index.php?r=<route>, which works with or without URL rewriting,
  // and relative to this page, so the app can live at a domain root or in any folder.
  const apiUrl = (route, query = '') => `../api/index.php?r=${route}${query ? '&' + query : ''}`;

  const state = { filters: {}, q: '', from: '', to: '', sort: 'recent', page: 1 };
  const closedFacets = new Set(matchMedia('(max-width: 960px)').matches
    ? ['status', 'zone', 'region', 'job_title', 'service']
    : ['region', 'service']);
  let opts = null;
  let user = '';
  let lastTotal = null;
  let current = null;
  let setupProblem = ''; // what the server is missing, when it cannot run at all

  const dlg = $('lead-dialog');
  const dateFmt = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' });
  const fmtDate = (s) => (s ? dateFmt.format(new Date(s.replace(' ', 'T') + 'Z')) : '');

  const label = (facet, value) =>
    facet === 'priority' ? opts.priorities[value] ?? value
    : facet === 'status' ? opts.statuses[value] ?? value
    : value;

  const priorityBadge = (p) => `<span class="badge p-${esc(p)}">${esc(label('priority', p))}</span>`;
  const statusBadge = (s) => `<span class="badge s-${esc(s)}">${esc(label('status', s))}</span>`;
  const carrier = (phone) => (/^01\d{9}$/.test(phone) ? CARRIERS[phone.slice(0, 3)] ?? '' : '');
  const waNumber = (phone) => {
    const digits = phone.replace(/\D/g, '');
    return /^01\d{9}$/.test(digits) ? '20' + digits.slice(1) : digits;
  };
  const times = (n) => (n === 2 ? 'مرتين' : `${n} مرات`);
  const dash = '<span class="muted">—</span>';

  async function api(url, { method = 'GET', body } = {}) {
    const res = await fetch(url, {
      method,
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 401) {
      showLogin();
      throw new Error(data.error || 'لازم تسجل دخول');
    }
    if (!res.ok) throw new Error(data.error || 'حصل خطأ');
    return data;
  }

  function toast(msg) {
    const t = $('toast');
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => (t.hidden = true), 3500);
  }

  function qs(extra = {}) {
    const p = new URLSearchParams();
    const all = { ...state.filters, q: state.q, from: state.from, to: state.to, sort: state.sort, page: state.page, ...extra };
    for (const [k, v] of Object.entries(all)) if (v) p.set(k, v);
    return p.toString();
  }

  // ---------- list ----------

  async function load({ notify = false } = {}) {
    const [list, stats] = await Promise.all([
      api(apiUrl('leads', qs())),
      api(apiUrl('stats', qs({ page: '', sort: '' }))),
    ]);
    if (notify && lastTotal !== null && stats.totals.total > lastTotal) {
      toast(`وصل ${stats.totals.total - lastTotal} عميل جديد`);
    }
    lastTotal = stats.totals.total;
    state.page = list.page;
    renderStats(stats.totals);
    renderFacets(stats.facets);
    renderChips();
    renderRows(list);
    $('export-btn').href = apiUrl('export.csv', qs({ page: '' }));
  }

  function renderStats(t) {
    const cards = [
      ['إجمالي العملاء', t.total],
      ['سجلوا النهارده', t.today],
      ['آخر 7 أيام', t.week],
      ['جداد لسه محدش كلمهم', t.new],
      ['أولوية عالية مفتوحة', t.hot, 'hot'],
      ['تم البيع', t.won],
    ];
    $('stats').innerHTML = cards
      .map(([name, value, cls]) => `<div class="stat ${cls ?? ''}"><div class="label">${name}</div><div class="value">${value ?? 0}</div></div>`)
      .join('');
  }

  function renderFacets(facets) {
    const fixedOrder = { priority: Object.keys(opts.priorities), status: Object.keys(opts.statuses) };
    $('facets').innerHTML = Object.entries(FACET_TITLES).map(([facet, title]) => {
      const items = facets[facet].filter((i) => i.value);
      if (fixedOrder[facet]) items.sort((a, b) => fixedOrder[facet].indexOf(a.value) - fixedOrder[facet].indexOf(b.value));
      const active = state.filters[facet];
      if (active && !items.some((i) => i.value === active)) items.unshift({ value: active, count: 0 });

      const list = items.length
        ? items.map((i) => {
            const text = facet === 'priority' ? priorityBadge(i.value) : facet === 'status' ? statusBadge(i.value) : esc(i.value);
            return `<li><button type="button" class="${i.value === active ? 'active' : ''}" data-facet="${facet}" data-value="${esc(i.value)}">
              <span>${text}</span><span class="count">${i.count}</span></button></li>`;
          }).join('')
        : '<li class="empty">لا يوجد</li>';
      return `<details class="facet" data-facet="${facet}" ${closedFacets.has(facet) ? '' : 'open'}>
        <summary>${title}</summary><ul>${list}</ul></details>`;
    }).join('');
  }

  function renderChips() {
    const chips = Object.entries(state.filters).map(([k, v]) => [k, `${FACET_TITLES[k] ?? k}: ${label(k, v)}`]);
    if (state.q) chips.push(['q', `بحث: ${state.q}`]);
    if (state.from) chips.push(['from', `من ${state.from}`]);
    if (state.to) chips.push(['to', `إلى ${state.to}`]);
    $('chips').innerHTML = chips
      .map(([k, text]) => `<span class="chip">${esc(text)}<button type="button" data-clear="${k}" aria-label="إزالة">×</button></span>`)
      .join('') + (chips.length > 1 ? '<button type="button" class="btn sm ghost" data-clear="all">مسح الكل</button>' : '');
  }

  function renderRows({ items, total, page, pages }) {
    $('rows').innerHTML = items.map((l) => `
      <tr data-id="${l.id}">
        <td><div class="name"><bdi>${esc(l.full_name)}</bdi></div>${l.company ? `<div class="sub"><bdi>${esc(l.company)}</bdi></div>` : ''}</td>
        <td class="phone">${l.phone ? `<bdi dir="ltr">${esc(l.phone)}</bdi>` : dash}${carrier(l.phone) ? `<div class="sub">${carrier(l.phone)}</div>` : ''}${!l.phone && l.email ? `<div class="sub"><bdi dir="ltr">${esc(l.email)}</bdi></div>` : ''}</td>
        <td>${l.job_title ? esc(l.job_title) : dash}</td>
        <td>${l.region ? esc(l.region) : dash}<div class="sub">${esc(l.zone)}</div></td>
        <td>${priorityBadge(l.priority)}</td>
        <td>${statusBadge(l.status)}</td>
        <td><div>${fmtDate(l.last_submitted_at)}</div>${l.submissions > 1 ? `<div class="sub">سجّل ${times(l.submissions)}</div>` : ''}</td>
      </tr>`).join('');
    const filtered = Object.keys(state.filters).length > 0 || state.q || state.from || state.to;
    $('empty').textContent = filtered ? 'مفيش عملاء بالفلاتر دي' : 'لسه مفيش عملاء. أول عميل يسجل هيظهر هنا.';
    $('empty').hidden = items.length > 0;

    const start = total ? (page - 1) * 50 + 1 : 0;
    $('pager').innerHTML = `
      <span>عرض ${start}–${start + items.length - (total ? 1 : 0)} من ${total}</span>
      <span class="btns">
        <button type="button" class="btn sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>السابق</button>
        <button type="button" class="btn sm" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>التالي</button>
      </span>`;
  }

  const reload = () => load().catch((e) => toast(e.message));

  $('facets').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-facet]');
    if (!btn) return;
    const { facet, value } = btn.dataset;
    if (state.filters[facet] === value) delete state.filters[facet];
    else state.filters[facet] = value;
    state.page = 1;
    reload();
  });

  $('facets').addEventListener('toggle', (e) => {
    const facet = e.target.dataset?.facet;
    if (facet) e.target.open ? closedFacets.delete(facet) : closedFacets.add(facet);
  }, true);

  $('chips').addEventListener('click', (e) => {
    const key = e.target.closest('[data-clear]')?.dataset.clear;
    if (!key) return;
    if (key === 'all') {
      Object.assign(state, { filters: {}, q: '', from: '', to: '' });
      $('search').value = $('from').value = $('to').value = '';
    } else if (key in state.filters) {
      delete state.filters[key];
    } else {
      state[key] = '';
      $({ q: 'search', from: 'from', to: 'to' }[key]).value = '';
    }
    state.page = 1;
    reload();
  });

  $('pager').addEventListener('click', (e) => {
    const page = e.target.closest('button[data-page]')?.dataset.page;
    if (!page) return;
    state.page = Number(page);
    reload();
  });

  let searchTimer;
  $('search').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.q = e.target.value.trim();
      state.page = 1;
      reload();
    }, 300);
  });
  for (const id of ['from', 'to', 'sort']) {
    $(id).addEventListener('change', (e) => {
      state[id] = e.target.value;
      state.page = 1;
      reload();
    });
  }

  $('rows').addEventListener('click', (e) => {
    const id = e.target.closest('tr[data-id]')?.dataset.id;
    if (id) openLead(id).catch((err) => toast(err.message));
  });

  $('refresh-btn').addEventListener('click', reload);

  // ---------- lead dialog ----------

  async function openLead(id) {
    renderLead(await api(apiUrl('leads/' + id)));
    if (!dlg.open) dlg.showModal();
  }

  function renderLead({ lead, notes }) {
    current = lead;
    $('d-name').innerHTML = `<bdi>${esc(lead.full_name)}</bdi>`;
    $('d-priority-badge').innerHTML = priorityBadge(lead.priority);

    $('d-actions').innerHTML = (lead.phone ? `
      <a class="btn sm" href="tel:${esc(lead.phone)}">📞 اتصال <bdi dir="ltr">${esc(lead.phone)}</bdi></a>
      <a class="btn sm" href="https://wa.me/${esc(waNumber(lead.phone))}" target="_blank" rel="noopener">واتساب</a>
      <button type="button" class="btn sm ghost" id="copy-phone">نسخ الرقم</button>` : '<span class="muted">العميل مسجلش رقم موبايل</span>') +
      (lead.email ? `<a class="btn sm" href="mailto:${esc(lead.email)}">✉ إيميل <bdi dir="ltr">${esc(lead.email)}</bdi></a>` : '');

    $('d-status').innerHTML = Object.entries(opts.statuses)
      .map(([k, v]) => `<option value="${k}" ${k === lead.status ? 'selected' : ''}>${esc(v)}</option>`).join('');

    $('d-priority').innerHTML =
      `<option value="auto" ${lead.priority_locked ? '' : 'selected'}>تلقائي حسب النقاط (${esc(opts.priorities[lead.auto_priority])})</option>` +
      Object.entries(opts.priorities)
        .map(([k, v]) => `<option value="${k}" ${lead.priority_locked && k === lead.priority ? 'selected' : ''}>${esc(v)} — يدوي</option>`).join('');
    $('d-score').textContent = `نقاط العميل: ${lead.score} (من المنصب والخدمة والميزانية والتوقيت)`;

    const rows = [
      ['المنصب', lead.job_title],
      ['الشركة', lead.company],
      ['المحافظة / الدولة', lead.region && `${lead.region} — ${lead.zone}`],
      ['المدينة / المنطقة', lead.city],
      ['الخدمة المطلوبة', lead.service],
      ['الميزانية', lead.budget],
      ['محتاج الخدمة', lead.timeline],
      ['البريد الإلكتروني', lead.email],
      ['المصدر', lead.source],
      ['شبكة الموبايل', carrier(lead.phone)],
    ];
    $('d-details').innerHTML =
      rows.map(([k, v]) => `<div><dt>${k}</dt><dd>${v ? `<bdi>${esc(v)}</bdi>` : dash}</dd></div>`).join('') +
      (lead.message ? `<div class="full"><dt>رسالة العميل</dt><dd class="message" dir="auto">${esc(lead.message)}</dd></div>` : '');

    renderNotes(notes);
    $('d-meta').textContent = `أول تسجيل: ${fmtDate(lead.created_at)}` + (lead.submissions > 1 ? ` · سجّل ${times(lead.submissions)}` : '');
  }

  function renderNotes(notes) {
    $('d-notes').innerHTML = notes.length
      ? notes.map((n) => `<li><div class="meta"><bdi>${esc(n.author)}</bdi> · <bdi>${fmtDate(n.created_at)}</bdi></div><div class="body" dir="auto">${esc(n.body)}</div></li>`).join('')
      : '<li class="muted">مفيش ملاحظات لسه</li>';
  }

  async function patchLead(body) {
    try {
      renderLead(await api(apiUrl('leads/' + current.id), { method: 'POST', body }));
      load().catch(() => {});
    } catch (e) {
      toast(e.message);
    }
  }

  $('d-status').addEventListener('change', (e) => patchLead({ status: e.target.value }));
  $('d-priority').addEventListener('change', (e) => patchLead({ priority: e.target.value }));

  $('d-actions').addEventListener('click', async (e) => {
    if (e.target.id !== 'copy-phone') return;
    try {
      await navigator.clipboard.writeText(current.phone);
      toast('تم نسخ الرقم');
    } catch {
      toast(current.phone);
    }
  });

  $('note-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const box = e.target.elements.body;
    if (!box.value.trim()) return box.focus();
    try {
      const { notes } = await api(apiUrl(`leads/${current.id}/notes`), { method: 'POST', body: { body: box.value } });
      box.value = '';
      renderNotes(notes);
    } catch (err) {
      toast(err.message);
    }
  });

  $('d-delete').addEventListener('click', async () => {
    if (!confirm(`متأكد إنك عايز تحذف "${current.full_name}"؟ مش هتقدر ترجعه.`)) return;
    try {
      await api(apiUrl(`leads/${current.id}/delete`), { method: 'POST', body: {} });
      dlg.close();
      toast('تم الحذف');
      reload();
    } catch (err) {
      toast(err.message);
    }
  });

  $('d-close').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => {
    if (e.target === dlg) dlg.close();
  });

  // ---------- auth ----------

  function showLogin() {
    user = '';
    if (dlg.open) dlg.close();
    $('app-view').hidden = true;
    $('login-view').hidden = false;
    $('login-form').elements.username.focus();
  }

  async function start(name) {
    user = name;
    opts ??= await api(apiUrl('options'));
    $('user-name').textContent = name;
    $('login-view').hidden = true;
    $('app-view').hidden = false;
    checkDataFolder();
    await load();
  }

  $('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const err = $('login-error');
    err.hidden = true;
    const res = await fetch(apiUrl('login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: f.elements.username.value, password: f.elements.password.value }),
    }).catch(() => null);
    const data = (await res?.json().catch(() => ({}))) ?? {};
    if (!res?.ok) {
      err.textContent = setupProblem || data.error || 'مفيش اتصال بالسيرفر';
      err.hidden = false;
      return;
    }
    f.reset();
    start(data.user).catch((e2) => toast(e2.message));
  });

  $('logout-btn').addEventListener('click', async () => {
    await fetch(apiUrl('logout'), { method: 'POST' }).catch(() => {});
    showLogin();
  });

  // New leads show up without a manual refresh.
  setInterval(() => {
    if (user && !document.hidden && !dlg.open) load({ notify: true }).catch(() => {});
  }, 30000);

  // After an upload: say plainly what the server is missing, instead of a login that never works.
  const SETUP_PROBLEMS = {
    pdo_sqlite: 'الاستضافة مش مفعّل فيها SQLite. من hPanel افتح PHP Configuration ثم Extensions وفعّل pdo_sqlite.',
    settings: 'ملف الإعدادات app/settings.php مش موجود على السيرفر. ارفع ملف crm-upload.zip الكامل وفكّه في نفس الفولدر.',
    data_dir: 'السيرفر مش قادر يكتب في فولدر data. خلي صلاحيات الفولدر 755.',
    api: 'مش قادر أوصل للـ API. اتأكد إن فولدر api وفولدر app اترفعوا كاملين.',
  };
  fetch(apiUrl('health'))
    .then((res) => res.json())
    .then((health) => health.ok || Promise.reject(health.problem))
    .catch((problem) => {
      setupProblem = SETUP_PROBLEMS[problem] ?? SETUP_PROBLEMS.api;
      $('login-error').textContent = setupProblem;
      $('login-error').hidden = false;
    });

  // When the customer database is inside the website folder, warn loudly if the server would
  // hand it out. Nothing to probe when it is kept outside; and the probe gives up after a few
  // seconds, because some host networks leave a refused request hanging.
  async function checkDataFolder() {
    try {
      const me = await api(apiUrl('me'));
      if (me.data_outside_web_folder) return;
      const res = await fetch('../data/access-check.txt', { cache: 'no-store', signal: AbortSignal.timeout(8000) });
      if (res.ok && (await res.text()).includes('CRM-DATA-EXPOSED')) $('exposed-warning').hidden = false;
    } catch {}
  }

  api(apiUrl('me'))
    .then((me) => start(me.user))
    .catch(() => {});
})();
