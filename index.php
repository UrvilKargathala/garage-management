<?php
/**
 * index.php — Public Homepage
 * Garage Management System
 *
 * Sections:
 *  1. Hero
 *  2. Services
 *  3. Why Choose Us
 *  4. How It Works
 *  5. About
 *  6. Contact
 *
 * No PHP business logic here — this is a pure presentation page.
 * Database connection is NOT loaded here (not needed for the homepage).
 */

$pageTitle    = 'Home';
$showCtaBanner = false; // We have a manual CTA section; skip the footer's generic one

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- ═══════════════════════════════════════════════════════════
     SECTION 1 : HERO
     ═══════════════════════════════════════════════════════════ -->
<section class="gms-hero" id="home" aria-label="Hero section">
    <div class="container gms-hero-inner">
        <div class="row align-items-center gy-5">

            <!-- Left: Headline + CTA -->
            <div class="col-lg-6">

                <div class="gms-hero-badge">
                    <span class="dot"></span>
                    Professional Garage Management System
                </div>

                <h1 class="gms-hero-title">
                    Your Vehicle<br />
                    Deserves the<br />
                    <span class="text-gradient">Best Care.</span>
                </h1>

                <p class="gms-hero-desc">
                    Book expert vehicle services online in minutes. Trusted mechanics,
                    transparent pricing, and real-time booking status — all in one place.
                </p>

                <div class="gms-hero-cta">
                    <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary">
                        <i class="bi bi-calendar-check-fill"></i>
                        Book a Service
                    </a>
                    <a href="<?= BASE_URL ?>login.php" class="btn-gms btn-gms-outline">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Login
                    </a>
                </div>

                <!-- Stat counters -->
                <div class="gms-hero-stats">
                    <div class="gms-hero-stat">
                        <span class="num" data-counter="1200" data-suffix="+">0+</span>
                        <span class="lbl">Happy Customers</span>
                    </div>
                    <div class="gms-hero-stat">
                        <span class="num" data-counter="8" data-suffix="+">0+</span>
                        <span class="lbl">Services Offered</span>
                    </div>
                    <div class="gms-hero-stat">
                        <span class="num" data-counter="15" data-suffix="+">0+</span>
                        <span class="lbl">Expert Mechanics</span>
                    </div>
                    <div class="gms-hero-stat">
                        <span class="num" data-counter="10" data-suffix=" yrs">0 yrs</span>
                        <span class="lbl">Experience</span>
                    </div>
                </div>

            </div><!-- /col -->

            <!-- Right: Smart Garage Service Dashboard -->
            <div class="col-lg-6">
                <div class="gms-hero-visual dashboard-visual">
                    
                    <!-- Background Car Image Area -->
                    <div class="dashboard-backdrop">
                        <img src="<?= BASE_URL ?>assets/img/hero_car_bg.jpg" alt="Premium Garage Environment" class="hero-car-img" />
                        <div class="backdrop-overlay"></div>
                    </div>


                </div><!-- /.gms-hero-visual -->
            </div><!-- /col -->

        </div><!-- /row -->
    </div><!-- /container -->

    <!-- Scroll indicator -->
    <div class="gms-scroll-indicator">
        <div class="mouse"><div class="wheel"></div></div>
        <span>Scroll</span>
    </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     SECTION 2 : SERVICES
     ═══════════════════════════════════════════════════════════ -->
