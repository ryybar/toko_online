(() => {
  const api = 'api/auth.php';
  let csrf = '';
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
    if (!body.trim()) throw new Error(`Respons server kosong (HTTP ${response.status}). Pastikan Apache dan MySQL XAMPP aktif; lihat log PHP jika masalah berlanjut.`);
    try { return JSON.parse(body); }
    catch { throw new Error(`Server PHP tidak mengirim JSON yang valid (HTTP ${response.status}). Periksa koneksi database dan log PHP XAMPP.`); }
  };

  fetch(api, { credentials: 'same-origin' })
    .then(async response => { const data = await readJson(response); if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`); return data; })
    .then(data => {
      csrf = data.csrf || '';
      document.querySelectorAll('[data-auth-form][data-mode="register"]').forEach(form => {
        if (form.querySelector('[data-owner-closed]')) return;
        const note = document.createElement('div');
        note.className = 'sp-role-note';
        note.dataset.roleNote = 'owner';
        note.dataset.ownerClosed = '';
        note.hidden = true;
        note.innerHTML = '<b>Akun owner sudah tersedia.</b><p>Silakan masuk dengan akun owner yang sudah dibuat.</p>';
        form.insertBefore(note, form.querySelector('[data-form-message]'));
      });
      document.querySelectorAll('[data-owner-only]').forEach(node => { node.hidden = false; node.disabled = data.ownerExists; });
      document.querySelectorAll('[data-owner-closed]').forEach(node => { node.hidden = !data.ownerExists; });
      const requestedRole = new URLSearchParams(location.search).get('role');
      document.querySelectorAll('[data-role-select]').forEach(select => {
        if (requestedRole && [...select.options].some(option => option.value === requestedRole)) select.value = requestedRole;
        select.dispatchEvent(new Event('change'));
      });
      if (data.signedIn) location.href = data.redirect || 'dashboard.php';
    })
    .catch(error => setMessage(status, error.message || 'Tidak dapat terhubung ke server. Buka website melalui hosting atau XAMPP.'));

  document.querySelectorAll('[data-modal-open]').forEach(button => button.addEventListener('click', () => {
    const modal = document.querySelector(`[data-modal="${button.dataset.modalOpen}"]`);
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('modal-visible');
    modal.querySelector('input,select')?.focus();
  }));
  document.querySelectorAll('[data-modal-close]').forEach(button => button.addEventListener('click', () => {
    button.closest('[data-modal]').hidden = true;
    if (!document.querySelector('[data-modal]:not([hidden])')) document.body.classList.remove('modal-visible');
  }));
  document.querySelectorAll('[data-modal]').forEach(modal => modal.addEventListener('click', event => {
    if (event.target === modal) {
      modal.hidden = true;
      if (!document.querySelector('[data-modal]:not([hidden])')) document.body.classList.remove('modal-visible');
    }
  }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      document.querySelectorAll('[data-modal]:not([hidden])').forEach(modal => { modal.hidden = true; });
      document.body.classList.remove('modal-visible');
    }
  });
  document.querySelectorAll('[data-modal-tab]').forEach(button => button.addEventListener('click', () => {
    const current = button.closest('[data-modal]');
    const target = button.dataset.modalTab;
    current.hidden = true;
    const next = document.querySelector(`[data-modal="${target}"]`);
    if (next) {
      next.hidden = false;
      next.querySelector('input,select')?.focus();
    }
  }));
  document.querySelectorAll('[data-role-select]').forEach(select => select.addEventListener('change', () => {
    const form = select.closest('[data-auth-form]');
    const role = select.value;
    const ownerClosed = role === 'owner' && !!select.querySelector('option[value="owner"]:disabled');
    form?.querySelectorAll('[data-role-note]').forEach(note => { note.hidden = note.dataset.roleNote !== role || (note.hasAttribute('data-owner-closed') && !ownerClosed); });
    form?.querySelectorAll('[data-owner-registration-fields], [data-account-registration-fields], [data-seller-registration-fields]').forEach(fields => {
      let show = false;
      if (fields.hasAttribute('data-owner-registration-fields')) show = role === 'owner' && !ownerClosed;
      else if (fields.hasAttribute('data-seller-registration-fields')) show = role === 'seller';
      else if (fields.hasAttribute('data-account-registration-fields')) show = role === 'customer' || role === 'seller';
      fields.hidden = !show;
      fields.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = !show; });
    });
    form?.querySelectorAll('[data-submit-register]').forEach(button => { button.hidden = ownerClosed; });
  }));

  document.querySelectorAll('[data-auth-form]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    const message = form.querySelector('[data-form-message]');
    const roleSelect = form.querySelector('[data-role-select]');
    if (form.dataset.mode === 'register' && roleSelect?.value === 'owner' && roleSelect.querySelector('option[value="owner"]:disabled')) {
      setMessage(message, 'Akun owner sudah tersedia. Silakan masuk sebagai owner.');
      return;
    }
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    if (roleSelect) payload.role = roleSelect.value;
    if (payload.owner_name) payload.name = payload.owner_name;
    if (payload.owner_email) payload.email = payload.owner_email;
    if (payload.owner_password) payload.password = payload.owner_password;
    payload.action = form.dataset.mode;
    payload.csrf = csrf;
    submit.disabled = true;
    submit.classList.add('is-loading');
    const oldLabel = submit.dataset.label || submit.textContent.trim();
    submit.dataset.label = oldLabel;
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
      submit.textContent = oldLabel;
    }
  }));

  document.querySelectorAll('[data-role-link]').forEach(link => link.addEventListener('click', () => {
    const url = new URL(link.href);
    const role = url.searchParams.get('role') || 'customer';
    document.querySelectorAll('[data-role-select]').forEach(select => {
      select.value = role;
      select.dispatchEvent(new Event('change'));
    });
  }));
  document.querySelectorAll('[data-default-role]').forEach(button => button.addEventListener('click', () => {
    const role = button.dataset.defaultRole;
    const modalName = button.dataset.modalOpen;
    window.setTimeout(() => document.querySelectorAll(`[data-modal="${modalName}"] [data-role-select]`).forEach(select => {
      select.value = role;
      select.dispatchEvent(new Event('change'));
    }), 0);
  }));
})();
