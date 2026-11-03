<?php
/**
 * includes/footer.php
 * Garage Management System
 *
 * Renders the site footer, loads JS bundles, closes the page.
 * Included as the LAST thing on every page.
 */
?>

<!-- ═══════════════════════════════════════════════════════════
     CTA BANNER (only shown on public/guest pages)
     ═══════════════════════════════════════════════════════════ -->
<?php if (!isLoggedIn() && ($showCtaBanner ?? true)): ?>
<section class="gms-cta-banner" id="get-started">
    <div class="container text-center">
        <p class="section-label justify-content-center mb-2">
            <i class="bi bi-rocket-takeoff-fill"></i> Get Started Today
        </p>
        <h2 class="gms-cta-title mb-3">
            Ready to Experience Professional<br class="d-none d-md-block" />
            Garage Service?
        </h2>
        <p class="gms-cta-sub mb-4">
            Register your account, add your vehicle, and book your first service in minutes.
        </p>
        <div class="d-flex justify-content-center flex-wrap gap-3">
            <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary">
                <i class="bi bi-person-plus-fill"></i> Create Free Account
            </a>
            <a href="<?= BASE_URL ?>login.php" class="btn-gms btn-gms-outline">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </a>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════════════════════ -->
<footer class="gms-footer" role="contentinfo">
    <div class="container">
        <div class="row gy-4">

            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6">
                <div class="gms-footer-brand mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:38px;height:38px;background:linear-gradient(135deg,#f97316,#fb923c);
                                    border-radius:8px;display:flex;align-items:center;
                                    justify-content:center;font-size:1.1rem;color:#fff;">
                            <i class="bi bi-wrench-adjustable"></i>
                        </div>
                        <span class="name">GarageMS</span>
                    </div>
                    <p class="tagline">
                        Your trusted partner for professional vehicle service and maintenance.
                        Quality work, transparent pricing, every time.
                    </p>
                </div>

                <!-- Social Icons -->
                <div class="gms-social">
                    <a href="#" aria-label="Facebook"  title="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" aria-label="Instagram" title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" aria-label="Twitter"   title="Twitter / X">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="#" aria-label="WhatsApp"  title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-sm-6">
                <h3 class="gms-footer-heading">Quick Links</h3>
                <ul class="gms-footer-links">
                    <li><a href="<?= BASE_URL ?>index.php">Home</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services">Services</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#about">About Us</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#contact">Contact</a></li>
                    <li><a href="<?= BASE_URL ?>login.php">Login</a></li>
                    <li><a href="<?= BASE_URL ?>register.php">Register</a></li>
                </ul>
            </div>

            <!-- Services Links -->
            <div class="col-lg-2 col-md-6 col-sm-6">
                <h3 class="gms-footer-heading">Services</h3>
                <ul class="gms-footer-links">
                    <li><a href="<?= BASE_URL ?>index.php#services">Oil Change</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services">General Service</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services">Brake Service</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services">AC Service</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services">Engine Repair</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services">Car Wash</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-4 col-md-6">
                <h3 class="gms-footer-heading">Contact Us</h3>

                <div class="gms-footer-contact-item">
                    <i class="bi bi-geo-alt-fill icon"></i>
                    <p>123, Service Road, Industrial Area,<br />City — 400001, India</p>
                </div>

                <div class="gms-footer-contact-item">
                    <i class="bi bi-telephone-fill icon"></i>
                    <p>+91 98765 43210<br />+91 98765 43211</p>
                </div>

                <div class="gms-footer-contact-item">
                    <i class="bi bi-envelope-fill icon"></i>
                    <p>info@garagems.com<br />support@garagems.com</p>
                </div>

                <div class="gms-footer-contact-item">
                    <i class="bi bi-clock-fill icon"></i>
                    <p>Mon – Sat: 8:00 AM – 7:00 PM<br />Sunday: 9:00 AM – 2:00 PM</p>
                </div>
            </div>

        </div><!-- /.row -->

        <!-- Bottom Bar -->
        <div class="gms-footer-bar">
            <p>
                &copy; <?= date('Y') ?> <span>GarageMS</span>. All rights reserved.
                &nbsp;·&nbsp; BCA College Project
            </p>
            <p>
                Built with <span>PHP 8</span>, <span>MySQL</span> &amp; <span>Bootstrap 5</span>
            </p>
        </div>

    </div><!-- /.container -->
</footer>


<!-- ── Bootstrap 5.3 JS Bundle (includes Popper) ────────────── -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmB5b0p+6c7dY69e+FWLWSCoFIY"
    crossorigin="anonymous"
></script>

<!-- ── Custom JS ─────────────────────────────────────────────── -->
<script src="<?= BASE_URL ?>assets/js/script.js"></script>

</body>
</html>