<section class="gms-services" id="services" aria-label="Our Services">
    <div class="container">

        <div class="text-center mb-2 reveal">
            <p class="section-label justify-content-center">
                <i class="bi bi-tools"></i> What We Offer
            </p>
            <h2 class="section-title dark">Our Expert Services</h2>
        </div>
        <p class="section-subtitle dark text-center reveal reveal-delay-1">
            From routine maintenance to complex repairs — our certified mechanics
            handle it all with precision and care.
        </p>

        <div class="row g-4">

            <!-- Oil Change -->
            <div class="col-sm-6 col-lg-4 reveal reveal-delay-1">
                <div class="gms-service-card">
                    <div class="gms-service-icon">🛢️</div>
                    <h3 class="gms-service-name">Oil Change</h3>
                    <p class="gms-service-desc">
                        Complete engine oil drain and refill using manufacturer-approved
                        lubricants. Includes oil filter replacement and fluid top-up.
                    </p>
                </div>
            </div>

            <!-- General Service -->
            <div class="col-sm-6 col-lg-4 reveal reveal-delay-2">
                <div class="gms-service-card">
                    <div class="gms-service-icon">🔧</div>
                    <h3 class="gms-service-name">General Service</h3>
                    <p class="gms-service-desc">
                        Comprehensive 40-point vehicle inspection covering engine, brakes,
                        tyres, suspension, lights, and all fluid levels.
                    </p>
                </div>
            </div>

            <!-- Brake Service -->
            <div class="col-sm-6 col-lg-4 reveal reveal-delay-3">
                <div class="gms-service-card">
                    <div class="gms-service-icon">🛑</div>
                    <h3 class="gms-service-name">Brake Service</h3>
                    <p class="gms-service-desc">
                        Full brake system inspection, pad replacement, rotor resurfacing,
                        and brake fluid flush to keep your stopping power at its best.
                    </p>
                </div>
            </div>

            <!-- AC Service -->
            <div class="col-sm-6 col-lg-4 reveal reveal-delay-1">
                <div class="gms-service-card">
                    <div class="gms-service-icon">❄️</div>
                    <h3 class="gms-service-name">AC Service</h3>
                    <p class="gms-service-desc">
                        AC compressor check, refrigerant gas recharge, condenser cleaning,
                        and cabin air filter replacement for optimal cooling.
                    </p>
                </div>
            </div>

            <!-- Engine Repair -->
            <div class="col-sm-6 col-lg-4 reveal reveal-delay-2">
                <div class="gms-service-card">
                    <div class="gms-service-icon">⚙️</div>
                    <h3 class="gms-service-name">Engine Repair</h3>
                    <p class="gms-service-desc">
                        OBD-II diagnostics, engine tune-up, timing belt/chain service,
                        and complete overhaul performed by certified engine specialists.
                    </p>
                </div>
            </div>

            <!-- Car Wash -->
            <div class="col-sm-6 col-lg-4 reveal reveal-delay-3">
                <div class="gms-service-card">
                    <div class="gms-service-icon">🚿</div>
                    <h3 class="gms-service-name">Car Wash &amp; Detailing</h3>
                    <p class="gms-service-desc">
                        Premium exterior wash, interior vacuuming, dashboard polish,
                        and full ceramic coating protection packages available.
                    </p>
                </div>
            </div>

        </div><!-- /row -->

        <!-- CTA below services -->
        <div class="text-center mt-5 reveal">
            <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary">
                <i class="bi bi-calendar-plus-fill"></i> Book Any Service Now
            </a>
        </div>

    </div><!-- /container -->
</section>


<!-- ═══════════════════════════════════════════════════════════
     SECTION 3 : WHY CHOOSE US
     ═══════════════════════════════════════════════════════════ -->
