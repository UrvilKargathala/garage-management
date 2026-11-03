/**
 * script.js — Global JavaScript
 * Garage Management System
 * Vanilla JS only — no jQuery, no frameworks.
 */

'use strict';

/* ── DOMContentLoaded ──────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initMobileMenu();
    initScrollReveal();
    initActiveNavLink();
    initAutoAlerts();
    initSmoothScroll();
    initStatsCounter();
});

/* ════════════════════════════════════════════════════════════
   1. NAVBAR — transparent → opaque on scroll
   ════════════════════════════════════════════════════════════ */
function initNavbar() {
    var navbar = document.getElementById('gmsNavbar');
    if (!navbar) return;

    function onScroll() {
        if (window.scrollY > 40) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // run once on load
}

/* ════════════════════════════════════════════════════════════
   2. MOBILE MENU — open / close
   ════════════════════════════════════════════════════════════ */
function initMobileMenu() {
    var hamburger  = document.getElementById('gmsHamburger');
    var mobileMenu = document.getElementById('gmsMobileMenu');
    if (!hamburger || !mobileMenu) return;

    hamburger.addEventListener('click', function () {
        var isOpen = mobileMenu.classList.toggle('open');
        hamburger.classList.toggle('open', isOpen);
        hamburger.setAttribute('aria-expanded', String(isOpen));
        // Prevent body scroll when menu is open
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // Close on any link click
    mobileMenu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            mobileMenu.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        });
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (
            mobileMenu.classList.contains('open') &&
            !mobileMenu.contains(e.target) &&
            !hamburger.contains(e.target)
        ) {
            mobileMenu.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    });
}

/* ════════════════════════════════════════════════════════════
   3. SCROLL REVEAL — fade elements in as they enter viewport
   ════════════════════════════════════════════════════════════ */
function initScrollReveal() {
    var targets = document.querySelectorAll('.reveal');
    if (!targets.length || !('IntersectionObserver' in window)) {
        // Fallback: show all immediately
        targets.forEach(function (el) {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    targets.forEach(function (el) { observer.observe(el); });
}

/* ════════════════════════════════════════════════════════════
   4. ACTIVE NAV LINK — highlight based on current URL / hash
   ════════════════════════════════════════════════════════════ */
function initActiveNavLink() {
    var currentPath = window.location.pathname;
    var currentHash = window.location.hash;

    // Desktop links
    document.querySelectorAll('.gms-nav-link').forEach(function (link) {
        var href = link.getAttribute('href') || '';
        if (
            (href === currentPath) ||
            (currentPath.endsWith(href) && href !== '/') ||
            (currentHash && href === currentHash)
        ) {
            link.classList.add('active');
        }
    });

    // Update active on hash change (single-page scrolling sections)
    window.addEventListener('hashchange', function () {
        document.querySelectorAll('.gms-nav-link').forEach(function (l) {
            l.classList.remove('active');
            if (l.getAttribute('href') === window.location.hash) {
                l.classList.add('active');
            }
        });
    });
}

/* ════════════════════════════════════════════════════════════
   5. AUTO DISMISS alerts (Bootstrap .alert)
   ════════════════════════════════════════════════════════════ */
function initAutoAlerts() {
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alertEl) {
        setTimeout(function () {
            if (typeof bootstrap !== 'undefined') {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                bsAlert.close();
            }
        }, 4500);
    });
}

/* ════════════════════════════════════════════════════════════
   6. SMOOTH SCROLL for in-page anchor links
   ════════════════════════════════════════════════════════════ */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href');
            if (targetId === '#') return;
            var target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // update URL without jumping
                history.pushState(null, '', targetId);
            }
        });
    });
}

/* ════════════════════════════════════════════════════════════
   7. STATS COUNTER ANIMATION
   Animates numbers from 0 to their data-target value.
   ════════════════════════════════════════════════════════════ */
function initStatsCounter() {
    var counters  = document.querySelectorAll('[data-counter]');
    if (!counters.length || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(function (el) { observer.observe(el); });
}

function animateCounter(el) {
    var target   = parseInt(el.getAttribute('data-counter'), 10);
    var suffix   = el.getAttribute('data-suffix') || '';
    var duration = 1800; // ms
    var start    = performance.now();

    function update(now) {
        var elapsed  = now - start;
        var progress = Math.min(elapsed / duration, 1);
        // Ease-out cubic
        var eased    = 1 - Math.pow(1 - progress, 3);
        var current  = Math.round(eased * target);
        el.textContent = current + suffix;
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    requestAnimationFrame(update);
}

/* ════════════════════════════════════════════════════════════
   8. DELETE CONFIRMATION
   Add onclick="confirmDelete(event,'Name')" to delete buttons.
   ════════════════════════════════════════════════════════════ */
function confirmDelete(event, itemName) {
    var name = itemName || 'this item';
    if (!window.confirm('Delete ' + name + '?\n\nThis action cannot be undone.')) {
        event.preventDefault();
    }
}

/* ════════════════════════════════════════════════════════════
   9. FORM VALIDATION FEEDBACK (Bootstrap 5 style)
   Add class="needs-validation" to any <form> for instant feedback.
   ════════════════════════════════════════════════════════════ */
document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});
