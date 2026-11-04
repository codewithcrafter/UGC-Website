<?php
/**
 * MY UGC VIDEO — Admin CMS Footer Partial
 */
?>
    </main> <!-- /.admin-content -->
  </div> <!-- /.admin-main -->
</div> <!-- /.admin-wrapper -->

<script>
// Mobile Navigation Drawer Toggle
(function() {
  const sidebar = document.getElementById('adminSidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const openBtn = document.getElementById('mobileMenuToggle');
  const closeBtn = document.getElementById('sidebarCloseBtn');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (backdrop) backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (openBtn) openBtn.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });

  // Auto-dismiss alerts with smooth fade
  const alertEl = document.getElementById('cmsAlert');
  if (alertEl) {
    setTimeout(() => {
      alertEl.style.opacity = '0';
      setTimeout(() => alertEl.remove(), 400);
    }, 5500);
  }
})();
</script>
</body>
</html>
