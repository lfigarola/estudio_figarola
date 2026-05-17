document.addEventListener('DOMContentLoaded', function () {
  var yearEl = document.getElementById('year');
  var formStartedInput = document.getElementById('form_started');
  var toggle = document.querySelector('[data-menu-toggle]');
  var nav = document.querySelector('[data-site-nav]');
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.site-nav a'));

  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  if (formStartedInput) {
    formStartedInput.value = Date.now().toString();
  }

  function closeMobileMenu() {
    if (!toggle || !nav) {
      return;
    }

    nav.classList.remove('is-open');
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
    document.body.classList.toggle('nav-open', isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Cerrar navegación' : 'Abrir navegación');
  });
});
