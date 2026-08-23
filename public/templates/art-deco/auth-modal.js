(() => {
  const modal = document.querySelector('[data-auth-modal]');
  if (!modal) {
    return;
  }

  function setOpen(open) {
    if (!open && modal.dataset.authHome) {
      window.location.href = modal.dataset.authHome;
      return;
    }
    modal.hidden = !open;
    document.body.style.overflow = open ? 'hidden' : '';
  }

  function switchTab(tab) {
    const next = tab === 'register' ? 'register' : 'login';
    if (next === 'register' && !modal.querySelector('[data-auth-panel="register"]')) {
      return;
    }
    modal.querySelectorAll('[data-auth-tab]').forEach((button) => {
      const active = button.dataset.authTab === next;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    modal.querySelectorAll('[data-auth-panel]').forEach((panel) => {
      const active = panel.dataset.authPanel === next;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
  }

  document.querySelectorAll('[data-auth-open]').forEach((node) => {
    node.addEventListener('click', (event) => {
      event.preventDefault();
      const tab = node.getAttribute('data-auth-open');
      if (tab === 'register' || tab === 'login') {
        switchTab(tab);
      }
      setOpen(true);
    });
  });

  modal.querySelectorAll('[data-auth-close]').forEach((node) => {
    node.addEventListener('click', () => setOpen(false));
  });
  modal.querySelectorAll('[data-auth-tab]').forEach((button) => {
    button.addEventListener('click', () => switchTab(button.dataset.authTab));
  });
  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) {
      setOpen(false);
    }
  });

  if (modal.dataset.authStart === 'register') {
    switchTab('register');
  }
  if (!modal.hidden) {
    document.body.style.overflow = 'hidden';
  }
})();
