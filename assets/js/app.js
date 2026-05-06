/**
 * app.js — GuestMS Global JS (no Bootstrap dependency)
 */

document.addEventListener('DOMContentLoaded', function () {
  bindCustomConfirmDialogs();
  bindNotificationDropdown();
  bindAppSounds();

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

function bindAppSounds() {
  const body = document.body;
  if (!body) return;

  const base = body.dataset.soundBase || '';
  const cue = body.dataset.appSound || '';
  const soundMap = {
    error: 'error.mp3',
    login: 'login_sound.mp3',
    notification: 'notification.mp3',
    success: 'success.mp3'
  };

  const playSound = name => {
    if (!base || !soundMap[name]) return;
    const audio = new Audio(base + '/' + soundMap[name]);
    audio.volume = 0.55;
    const played = audio.play();
    if (played && typeof played.catch === 'function') {
      played.catch(() => {
        window.__pendingAppSound = name;
      });
    }
  };

  window.playAppSound = playSound;

  if (cue) {
    setTimeout(() => playSound(cue), 120);
  }

  const unlock = () => {
    if (window.__pendingAppSound) {
      const pending = window.__pendingAppSound;
      window.__pendingAppSound = '';
      playSound(pending);
    }
    document.removeEventListener('click', unlock);
    document.removeEventListener('keydown', unlock);
  };

  document.addEventListener('click', unlock);
  document.addEventListener('keydown', unlock);
}

function bindNotificationDropdown() {
  const button = document.getElementById('notifBtn');
  const dropdown = document.getElementById('notifDropdown');
  if (!button || !dropdown) return;

  const positionDropdown = () => {
    const rect = button.getBoundingClientRect();
    const width = Math.min(340, window.innerWidth - 24);
    const left = Math.max(12, Math.min(window.innerWidth - width - 12, rect.right - width));
    dropdown.style.width = width + 'px';
    dropdown.style.left = left + 'px';
    dropdown.style.top = Math.round(rect.bottom + 10) + 'px';
  };

  button.addEventListener('click', function (event) {
    event.stopPropagation();
    positionDropdown();
    const open = dropdown.classList.toggle('show');
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  dropdown.addEventListener('click', function (event) {
    event.stopPropagation();
  });

  document.addEventListener('click', function () {
    dropdown.classList.remove('show');
    button.setAttribute('aria-expanded', 'false');
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      dropdown.classList.remove('show');
      button.setAttribute('aria-expanded', 'false');
    }
  });

  window.addEventListener('resize', function () {
    if (dropdown.classList.contains('show')) positionDropdown();
  });
}

function ensureConfirmDialog() {
  let dialog = document.getElementById('appConfirmDialog');
  if (dialog) return dialog;

  dialog = document.createElement('div');
  dialog.id = 'appConfirmDialog';
  dialog.className = 'app-confirm-backdrop';
  dialog.innerHTML = `
    <div class="app-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle">
      <div class="app-confirm-header">
        <div class="app-confirm-icon"><i data-lucide="alert-circle"></i></div>
        <div>
          <div class="app-confirm-title" id="appConfirmTitle">Please Confirm</div>
          <div class="app-confirm-message" id="appConfirmMessage"></div>
        </div>
      </div>
      <div class="app-confirm-actions">
        <button type="button" class="btn btn-outline" id="appConfirmCancel">Cancel</button>
        <button type="button" class="btn btn-primary" id="appConfirmOk">Continue</button>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);
  if (window.lucide) lucide.createIcons();
  return dialog;
}

function bindCustomConfirmDialogs() {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    if (el.dataset.confirmBound === '1') return;
    el.dataset.confirmBound = '1';
    el.addEventListener('click', function (e) {
      if (this.dataset.confirmAccepted === '1') {
        this.dataset.confirmAccepted = '0';
        return;
      }

      e.preventDefault();
      const trigger = this;
      const dialog = ensureConfirmDialog();
      const message = document.getElementById('appConfirmMessage');
      const ok = document.getElementById('appConfirmOk');
      const cancel = document.getElementById('appConfirmCancel');

      message.textContent = trigger.getAttribute('data-confirm') || 'Continue with this action?';
      dialog.classList.add('show');

      const close = () => {
        dialog.classList.remove('show');
        ok.onclick = null;
        cancel.onclick = null;
        document.removeEventListener('keydown', onKeydown);
      };

      const onKeydown = event => {
        if (event.key === 'Escape') close();
      };

      cancel.onclick = close;
      ok.onclick = () => {
        close();
        trigger.dataset.confirmAccepted = '1';
        if (trigger.tagName === 'BUTTON' && trigger.form) {
          trigger.click();
        } else if (trigger.tagName === 'A' && trigger.href) {
          window.location.href = trigger.href;
        }
      };
      document.addEventListener('keydown', onKeydown);
    });
  });
}
