<?php
/**
 * admin/includes/admin_footer.php — Admin Layout Closing Tags
 * Garage Management System
 *
 * Closes the admin-main and admin-layout divs opened by admin_nav.php.
 * Also includes the admin-specific JS for sidebar toggling.
 */
?>
    </main><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<script>
(function () {
    'use strict';
    const sidebar  = document.getElementById('adminSidebar');
    const overlay  = document.getElementById('adminSidebarOverlay');
    const toggle   = document.getElementById('adminSidebarToggle');
    const closeBtn = document.getElementById('adminSidebarClose');

    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('active');
        document.body.style.overflow = '';
    }

    toggle?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
})();
</script>