<section class="gms-why" id="why-us" aria-label="Why Choose Us">
    <div class="container">

        <div class="text-center mb-2 reveal">
            <p class="section-label justify-content-center">
                <i class="bi bi-star-fill"></i> Our Advantages
            </p>
            <h2 class="section-title light">Why Choose GarageMS?</h2>
        </div>
        <p class="section-subtitle light text-center reveal reveal-delay-1">
            We go beyond just fixing cars. We build lasting relationships through
            honesty, expertise, and outstanding customer care.
        </p>

        <div class="row g-4">

            <div class="col-sm-6 col-lg-4 reveal reveal-delay-1">
                <div class="gms-why-card">
                    <div class="gms-why-icon why-orange">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <div class="gms-why-body">
                        <h4>Experienced Mechanics</h4>
                        <p>All our mechanics are certified with 5+ years of hands-on experience
                           across all major vehicle brands and models.</p>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 reveal reveal-delay-2">
                <div class="gms-why-card">
                    <div class="gms-why-icon why-green">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                    <div class="gms-why-body">
                        <h4>Transparent Pricing</h4>
                        <p>No hidden charges, ever. You get a full cost estimate before
                           work begins and pay exactly what was quoted.</p>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 reveal reveal-delay-3">
                <div class="gms-why-card">
                    <div class="gms-why-icon why-blue">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <div class="gms-why-body">
                        <h4>Genuine Parts Only</h4>
                        <p>We use only OEM and manufacturer-approved parts, backed by
                           official warranty for your complete peace of mind.</p>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 reveal reveal-delay-1">
                <div class="gms-why-card">
                    <div class="gms-why-icon why-purple">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <div class="gms-why-body">
                        <h4>Fast Turnaround</h4>
                        <p>Most standard services are completed same day. Our streamlined
                           workflow means minimum waiting time for you.</p>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 reveal reveal-delay-2">
                <div class="gms-why-card">
                    <div class="gms-why-icon why-cyan">
                        <i class="bi bi-phone-fill"></i>
                    </div>
                    <div class="gms-why-body">
                        <h4>Real-Time Updates</h4>
                        <p>Track your vehicle's service status online from anywhere.
                           Get notified the moment your car is ready for pickup.</p>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 reveal reveal-delay-3">
                <div class="gms-why-card">
                    <div class="gms-why-icon why-red">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div class="gms-why-body">
                        <h4>Safe &amp; Secure</h4>
                        <p>CCTV-monitored premises, your vehicle is in safe hands 24/7.
                           We treat every vehicle as our own.</p>
                    </div>
                </div>
            </div>

        </div><!-- /row -->
    </div><!-- /container -->
</section>


<!-- ═══════════════════════════════════════════════════════════
     SECTION 4 : HOW IT WORKS
     ═══════════════════════════════════════════════════════════ -->
<section class="gms-how" id="how-it-works" aria-label="How It Works">
    <div class="container">

        <div class="text-center mb-2 reveal">
            <p class="section-label justify-content-center">
                <i class="bi bi-list-ol"></i> Simple Process
            </p>
            <h2 class="section-title dark">How It Works</h2>
        </div>
        <p class="section-subtitle dark text-center reveal reveal-delay-1">
            Getting your vehicle serviced has never been easier.
            Follow these 5 simple steps and we'll take care of the rest.
        </p>

        <div class="row g-4 g-lg-2 justify-content-center">

            <div class="col-6 col-md-4 col-lg reveal reveal-delay-1">
                <div class="gms-step">
                    <div class="gms-step-number">
                        <span class="gms-step-icon">👤</span>
                    </div>
                    <h3 class="gms-step-title">Register</h3>
                    <p class="gms-step-desc">
                        Create your free account with email and a secure password.
                    </p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg reveal reveal-delay-2">
                <div class="gms-step">
                    <div class="gms-step-number">
                        <span class="gms-step-icon">🚗</span>
                    </div>
                    <h3 class="gms-step-title">Add Vehicle</h3>
                    <p class="gms-step-desc">
                        Enter your vehicle details — make, model, year and plate number.
                    </p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg reveal reveal-delay-3">
                <div class="gms-step">
                    <div class="gms-step-number">
                        <span class="gms-step-icon">🔧</span>
                    </div>
                    <h3 class="gms-step-title">Select Service</h3>
                    <p class="gms-step-desc">
                        Browse our catalogue and pick the service your vehicle needs.
                    </p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg reveal reveal-delay-4">
                <div class="gms-step">
                    <div class="gms-step-number">
                        <span class="gms-step-icon">📅</span>
                    </div>
                    <h3 class="gms-step-title">Book Appointment</h3>
                    <p class="gms-step-desc">
                        Choose a convenient date and time slot for your appointment.
                    </p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg reveal reveal-delay-5">
                <div class="gms-step">
                    <div class="gms-step-number">
                        <span class="gms-step-icon">✅</span>
                    </div>
                    <h3 class="gms-step-title">Get Serviced</h3>
                    <p class="gms-step-desc">
                        Drop off your vehicle. Track progress online. Pick it up — done!
                    </p>
                </div>
            </div>

        </div><!-- /row -->

        <!-- Action CTA -->
        <div class="text-center mt-5 reveal">
            <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary me-3">
                <i class="bi bi-person-plus-fill"></i> Register Now — It's Free
            </a>
            <a href="<?= BASE_URL ?>index.php#services" class="btn-gms btn-gms-outline-accent">
                <i class="bi bi-grid-fill"></i> View Services
            </a>
        </div>

    </div><!-- /container -->
