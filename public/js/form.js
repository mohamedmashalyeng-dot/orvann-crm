(() => {
  // Found from this script's own address, so the page that loads it can live in any folder.
  const API = new URL('../api/index.php', document.currentScript.src).href;
  const params = new URLSearchParams(location.search);
  const embedded = params.has('embed');
  if (embedded) document.body.classList.add('embedded');

  const form = document.getElementById('lead-form');
  const formView = document.getElementById('form-view');
  const successView = document.getElementById('success-view');
  const errorBox = document.getElementById('form-error');
  const submitBtn = form.querySelector('button[type="submit"]');

  function source() {
    if (params.get('utm_source')) return params.get('utm_source');
    if (params.get('source')) return params.get('source');
    try {
      if (document.referrer) return new URL(document.referrer).hostname;
    } catch {}
    return 'website';
  }

  function addOption(parent, value, label = value) {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    parent.append(opt);
  }

  function fillSelects(opts) {
    for (const select of form.querySelectorAll('select[data-list]')) {
      addOption(select, '', 'اختار...');
      for (const item of opts[select.dataset.list]) addOption(select, item.name);
    }
    // Governorates and countries grouped by zone
    const region = form.elements.region;
    addOption(region, '', 'اختار المكان...');
    const groups = new Map();
    for (const r of opts.regions) {
      if (!groups.has(r.zone)) {
        const g = document.createElement('optgroup');
        g.label = r.zone;
        groups.set(r.zone, g);
        region.append(g);
      }
      addOption(groups.get(r.zone), r.name);
    }
  }

  function clearErrors() {
    errorBox.hidden = true;
    for (const f of form.querySelectorAll('.field.invalid')) {
      f.classList.remove('invalid');
      f.querySelector('.err')?.remove();
    }
  }

  function showErrors(errors = {}, message) {
    let first;
    for (const [name, msg] of Object.entries(errors)) {
      const input = form.elements[name];
      const field = input?.closest('.field');
      if (!field) continue;
      field.classList.add('invalid');
      const err = document.createElement('small');
      err.className = 'err';
      err.textContent = msg;
      field.append(err);
      first ??= input;
    }
    if (message) {
      errorBox.textContent = message;
      errorBox.hidden = false;
    }
    first?.focus();
  }

  // Quick checks before sending; the server validates everything again.
  function localErrors(data) {
    const errors = {};
    if (data.full_name.trim().length < 2) errors.full_name = 'اكتب الاسم بالكامل';
    if (data.phone.replace(/[^\d٠-٩۰-۹]/g, '').length < 8) errors.phone = 'اكتب رقم موبايل صحيح';
    if (!data.job_title) errors.job_title = 'اختار المنصب';
    if (!data.region) errors.region = 'اختار المحافظة أو الدولة';
    return errors;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();
    const data = Object.fromEntries(new FormData(form));
    const errors = localErrors(data);
    if (Object.keys(errors).length) return showErrors(errors, 'من فضلك كمّل البيانات المطلوبة');

    data.source = source();
    submitBtn.disabled = true;
    submitBtn.textContent = 'جاري الإرسال...';
    try {
      const res = await fetch(API + '?r=leads', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const out = await res.json().catch(() => ({}));
      if (!res.ok) return showErrors(out.errors, out.error || 'حصل خطأ، حاول تاني');
      form.reset();
      formView.hidden = true;
      successView.hidden = false;
    } catch {
      showErrors({}, 'مفيش اتصال بالإنترنت، حاول تاني');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'إرسال البيانات';
    }
  });

  document.getElementById('again').addEventListener('click', () => {
    successView.hidden = true;
    formView.hidden = false;
  });

  // When shown inside embed.js's iframe, tell the host page our height.
  if (embedded && window.parent !== window) {
    const post = () =>
      window.parent.postMessage({ crmFormHeight: Math.ceil(document.body.getBoundingClientRect().height) }, '*');
    new ResizeObserver(post).observe(document.body);
  }

  fetch(API + '?r=options')
    .then((r) => r.json())
    .then(fillSelects)
    .catch(() => showErrors({}, 'مش قادرين نحمّل الفورم، اعمل تحديث للصفحة'));
})();
