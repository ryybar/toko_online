(() => {
  const api = 'api/auth.php';
  let csrf = '';
  let activeTerms = {};
  let ownerExists = null;
  const status = document.querySelector('[data-auth-status]');
  const setMessage = (target, message, kind = 'error') => {
    const box = target || status;
    if (!box) return;
    box.textContent = message;
    box.hidden = !message;
    box.classList.toggle('is-error', kind === 'error');
    box.classList.toggle('is-success', kind === 'success');
  };
  const readJson = async response => {
    const body = await response.text();
    const contentType = response.headers.get('content-type') || 'tidak dicantumkan';
    if (!body.trim()) throw new Error(`Respons server kosong (HTTP ${response.status}). Periksa Vercel → Deployments → Runtime Logs.`);
    try { return JSON.parse(body); }
    catch {
      throw new Error(`Endpoint PHP tidak mengirim JSON (HTTP ${response.status}; Content-Type: ${contentType}). Jika ini di Vercel, pastikan vercel.json ikut ter-deploy dari Root Directory yang benar dan buka Deployments → Runtime Logs. XAMPP hanya untuk versi lokal.`);
    }
  };

  const applyRegistrationState = form => {
    const select = form.querySelector('[data-role-select]');
    if (!select) return;
    const role = select.value;
    const isRegister = form.dataset.mode === 'register';
    const ownerOption = select.querySelector('[data-owner-signup-option]');
    if (ownerOption && isRegister && ownerExists === true) {
      ownerOption.disabled = true;
      ownerOption.hidden = true;
      if (isRegister && role === 'owner') select.value = 'customer';
    }
    const selectedRole = select.value;
    const ownerClosed = selectedRole === 'owner' && ownerExists === true;
    form.querySelectorAll('[data-owner-registration-note]').forEach(note => { note.hidden = !(selectedRole === 'owner' && ownerExists === false); });
    form.querySelectorAll('[data-account-registration-fields], [data-seller-registration-fields], [data-owner-registration-fields]').forEach(fields => {
      let show = false;
      if (fields.hasAttribute('data-account-registration-fields')) show = selectedRole === 'customer' || selectedRole === 'seller';
      if (fields.hasAttribute('data-seller-registration-fields')) show = selectedRole === 'seller';
      if (fields.hasAttribute('data-owner-registration-fields')) show = selectedRole === 'owner' && ownerExists === false;
      fields.hidden = !show;
      fields.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = !show; });
    });
    form.querySelectorAll('[data-terms-consent]').forEach(box => {
      const terms = activeTerms[selectedRole];
      const applicable = selectedRole === 'customer' || selectedRole === 'seller';
      box.hidden = !applicable;
      const checkbox = box.querySelector('input[name="terms_accepted"]');
      const versionInput = box.querySelector('[data-terms-id]');
      if (checkbox) { checkbox.disabled = !applicable || !terms; checkbox.required = applicable; }
      if (versionInput) { versionInput.disabled = !applicable || !terms; versionInput.value = terms?.id || ''; }
      box.querySelectorAll('[data-terms-audience]').forEach(preview => { preview.hidden = preview.dataset.termsAudience !== selectedRole; });
      const versionLabel = box.querySelector('[data-terms-version]');
      if (versionLabel) versionLabel.textContent = terms?.version ? `(versi ${selectedRole === 'seller' ? 'penjual' : 'pembeli'} v${terms.version})` : '';
    });
    form.querySelectorAll('[data-submit-register]').forEach(button => { button.hidden = ownerClosed; });
  };

  fetch(api, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
    .then(async response => { const data = await readJson(response); if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`); return data; })
    .then(data => {
      csrf = data.csrf || '';
      activeTerms = data.terms || {};
      ownerExists = Boolean(data.ownerExists);
      document.querySelectorAll('[data-terms-audience]').forEach(preview => {
        const terms = activeTerms[preview.dataset.termsAudience];
        if (!terms) preview.textContent = 'Syarat belum tersedia. Jalankan migrasi database terlebih dahulu.';
        else preview.textContent = terms.body || '';
      });
      document.querySelectorAll('[data-owner-signup-link]').forEach(link => { link.hidden = ownerExists; });
      document.querySelectorAll('[data-owner-login-link]').forEach(link => { link.hidden = !ownerExists; });
      document.querySelectorAll('[data-owner-signup-note]').forEach(note => { note.hidden = !ownerExists; });
      document.querySelectorAll('[data-role-select]').forEach(select => applyRegistrationState(select.closest('[data-auth-form]')));
      if (data.signedIn) location.href = data.redirect || 'dashboard.php';
    })
    .catch(error => setMessage(status, error.message || 'Tidak dapat terhubung ke server. Buka website melalui hosting atau XAMPP.'));

  document.querySelectorAll('[data-role-select]').forEach(select => select.addEventListener('change', () => applyRegistrationState(select.closest('[data-auth-form]'))));
  document.querySelectorAll('[data-auth-form]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    const message = form.querySelector('[data-form-message]');
    const roleSelect = form.querySelector('[data-role-select]');
    const role = roleSelect?.value || 'customer';
    if (form.dataset.mode === 'register' && role === 'owner' && ownerExists !== false) {
      setMessage(message, ownerExists ? 'Akun owner sudah terdaftar. Silakan masuk sebagai owner.' : 'Status akun owner belum termuat. Muat ulang halaman.');
      return;
    }
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.role = role;
    if (form.dataset.mode === 'register' && role === 'owner') {
      payload.name = payload.owner_name || '';
      payload.email = payload.owner_email || '';
      payload.phone = payload.owner_phone || '';
      payload.password = payload.owner_password || '';
      payload.password_confirmation = payload.owner_password_confirmation || '';
    }
    const terms = activeTerms[role];
    if (form.dataset.mode === 'register' && (role === 'customer' || role === 'seller')) {
      if (!terms) { setMessage(message, 'Syarat akun belum termuat. Pastikan migrasi database sudah dijalankan.'); return; }
      payload.terms_document_id = terms.id;
    }
    payload.action = form.dataset.mode;
    payload.csrf = csrf;
    submit.disabled = true;
    submit.classList.add('is-loading');
    const oldMarkup = submit.innerHTML;
    submit.setAttribute('aria-busy', 'true');
    submit.textContent = 'Memproses…';
    setMessage(message, '');
    try {
      const response = await fetch(api, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify(payload) });
      const result = await readJson(response);
      if (!response.ok || !result.ok) throw new Error(result.message || 'Permintaan belum berhasil.');
      setMessage(message, `${result.message} Mengalihkan…`, 'success');
      window.setTimeout(() => { location.href = result.redirect || 'dashboard.php'; }, 650);
    } catch (error) {
      setMessage(message, error.message || 'Terjadi kendala. Silakan coba lagi.');
      submit.disabled = false;
      submit.classList.remove('is-loading');
      submit.removeAttribute('aria-busy');
      submit.innerHTML = oldMarkup;
    }
  }));

  document.querySelectorAll('[data-modal-open]').forEach(button => button.addEventListener('click', () => {
    const modal = document.querySelector(`[data-modal="${button.dataset.modalOpen}"]`);
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('modal-visible');
    modal.querySelector('input,select,button,a')?.focus();
  }));
  document.querySelectorAll('[data-modal-close]').forEach(button => button.addEventListener('click', () => {
    button.closest('[data-modal]').hidden = true;
    if (!document.querySelector('[data-modal]:not([hidden])')) document.body.classList.remove('modal-visible');
  }));
  document.querySelectorAll('[data-modal]').forEach(modal => modal.addEventListener('click', event => {
    if (event.target === modal) { modal.hidden = true; document.body.classList.remove('modal-visible'); }
  }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { document.querySelectorAll('[data-modal]:not([hidden])').forEach(modal => { modal.hidden = true; }); document.body.classList.remove('modal-visible'); }
  });
  document.querySelectorAll('[data-modal-tab]').forEach(button => button.addEventListener('click', () => {
    const current = button.closest('[data-modal]');
    const next = document.querySelector(`[data-modal="${button.dataset.modalTab}"]`);
    if (current) current.hidden = true;
    if (next) { next.hidden = false; next.querySelector('input,select,button,a')?.focus(); }
  }));
  document.querySelectorAll('[data-auth-form] [data-role-select]').forEach(select => applyRegistrationState(select.closest('[data-auth-form]')) );
  document.querySelectorAll('[data-default-role]').forEach(button => button.addEventListener('click', () => {
    const role = button.dataset.defaultRole;
    const modalName = button.dataset.modalOpen;
    window.setTimeout(() => document.querySelectorAll(`[data-modal="${modalName}"] [data-role-select]`).forEach(select => { select.value = role; select.dispatchEvent(new Event('change')); }), 0);
  }));
})();
