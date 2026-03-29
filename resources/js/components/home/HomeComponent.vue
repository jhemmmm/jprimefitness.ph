<template>
    <div class="content">
        <!-- Hero Section -->
        <section class="jprime-hero" id="home">
            <div class="hero-overlay"></div>
            <div class="container hero-content py-5">
                <div class="row align-items-center gy-5">
                    <!-- Left -->
                    <div class="col-lg-7">
                        <div class="hero-badge">
                            <i class="bi bi-lightning-charge-fill text-warning"></i>
                            Multi-branch gym &bull; Prices vary per location
                        </div>

                        <h1 class="hero-title">One membership mindset.</h1>
                        <h1 class="hero-title-red">Many locations.</h1>

                        <p class="hero-desc">Train at the branch nearest you. Each location has its own pricing, promos, and coaches - but the JPRIME standard stays the same: clean, safe, and result-driven.</p>

                        <div class="d-flex flex-wrap gap-3">
                            <a href="#branches" class="btn btn-danger px-4 py-2 fw-semibold rounded-1"> <i class="bi bi-geo-alt-fill me-2"></i>Find a Branch </a>
                            <a href="#membership" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-1"> How Membership Works </a>
                        </div>

                        <div class="d-flex flex-wrap hero-features mt-4">
                            <div class="hero-pill-item me-4 mb-2">
                                <div class="hero-pill-icon">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-white" style="font-size: 0.82rem">Clean &amp; Safe</div>
                                    <div class="text-white-50" style="font-size: 0.72rem">Safe hygienic conditions</div>
                                </div>
                            </div>
                            <div class="hero-pill-item me-4 mb-2">
                                <div class="hero-pill-icon">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-white" style="font-size: 0.82rem">Coaching</div>
                                    <div class="text-white-50" style="font-size: 0.72rem">Optional per branch</div>
                                </div>
                            </div>
                            <div class="hero-pill-item mb-2">
                                <div class="hero-pill-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-white" style="font-size: 0.82rem">Flexible Hours</div>
                                    <div class="text-white-50" style="font-size: 0.72rem">Open early &amp; late</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right -->
                    <div class="col-lg-5 col-xl-4 offset-xl-1">
                        <div class="card shadow-lg border-0">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-geo-alt-fill text-danger fs-5"></i>
                                    <h5 class="fw-bold mb-0">Quick Branch Lookup</h5>
                                </div>
                                <p class="text-muted small mb-3">Pick a branch to view pricing &amp; details</p>

                                <select v-model="selectedBranch" class="form-select rounded-1 mb-3">
                                    <option value="">Choose branch...</option>
                                    <option value="calabanga">JPRIME Fitness Calabanga</option>
                                    <option value="naga">JPRIME Fitness Naga</option>
                                    <option value="legazpi">JPRIME Fitness Legazpi</option>
                                </select>

                                <div class="d-grid gap-2">
                                    <button @click="viewBranch" :disabled="!selectedBranch" class="btn btn-danger rounded-1 fw-semibold">View Branch Page</button>
                                    <a href="/#branches" class="btn btn-outline-danger rounded-1 fw-semibold">Browse all branches</a>
                                </div>

                                <p class="text-muted small mt-3 mb-0"><strong>Note:</strong> Pricing, promos, and PT rates can differ per branch (check each branch page).</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="py-5 bg-white" id="about">
            <div class="container">
                <div class="row g-5 align-items-center">
                    <!-- Left: Story and stats -->
                    <div class="col-lg-6">
                        <div class="section-eyebrow mb-3">Our Story</div>
                        <h2 class="fw-bold mb-3">Fitness that fits your city,<br />your budget, your goals.</h2>
                        <p class="text-muted mb-3">JPRIME Fitness was built on a simple belief: quality gym access should not be locked behind one location or a premium price tag. We plant branches where communities need them, staff them with coaches who are invested in your progress, and hold every location to the same JPRIME standard of cleanliness, safety, and results.</p>
                        <p class="text-muted mb-4">Each branch sets its own local pricing and promos - so you get a rate that makes sense for your area, without sacrificing the training environment you deserve.</p>

                        <div class="row g-3 pt-4" style="border-top: 1px solid #eee">
                            <div class="col-6 col-sm-3">
                                <div class="text-center">
                                    <div class="fw-bold fs-3 text-danger">3+</div>
                                    <div class="small text-muted">Branches</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="text-center">
                                    <div class="fw-bold fs-3 text-danger">500+</div>
                                    <div class="small text-muted">Members</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="text-center">
                                    <div class="fw-bold fs-3 text-danger">10+</div>
                                    <div class="small text-muted">Coaches</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="text-center">
                                    <div class="fw-bold fs-3 text-danger">5+</div>
                                    <div class="small text-muted">Cities</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Pillars  -->
                    <div class="col-lg-6">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="program-icon">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1">Consistent Standards</h6>
                                        <p class="text-muted small mb-0">Every branch follows the same hygiene, safety, and equipment maintenance protocols.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="program-icon">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1">Community-Driven</h6>
                                        <p class="text-muted small mb-0">Our branches are rooted in local neighborhoods and built to serve real, everyday people.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="program-icon">
                                            <i class="bi bi-tag-fill"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1">Honest Pricing</h6>
                                        <p class="text-muted small mb-0">Local rates, no hidden fees, and flexible plans that fit different budgets.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="program-icon">
                                            <i class="bi bi-trophy-fill"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1">Result-Focused</h6>
                                        <p class="text-muted small mb-0">Coaches and programs built around measurable progress, not just showing up.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Find Your Nearest Branch -->
        <section class="py-5 bg-light" id="branches">
            <div class="container">
                <div class="row align-items-start align-items-md-center mb-5 gy-3">
                    <div class="col-md">
                        <div class="section-eyebrow mb-2">Locations</div>
                        <h2 class="fw-bold mb-1">Find your nearest branch</h2>
                        <p class="text-muted mb-0">Search by city/area. Tap a branch to see its prices and promos.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0 rounded-end-1" placeholder="Search branches (e.g. Calabanga, Naga...)" />
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Calabanga TEST -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 overflow-hidden">
                            <div class="branch-img-placeholder">
                                <span class="branch-status-badge branch-status-open">Open</span>
                            </div>
                            <div class="card-body pb-2">
                                <h5 class="fw-bold mb-1">JPRIME Fitness Calabanga</h5>
                                <p class="text-muted small mb-3"><i class="bi bi-clock me-1"></i>6am - 11pm</p>
                                <div class="pricing-row">
                                    <span class="pricing-label">Monthly</span>
                                    <span class="pricing-value">&#8369;1,600</span>
                                </div>
                                <div class="pricing-row">
                                    <span class="pricing-label">Daily</span>
                                    <span class="pricing-value">&#8369;100</span>
                                </div>
                                <div class="pricing-row">
                                    <span class="pricing-label">PT / session</span>
                                    <span class="pricing-value">&#8369;500</span>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-grid gap-2">
                                    <a href="/branches/calabanga" class="btn btn-danger rounded-1 fw-semibold btn-sm py-2">View branch</a>
                                    <button type="button" class="btn btn-outline-danger rounded-1 fw-semibold btn-sm py-2 branch-quick-view" data-bs-toggle="modal" data-bs-target="#branchQuickViewModal" data-name="JPRIME Fitness Calabanga" data-status="open" data-status-label="Open" data-address="Rizal St, Brgy. Centro, Calabanga, Camarines Sur" data-hours="6:00 AM &ndash; 11:00 PM" data-monthly="&#8369;1,600" data-daily="&#8369;100" data-pt="&#8369;500" data-amenities="Free Weights,Cable Machines,Cardio Area,Shower Room,Locker,AC,CCTV" data-url="/branches/calabanga" data-promo="1st month at &#8369;1,200 (&#8369;400 off) &mdash; limited slots">Quick view</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Naga TEST -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 overflow-hidden">
                            <div class="branch-img-placeholder">
                                <span class="branch-status-badge branch-status-soon">Coming Soon</span>
                            </div>
                            <div class="card-body pb-2">
                                <h5 class="fw-bold mb-1">JPRIME Fitness Naga</h5>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-clock me-1"></i>1am - 12am
                                    <span class="ms-1 fst-italic">- Magistrado has limited promos</span>
                                </p>
                                <div class="pricing-row">
                                    <span class="pricing-label">Monthly</span>
                                    <span class="pricing-value">&#8369;1,800</span>
                                </div>
                                <div class="pricing-row">
                                    <span class="pricing-label">Daily</span>
                                    <span class="pricing-value">&#8369;120</span>
                                </div>
                                <div class="pricing-row">
                                    <span class="pricing-label">PT / session</span>
                                    <span class="pricing-value">&#8369;400</span>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-grid gap-2">
                                    <a href="/branches/naga" class="btn btn-danger rounded-1 fw-semibold btn-sm py-2">View branch</a>
                                    <button type="button" class="btn btn-outline-danger rounded-1 fw-semibold btn-sm py-2 branch-quick-view" data-bs-toggle="modal" data-bs-target="#branchQuickViewModal" data-name="JPRIME Fitness Naga" data-status="soon" data-status-label="Coming Soon" data-address="Magistrado, Naga City, Camarines Sur" data-hours="1:00 AM &ndash; 12:00 AM" data-monthly="&#8369;1,800" data-daily="&#8369;120" data-pt="&#8369;400" data-amenities="Free Weights,Cable Machines,Cardio Area,Shower Room,Locker,AC" data-url="/branches/naga" data-promo="">Quick view</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Legazpi TEST -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 overflow-hidden">
                            <div class="branch-img-placeholder">
                                <span class="branch-status-badge branch-status-soon">Coming Soon</span>
                            </div>
                            <div class="card-body pb-2">
                                <h5 class="fw-bold mb-1">JPRIME Fitness Legazpi</h5>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-clock me-1"></i>6am - 10pm
                                    <span class="ms-1 fst-italic">- Satellite location</span>
                                </p>
                                <div class="pricing-row">
                                    <span class="pricing-label">Monthly</span>
                                    <span class="pricing-value">&#8369;1,700</span>
                                </div>
                                <div class="pricing-row">
                                    <span class="pricing-label">Daily</span>
                                    <span class="pricing-value">&#8369;120</span>
                                </div>
                                <div class="pricing-row">
                                    <span class="pricing-label">PT / session</span>
                                    <span class="pricing-value">&#8369;250</span>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-grid gap-2">
                                    <a href="/branches/legazpi" class="btn btn-danger rounded-1 fw-semibold btn-sm py-2">View branch</a>
                                    <button type="button" class="btn btn-outline-danger rounded-1 fw-semibold btn-sm py-2 branch-quick-view" data-bs-toggle="modal" data-bs-target="#branchQuickViewModal" data-name="JPRIME Fitness Legazpi" data-status="soon" data-status-label="Coming Soon" data-address="Satellite location, Legazpi City, Albay" data-hours="6:00 AM &ndash; 10:00 PM" data-monthly="&#8369;1,700" data-daily="&#8369;120" data-pt="&#8369;250" data-amenities="Free Weights,Cable Machines,Cardio Area,Shower Room" data-url="/branches/legazpi" data-promo="">Quick view</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Franchise CTA Banner -->
                <div class="franchise-cta mt-5">
                    <div>
                        <h4 class="fw-bold text-white mb-1">Want to open a JPRIME Fitness branch?</h4>
                        <p class="text-white-50 mb-0 small">We're building a network. Ask about partnership / franchising options.</p>
                    </div>
                    <a href="#contact" class="btn btn-danger px-4 py-2 fw-semibold rounded-1 flex-shrink-0"> Inquire Now </a>
                </div>
            </div>
        </section>

        <!-- Program Section -->
        <section class="py-5 bg-white" id="services">
            <div class="container">
                <div class="mb-5">
                    <div class="section-eyebrow mb-2">What we offer</div>
                    <h2 class="fw-bold mb-1">Programs for every goal</h2>
                    <p class="text-muted mb-0">Your branch may offer additional classes, but these are our core options.</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="program-icon">
                                    <i class="bi bi-bar-chart-line-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Strength Training</h5>
                                <p class="text-muted small mb-0">Data-backed progressions, progressive overload, and form coaching.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="program-icon">
                                    <i class="bi bi-fire"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Fat Loss &amp; Conditioning</h5>
                                <p class="text-muted small mb-0">Circuit work, intervals, and sustainable weekly plans.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="program-icon">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Functional &amp; Performance</h5>
                                <p class="text-muted small mb-0">Mobility, endurance, and athletic movement training.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How Membership Works -->
        <section class="py-5 bg-light" id="membership">
            <div class="container">
                <div class="row g-5">
                    <!-- Left: Steps -->
                    <div class="col-lg-6">
                        <div class="section-eyebrow mb-2">Getting started</div>
                        <h2 class="fw-bold mb-2">How membership works</h2>
                        <p class="text-muted mb-4">Pick the branch closest to you, choose a plan that fits your schedule and budget, then walk in and train. Your membership is tied to your home branch - pricing, coaching options, and promos are managed locally so everything stays relevant to you.</p>

                        <div class="step-item">
                            <div class="step-number flex-shrink-0">1</div>
                            <div>
                                <h6 class="fw-bold mb-1">Pick a branch</h6>
                                <p class="text-muted small mb-0">Select the location you'll mostly train at.</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number flex-shrink-0">2</div>
                            <div>
                                <h6 class="fw-bold mb-1">Choose your plan</h6>
                                <p class="text-muted small mb-0">Walk-in, monthly, or bundles (3, 6, 12 month packs).</p>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number flex-shrink-0">3</div>
                            <div>
                                <h6 class="fw-bold mb-1">Optional coaching</h6>
                                <p class="text-muted small mb-0">Personal training rates differ per branch.</p>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <a href="#branches" class="btn btn-dark px-4 fw-semibold rounded-1">Compare Branch Prices</a>
                            <a href="#contact" class="btn btn-outline-secondary px-4 fw-semibold rounded-1">Ask a Question</a>
                        </div>
                    </div>

                    <!-- Right: Benefits -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4">What you'll typically get</h5>

                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check-circle-fill text-danger mt-1 flex-shrink-0"></i>
                                            <div>
                                                <div class="fw-semibold" style="font-size: 0.85rem">Gym Access</div>
                                                <div class="text-muted" style="font-size: 0.75rem">Full equipment &amp; facilities</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check-circle-fill text-danger mt-1 flex-shrink-0"></i>
                                            <div>
                                                <div class="fw-semibold" style="font-size: 0.85rem">Progress Tracking</div>
                                                <div class="text-muted" style="font-size: 0.75rem">Basic, can be more</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check-circle-fill text-danger mt-1 flex-shrink-0"></i>
                                            <div>
                                                <div class="fw-semibold" style="font-size: 0.85rem">Promos</div>
                                                <div class="text-muted" style="font-size: 0.75rem">Depends on branch</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check-circle-fill text-danger mt-1 flex-shrink-0"></i>
                                            <div>
                                                <div class="fw-semibold" style="font-size: 0.85rem">Coaching</div>
                                                <div class="text-muted" style="font-size: 0.75rem">Optional PT add-on</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tip-box"><strong>Tip:</strong> For exact prices on each Branch Page, use the app - it's always accurate.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="py-5 bg-white" id="contact">
            <div class="container">
                <div class="section-eyebrow mb-2">Get in touch</div>
                <h2 class="fw-bold mb-1">Contact us</h2>
                <p class="text-muted mb-5">Ask about branches, memberships, promos, or partnership opportunities.</p>
                <div class="row g-4">
                    <!-- Left: Contact Form -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4">Send us a message</h5>
                                <form method="POST" action="#">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Full Name</label>
                                        <input type="text" class="form-control rounded-1" placeholder="Your name" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Email / Phone / Messenger</label>
                                        <input type="text" class="form-control rounded-1" placeholder="How we can reach you" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Inquiry</label>
                                        <select class="form-select rounded-1">
                                            <option>Membership</option>
                                            <option>Franchise / Partnership</option>
                                            <option>Coaching / PT</option>
                                            <option>General Question</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Message</label>
                                        <textarea class="form-control rounded-1" rows="4" placeholder="Type your message..."></textarea>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-danger fw-semibold rounded-1 py-2">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Quick Links and Hours -->
                    <div class="col-lg-5">
                        <div class="card mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Quick links</h5>
                                <div class="d-grid gap-2">
                                    <a href="#branches" class="quick-links-btn">
                                        <i class="bi bi-geo-alt-fill text-danger"></i>
                                        View branches
                                    </a>
                                    <a href="#membership" class="quick-links-btn">
                                        <i class="bi bi-person-badge-fill text-danger"></i>
                                        Membership Info
                                    </a>
                                    <a href="#services" class="quick-links-btn">
                                        <i class="bi bi-activity text-danger"></i>
                                        Program
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Business hours</h5>
                                <div class="text-center py-3 text-muted">
                                    <i class="bi bi-map fs-2 d-block mb-2"></i>
                                    <small>Go to an actual branch page for more details.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal -->
        <div class="modal fade" id="branchQuickViewModal" tabindex="-1" aria-labelledby="branchQuickViewLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 overflow-hidden">
                    <!-- Photo bar -->
                    <div class="branch-qv-photo position-relative">
                        <span id="qvStatusBadge" class="branch-status-badge"></span>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-0">
                        <div class="row g-0">
                            <!-- Left: Info -->
                            <div class="col-md-7 p-4">
                                <h4 id="qvName" class="fw-bold mb-1"></h4>
                                <div class="d-flex align-items-center gap-2 text-muted small mb-3">
                                    <i class="bi bi-geo-alt-fill text-danger flex-shrink-0"></i>
                                    <span id="qvAddress"></span>
                                </div>

                                <!-- Hours -->
                                <div class="d-flex align-items-center gap-2 mb-3 text-muted small">
                                    <i class="bi bi-clock-fill text-danger"></i>
                                    <span id="qvHours"></span>
                                </div>

                                <!-- Rates -->
                                <h6 class="fw-bold mb-2">Rates</h6>
                                <div id="qvRates" class="mb-3">
                                    <div class="pricing-row">
                                        <span class="pricing-label">Monthly</span>
                                        <span id="qvMonthly" class="pricing-value"></span>
                                    </div>
                                    <div class="pricing-row">
                                        <span class="pricing-label">Daily / Walk-in</span>
                                        <span id="qvDaily" class="pricing-value"></span>
                                    </div>
                                    <div class="pricing-row">
                                        <span class="pricing-label">PT / session</span>
                                        <span id="qvPt" class="pricing-value"></span>
                                    </div>
                                </div>

                                <!-- Promo -->
                                <div id="qvPromoWrap" class="branch-promo-card py-3 px-3 mb-0" style="display: none !important">
                                    <div class="fw-bold" style="font-size: 0.7rem; letter-spacing: 1px; opacity: 0.75; text-transform: uppercase">Active Promo</div>
                                    <div id="qvPromo" class="small mt-1" style="opacity: 0.9"></div>
                                </div>
                            </div>

                            <!-- Right: Amenities + CTA  -->
                            <div class="col-md-5 p-4 bg-light border-start">
                                <h6 class="fw-bold mb-3">Amenities</h6>
                                <div id="qvAmenities" class="d-flex flex-wrap gap-2 mb-4"></div>

                                <div class="d-grid gap-2 mt-auto">
                                    <a id="qvViewBtn" href="#" class="btn btn-danger rounded-1 fw-semibold"> <i class="bi bi-arrow-right-circle me-1"></i>View full branch page </a>
                                    <a id="qvDirectionsBtn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark rounded-1 fw-semibold"> <i class="bi bi-map-fill me-1"></i>Get Directions </a>
                                    <button class="btn btn-outline-secondary rounded-1 fw-semibold" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data: function () {
        return {
            selectedBranch: "",
        };
    },
    mounted: function () {
        this.init();
    },
    methods: {
        viewBranch: function () {
            if (this.selectedBranch) {
                window.location.href = "/branches/" + this.selectedBranch;
            }
        },
        init: function () {
            document.addEventListener("DOMContentLoaded", function () {
                var modal = document.getElementById("branchQuickViewModal");
                modal.addEventListener("show.bs.modal", function (e) {
                    var btn = e.relatedTarget;

                    var name = btn.dataset.name;
                    var status = btn.dataset.status; // 'open' | 'soon'
                    var slabel = btn.dataset.statusLabel;
                    var address = btn.dataset.address;
                    var hours = btn.dataset.hours;
                    var monthly = btn.dataset.monthly;
                    var daily = btn.dataset.daily;
                    var pt = btn.dataset.pt;
                    var amenities = btn.dataset.amenities.split(",");
                    var url = btn.dataset.url;
                    var promo = btn.dataset.promo;

                    // Name
                    modal.querySelector("#qvName").textContent = name;

                    // Address
                    modal.querySelector("#qvAddress").innerHTML = address;

                    // Directions link
                    modal.querySelector("#qvDirectionsBtn").href = "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(address);

                    // Hours
                    modal.querySelector("#qvHours").innerHTML = hours;

                    // Status badge
                    var badge = modal.querySelector("#qvStatusBadge");
                    badge.textContent = slabel;
                    badge.className = "branch-status-badge branch-status-" + status;

                    // Rates
                    modal.querySelector("#qvMonthly").innerHTML = monthly;
                    modal.querySelector("#qvDaily").innerHTML = daily;
                    modal.querySelector("#qvPt").innerHTML = pt;

                    // Promo
                    var promoWrap = modal.querySelector("#qvPromoWrap");
                    if (promo) {
                        promoWrap.style.removeProperty("display");
                        modal.querySelector("#qvPromo").innerHTML = promo;
                    } else {
                        promoWrap.style.setProperty("display", "none", "important");
                    }

                    // Amenities
                    var amenBox = modal.querySelector("#qvAmenities");
                    amenBox.innerHTML = "";
                    amenities.forEach(function (a) {
                        if (!a.trim()) return;
                        var span = document.createElement("span");
                        span.className = "branch-amenity-badge";
                        span.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>' + a.trim();
                        amenBox.appendChild(span);
                    });

                    // CTA link
                    modal.querySelector("#qvViewBtn").href = url;
                });
            });
        },
    },
};
</script>
