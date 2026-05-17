document.addEventListener('DOMContentLoaded', function () {
  var yearEl = document.getElementById('year');
  var formStartedInput = document.getElementById('form_started');
  var toggle = document.querySelector('[data-menu-toggle]');
  var nav = document.querySelector('[data-site-nav]');
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.site-nav a'));
  var contactForm = document.querySelector('[data-contact-form]');
  var contactSubmit = document.querySelector('[data-contact-submit]');
  var toastAutoDismissMs = 8200;

  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  if (formStartedInput) {
    formStartedInput.value = Date.now().toString();
  }

  function ensureToastRegion() {
    var region = document.querySelector('[data-toast-region]');

    if (region) {
      return region;
    }

    region = document.createElement('div');
    region.className = 'realhub-toast-region';
    region.setAttribute('data-toast-region', '');
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.appendChild(region);

    return region;
  }

  function dismissToast(toast) {
    if (!toast || toast.dataset.dismissing === 'true') {
      return;
    }

    toast.dataset.dismissing = 'true';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-0.25rem)';
    window.setTimeout(function () {
      toast.remove();
    }, 160);
  }

  function showToast(options) {
    var type = options.type || 'success';
    var title = options.title || (type === 'error' ? 'Necesita atención' : 'Mensaje enviado');
    var message = String(options.message || '').trim();
    var region;
    var toast;
    var close;
    var timer;

    if (!message) {
      return;
    }

    region = ensureToastRegion();
    toast = document.createElement('div');
    toast.className = 'realhub-toast realhub-toast--' + type;
    toast.innerHTML = '<div class="realhub-toast__title"></div><div class="realhub-toast__message"></div>';
    toast.querySelector('.realhub-toast__title').textContent = title;
    toast.querySelector('.realhub-toast__message').textContent = message;

    close = document.createElement('button');
    close.type = 'button';
    close.className = 'realhub-toast__close';
    close.setAttribute('aria-label', 'Cerrar notificación');
    close.addEventListener('click', function () {
      window.clearTimeout(timer);
      dismissToast(toast);
    });

    toast.appendChild(close);
    region.appendChild(toast);

    timer = window.setTimeout(function () {
      dismissToast(toast);
    }, Number(options.timeout || toastAutoDismissMs));

    toast.addEventListener('mouseenter', function () {
      window.clearTimeout(timer);
    });

    toast.addEventListener('mouseleave', function () {
      timer = window.setTimeout(function () {
        dismissToast(toast);
      }, 1800);
    });
  }

  function setContactPending(isPending) {
    if (!contactSubmit) {
      return;
    }

    contactSubmit.disabled = isPending;
    contactSubmit.textContent = isPending ? 'Enviando...' : 'Enviar consulta';
  }

  if (contactForm && window.fetch && window.FormData) {
    contactForm.addEventListener('submit', function (event) {
      var formData;

      event.preventDefault();

      if (formStartedInput) {
        formStartedInput.value = formStartedInput.value || Date.now().toString();
      }

      formData = new FormData(contactForm);
      setContactPending(true);

      fetch(contactForm.action, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().catch(function () {
            return {
              ok: false,
              title: 'Mensaje no enviado',
              message: 'Hubo un problema al enviar el mensaje. Intente nuevamente o escriba directamente por email.'
            };
          });
        })
        .then(function (payload) {
          var ok = Boolean(payload && payload.ok);

          showToast({
            type: ok ? 'success' : 'error',
            title: payload && payload.title ? payload.title : (ok ? 'Mensaje enviado' : 'Mensaje no enviado'),
            message: payload && payload.message ? payload.message : 'No pudimos confirmar el envío.',
            timeout: ok ? 7600 : 11000
          });

          if (ok) {
            contactForm.reset();
          }
        })
        .catch(function () {
          showToast({
            type: 'error',
            title: 'Mensaje no enviado',
            message: 'No pudimos enviar la consulta. Revise su conexión o escriba directamente por email.',
            timeout: 11000
          });
        })
        .finally(function () {
          setContactPending(false);

          if (formStartedInput) {
            formStartedInput.value = Date.now().toString();
          }
        });
    });
  }

  function closeMobileMenu() {
    if (!toggle || !nav) {
      return;
    }

    nav.classList.remove('is-open');
    toggle.classList.remove('active');
    document.body.classList.remove('nav-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir navegación');
  }

  navLinks.forEach(function (link) {
    link.addEventListener('click', closeMobileMenu);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeMobileMenu();
    }
  });

  if (!toggle || !nav) {
    return;
  }

  toggle.addEventListener('click', function () {
    var isOpen = !nav.classList.contains('is-open');

    nav.classList.toggle('is-open', isOpen);
    toggle.classList.toggle('active', isOpen);
    document.body.classList.toggle('nav-open', isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Cerrar navegación' : 'Abrir navegación');
  });

  document.addEventListener('click', function (event) {
    if (!nav.classList.contains('is-open')) {
      return;
    }

    if (toggle.contains(event.target) || nav.contains(event.target)) {
      return;
    }

    closeMobileMenu();
  });
});
