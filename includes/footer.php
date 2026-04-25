  </main><!-- /.page-content -->
</div><!-- /.main-area -->
</div><!-- /.app-wrapper -->

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Init Lucide icons
lucide.createIcons();

// Sidebar toggle
const sidebar   = document.getElementById('sidebar');
const mainArea  = document.getElementById('mainArea');
const toggleBtn = document.getElementById('sidebarToggle');

// Restore state
if (localStorage.getItem('sidebarCollapsed') === '1') {
  sidebar.classList.add('collapsed');
}

toggleBtn && toggleBtn.addEventListener('click', () => {
  sidebar.classList.toggle('collapsed');
  localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
});

// User dropdown
const userBtn  = document.getElementById('userMenuBtn');
const userDrop = document.getElementById('userDropdown');
userBtn && userBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  userDrop.style.display = userDrop.style.display === 'none' ? 'block' : 'none';
});
document.addEventListener('click', () => { if(userDrop) userDrop.style.display = 'none'; });

// Live clock
function updateClock() {
  const now  = new Date();
  const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const mons = ['January','February','March','April','May','June',
                'July','August','September','October','November','December'];
  const dateEl = document.getElementById('topbarDate');
  const timeEl = document.getElementById('topbarTime');
  if (dateEl) {
    dateEl.textContent = mons[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
  }
  if (timeEl) {
    let h = now.getHours(), m = now.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    timeEl.textContent = h + ':' + String(m).padStart(2,'0') + ' ' + ap;
  }
}
updateClock();
setInterval(updateClock, 30000);

// Confirm dialogs
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});
</script>
</body>
</html>