</section>


<!-- ═══════════════════════════════════════════════════════════
     SECTION 5 : ABOUT
     ═══════════════════════════════════════════════════════════ -->
<section class="gms-about" id="about" aria-label="About Us">
    <div class="container">
        <div class="row align-items-center gy-5">

            <!-- Left: Visual -->
            <div class="col-lg-5 reveal">
                <style>
                    /* Futuristic Vehicle Health Scanner */
                    .gms-health-scanner {
                        position: relative;
                        background: linear-gradient(145deg, var(--clr-dark-3), var(--clr-dark-2));
                        border: 1px solid rgba(6, 182, 212, 0.2); /* Cyan glow hint */
                        border-radius: var(--radius-xl);
                        padding: 2.5rem 1.5rem;
                        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), inset 0 0 20px rgba(6, 182, 212, 0.05);
                        overflow: hidden;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        color: var(--clr-text);
                        z-index: 1;
                    }
                    /* Subtle background grid */
                    .gms-health-scanner::before {
                        content: '';
                        position: absolute;
                        inset: 0;
                        background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                                          linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
                        background-size: 20px 20px;
                        z-index: -1;
                        pointer-events: none;
                    }
                    .scanner-header {
                        text-align: center;
                        margin-bottom: 2rem;
                    }
                    .scanner-title {
                        font-family: var(--font-display);
                        font-size: 1.1rem;
                        font-weight: 800;
                        letter-spacing: 2px;
                        color: var(--clr-cyan);
                        text-transform: uppercase;
                        margin: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 0.5rem;
                    }
                    .scanner-title i {
                        animation: pulse-dot 2s infinite;
                    }
                    
                    .scanner-center {
                        position: relative;
                        width: 100%;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        margin-bottom: 2rem;
                    }
                    
                    /* Main Circular Score */
                    .health-circle {
                        position: relative;
                        width: 140px;
                        height: 140px;
                        border-radius: 50%;
                        background: radial-gradient(circle, var(--clr-dark-4) 40%, transparent 70%);
                        border: 2px dashed rgba(249, 115, 22, 0.4);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 0 30px rgba(249, 115, 22, 0.2);
                        animation: spin-slow 15s linear infinite;
                    }
                    .health-circle-inner {
                        position: absolute;
                        top: 0; left: 0; right: 0; bottom: 0;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        z-index: 2;
                    }
                    .health-score {
                        font-family: var(--font-display);
                        font-size: 2.5rem;
                        font-weight: 900;
                        color: #fff;
                        line-height: 1;
                        text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
                    }
                    .health-lbl {
                        font-size: 0.65rem;
                        font-weight: 700;
                        letter-spacing: 1px;
                        color: var(--clr-accent);
                        text-transform: uppercase;
                        margin-top: 0.2rem;
                    }

                    /* Diagnostic Indicators */
                    .diag-list {
                        width: 100%;
                        display: flex;
                        flex-direction: column;
                        gap: 0.8rem;
                        margin-bottom: 2rem;
                    }
                    .diag-item {
                        background: rgba(0, 0, 0, 0.3);
                        border: 1px solid rgba(255, 255, 255, 0.05);
                        border-radius: var(--radius-sm);
                        padding: 0.6rem 1rem;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .diag-label {
                        font-size: 0.8rem;
                        font-weight: 600;
                        color: var(--clr-text-muted);
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                    .diag-label i {
                        color: var(--clr-cyan);
                        font-size: 0.9rem;
                    }
                    .diag-status {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                    }
                    .diag-val {
                        font-family: var(--font-display);
                        font-size: 0.9rem;
                        font-weight: 700;
                        color: #fff;
                    }
                    .diag-text {
                        font-size: 0.7rem;
                        font-weight: 700;
                        text-transform: uppercase;
                        padding: 0.2rem 0.5rem;
                        border-radius: 4px;
                        background: rgba(34, 197, 94, 0.15);
                        color: var(--clr-green);
                    }
                    .diag-text.warning {
                        background: rgba(249, 115, 22, 0.15);
                        color: var(--clr-accent);
                    }

                    /* Secondary Card */
                    .scanner-secondary {
                        background: rgba(15, 23, 42, 0.6);
                        border: 1px solid rgba(249, 115, 22, 0.2);
                        border-radius: var(--radius-md);
                        padding: 1rem;
                        width: 100%;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 1.5rem;
                        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    }
                    .sec-left {
                        display: flex;
                        flex-direction: column;
                    }
                    .sec-lbl {
                        font-size: 0.75rem;
                        color: var(--clr-text-muted);
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        font-weight: 600;
                    }
                    .sec-val {
                        font-family: var(--font-display);
                        font-size: 1.25rem;
                        font-weight: 800;
                        color: var(--clr-accent);
                        line-height: 1.2;
                    }
                    .sec-est {
                        font-size: 0.75rem;
                        color: var(--clr-cyan);
                        font-weight: 500;
                    }
                    .sec-icon {
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        background: rgba(249, 115, 22, 0.1);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: var(--clr-accent);
                        font-size: 1.2rem;
                    }

                    /* Bottom message */
                    .scanner-footer {
                        text-align: center;
                        font-size: 0.85rem;
                        color: var(--clr-text-subtle);
                        font-weight: 500;
                        font-style: italic;
                    }
                </style>
                <div class="gms-health-scanner">
                    <div class="scanner-header">
                        <h4 class="scanner-title"><i class="bi bi-record-circle-fill"></i> Vehicle Health Scan</h4>
                    </div>

                    <div class="scanner-center">
                        <div class="health-circle"></div>
                        <div class="health-circle-inner">
                            <span class="health-score">95%</span>
                            <span class="health-lbl">Overall Health</span>
                        </div>
                    </div>

                    <div class="diag-list">
                        <div class="diag-item">
                            <span class="diag-label"><i class="bi bi-gear-fill"></i> ENGINE</span>
                            <div class="diag-status">
                                <span class="diag-val">98%</span>
                                <span class="diag-text">Excellent</span>
                            </div>
                        </div>
                        <div class="diag-item">
                            <span class="diag-label"><i class="bi bi-battery-charging"></i> BATTERY</span>
                            <div class="diag-status">
                                <span class="diag-val">94%</span>
                                <span class="diag-text">Good</span>
                            </div>
                        </div>
                        <div class="diag-item">
                            <span class="diag-label"><i class="bi bi-stop-circle-fill"></i> BRAKES</span>
                            <div class="diag-status">
                                <span class="diag-val">96%</span>
                                <span class="diag-text">Good</span>
                            </div>
                        </div>
                        <div class="diag-item">
                            <span class="diag-label"><i class="bi bi-record-circle"></i> TYRES</span>
                            <div class="diag-status">
                                <span class="diag-val">97%</span>
                                <span class="diag-text">Excellent</span>
                            </div>
                        </div>
                        <div class="diag-item">
                            <span class="diag-label"><i class="bi bi-droplet-fill" style="color:var(--clr-accent)"></i> OIL LEVEL</span>
                            <div class="diag-status">
                                <span class="diag-val">92%</span>
                                <span class="diag-text warning">Good</span>
                            </div>
                        </div>
                    </div>

                    <div class="scanner-secondary">
                        <div class="sec-left">
                            <span class="sec-lbl">Next Service</span>
                            <span class="sec-val">2,450 KM</span>
                            <span class="sec-est">Estimated in 18 Days</span>
                        </div>
                        <div class="sec-icon">
                            <i class="bi bi-calendar-event-fill"></i>
                        </div>
                    </div>

                    <div class="scanner-footer">
                        "We don't just fix cars, we keep them road-ready."
                    </div>
                </div>
            </div>

            <!-- Right: Content -->
            <div class="col-lg-7 gms-about-content reveal reveal-delay-2">

                <p class="section-label">
                    <i class="bi bi-info-circle-fill"></i> About Us
                </p>
                <h2 class="section-title light mb-3">
                    A Garage You Can<br />
                    <span class="text-gradient">Actually Trust</span>
                </h2>
                <p style="color:var(--clr-text-muted);line-height:1.8;margin-bottom:1.5rem;font-size:0.95rem;">
                    GarageMS was founded with a single vision: to make professional vehicle
                    servicing accessible, transparent, and stress-free for every car owner.
                    Over the past decade, we've served thousands of satisfied customers and
                    built a reputation for quality workmanship and honest pricing.
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="gms-about-feature">
                            <div class="gms-about-check"><i class="bi bi-check-lg"></i></div>
                            <p><strong>ISO-certified workshop</strong> with modern diagnostic equipment and tools.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gms-about-feature">
                            <div class="gms-about-check"><i class="bi bi-check-lg"></i></div>
                            <p><strong>15+ certified mechanics</strong> trained on all major vehicle brands.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gms-about-feature">
                            <div class="gms-about-check"><i class="bi bi-check-lg"></i></div>
                            <p><strong>Online booking system</strong> for appointments 24 hours a day.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gms-about-feature">
                            <div class="gms-about-check"><i class="bi bi-check-lg"></i></div>
                            <p><strong>Service history records</strong> maintained digitally for every vehicle.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gms-about-feature">
                            <div class="gms-about-check"><i class="bi bi-check-lg"></i></div>
                            <p><strong>Genuine OEM parts</strong> with manufacturer warranty on all replacements.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gms-about-feature">
                            <div class="gms-about-check"><i class="bi bi-check-lg"></i></div>
                            <p><strong>Transparent billing</strong> — no surprises, no hidden charges, ever.</p>
                        </div>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary">
                    <i class="bi bi-calendar-check-fill"></i> Book a Service Today
                </a>

            </div><!-- /col -->
        </div><!-- /row -->
    </div><!-- /container -->
</section>


<!-- ═══════════════════════════════════════════════════════════
     SECTION 6 : CONTACT
     ═══════════════════════════════════════════════════════════ -->
<section class="gms-contact" id="contact" aria-label="Contact Us">
    <div class="container">

        <div class="text-center mb-2 reveal">
            <p class="section-label justify-content-center">
                <i class="bi bi-envelope-fill"></i> Get In Touch
            </p>
            <h2 class="section-title dark">Contact Us</h2>
        </div>
        <p class="section-subtitle dark text-center reveal reveal-delay-1">
            Have a question or want to book a service? We're here to help.
            Reach out through any of the channels below.
        </p>

        <div class="row g-4">

            <!-- Contact info cards -->
            <div class="col-lg-5">
                <div class="d-flex flex-column gap-3">

                    <div class="gms-contact-card reveal reveal-delay-1">
                        <div class="gms-contact-icon why-orange">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="gms-contact-body">
                            <h5>Visit Us</h5>
                            <p>123, Service Road, Industrial Area,<br />City — 400001, India</p>
                        </div>
                    </div>

                    <div class="gms-contact-card reveal reveal-delay-2">
                        <div class="gms-contact-icon why-blue">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div class="gms-contact-body">
                            <h5>Call Us</h5>
                            <p>+91 98765 43210<br />Mon – Sat, 8:00 AM – 7:00 PM</p>
                        </div>
                    </div>

                    <div class="gms-contact-card reveal reveal-delay-3">
                        <div class="gms-contact-icon why-green">
                            <i class="bi bi-envelope-fill"></i>
                        </div>
                        <div class="gms-contact-body">
                            <h5>Email Us</h5>
                            <p>info@garagems.com<br />support@garagems.com</p>
                        </div>
                    </div>

                    <div class="gms-contact-card reveal reveal-delay-4">
                        <div class="gms-contact-icon why-purple">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                        <div class="gms-contact-body">
                            <h5>Working Hours</h5>
                            <p>Mon – Sat: 8:00 AM – 7:00 PM<br />Sunday: 9:00 AM – 2:00 PM</p>
                        </div>
                    </div>

                </div>
            </div><!-- /col -->

            <!-- Contact form -->
            <div class="col-lg-7 reveal reveal-delay-2">
                <div class="gms-contact-form">
                    <h3 style="font-size:1.2rem;font-weight:700;color:var(--clr-dark-2);margin-bottom:1.5rem;">
                        <i class="bi bi-send-fill me-2 text-warning"></i>Send Us a Message
                    </h3>

                    <!-- NOTE: This form's action will be connected in a later step.
                         For now it demonstrates the layout and validation. -->
                    <form id="contactForm" class="needs-validation" novalidate
                          action="<?= BASE_URL ?>index.php#contact" method="POST">

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="contact_name" class="form-label">
                                    Full Name <span style="color:#ef4444">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="contact_name"
                                    name="contact_name"
                                    class="form-control"
                                    placeholder="Your full name"
                                    required
                                />
                                <div class="invalid-feedback">Please enter your name.</div>
                            </div>

                            <div class="col-sm-6">
                                <label for="contact_email" class="form-label">
                                    Email Address <span style="color:#ef4444">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="contact_email"
                                    name="contact_email"
                                    class="form-control"
                                    placeholder="your@email.com"
                                    required
                                />
                                <div class="invalid-feedback">Please enter a valid email.</div>
                            </div>

                            <div class="col-sm-6">
                                <label for="contact_phone" class="form-label">Phone Number</label>
                                <input
                                    type="tel"
                                    id="contact_phone"
                                    name="contact_phone"
                                    class="form-control"
                                    placeholder="+91 98765 43210"
                                />
                            </div>

                            <div class="col-sm-6">
                                <label for="contact_subject" class="form-label">Subject</label>
                                <select id="contact_subject" name="contact_subject" class="form-select">
                                    <option value="">Select a topic…</option>
                                    <option value="booking">Service Booking</option>
                                    <option value="pricing">Pricing Enquiry</option>
                                    <option value="complaint">Complaint</option>
                                    <option value="feedback">Feedback</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="contact_message" class="form-label">
                                    Message <span style="color:#ef4444">*</span>
                                </label>
                                <textarea
                                    id="contact_message"
                                    name="contact_message"
                                    class="form-control"
                                    placeholder="Tell us how we can help you…"
                                    required
                                ></textarea>
                                <div class="invalid-feedback">Please enter your message.</div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn-gms btn-gms-primary w-100">
                                    <i class="bi bi-send-fill"></i> Send Message
                                </button>
                            </div>
                        </div><!-- /row -->
                    </form>
                </div>
            </div><!-- /col -->

        </div><!-- /row -->
    </div><!-- /container -->
</section>


<!-- ═══════════════════════════════════════════════════════════
     CTA BANNER (inline, before footer)
     ═══════════════════════════════════════════════════════════ -->
<?php if (!isLoggedIn()): ?>
<section class="gms-cta-banner" id="get-started" aria-label="Call to Action">
    <div class="container text-center">
        <p class="section-label justify-content-center mb-2 reveal">
            <i class="bi bi-rocket-takeoff-fill"></i> Start Today
        </p>
        <h2 class="gms-cta-title mb-3 reveal reveal-delay-1">
            Ready to Book Your<br class="d-none d-md-block"/>
            First Service?
        </h2>
        <p class="gms-cta-sub mb-4 reveal reveal-delay-2">
            Join thousands of satisfied customers. Register free — takes less than 2 minutes.
        </p>
        <div class="d-flex justify-content-center flex-wrap gap-3 reveal reveal-delay-3">
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


<?php
// Footer (renders the HTML footer, loads JS, closes </body></html>)
require_once __DIR__ . '/includes/footer.php';
?>
