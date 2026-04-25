/**
 * app.js — GuestMS Global JS (no Bootstrap dependency)
 */

// Confirm dialogs (data-confirm attribute)
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(this.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // Auto-dismiss flash messages after 5s
  const flash = document.getElementById('flashMsg');
  if (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity .4s';
      flash.style.opacity = '0';
      setTimeout(function () { flash.remove(); }, 400);
    }, 5000);
  }

  // Highlight active nav item based on URL
  const path = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach(function (item) {
    const href = item.getAttribute('href');
    if (href && path.includes(href.split('/').pop().replace('.php', ''))) {
      item.classList.add('active');
    }
  });
});
