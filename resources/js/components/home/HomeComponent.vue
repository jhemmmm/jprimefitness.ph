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
                     {{ heroBadge }}
                  </div>

                  <h1 class="hero-title">Train smarter.</h1>
                  <h1 class="hero-title-red">Live stronger.</h1>

                  <p class="hero-desc">{{ business.name }} is built for real progress - clean facilities, modern equipment, and coaches who actually care. One home, one standard, one community committed to your goals.</p>

                  <div class="d-flex flex-wrap gap-3">
                     <a href="#membership" class="btn btn-danger px-4 py-2 fw-semibold rounded-1"> <i class="bi bi-person-plus-fill me-2"></i>Join Now </a>
                     <a href="#pricing" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-1"> View Pricing </a>
                  </div>

                  <div class="d-flex flex-wrap hero-features mt-4">
                     <div class="hero-pill-item me-4 mb-2">
                        <div class="hero-pill-icon">
                           <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                           <div class="fw-semibold text-white" style="font-size: 0.82rem">Clean &amp; Safe</div>
                           <div class="text-white-50" style="font-size: 0.72rem">Hygienic conditions</div>
                        </div>
                     </div>
                     <div class="hero-pill-item me-4 mb-2">
                        <div class="hero-pill-icon">
                           <i class="bi bi-person-badge"></i>
                        </div>
                        <div>
                           <div class="fw-semibold text-white" style="font-size: 0.82rem">Coaching</div>
                           <div class="text-white-50" style="font-size: 0.72rem">Optional PT add-on</div>
                        </div>
                     </div>
                     <div class="hero-pill-item mb-2">
                        <div class="hero-pill-icon">
                           <i class="bi bi-clock"></i>
                        </div>
                        <div>
                           <div class="fw-semibold text-white" style="font-size: 0.82rem">Flexible Hours</div>
                           <div class="text-white-50" style="font-size: 0.72rem">{{ hoursShort }}</div>
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
                           <h5 class="fw-bold mb-0">Visit Us</h5>
                        </div>
                        <p class="text-muted small mb-3">Drop by anytime during opening hours</p>

                        <div class="pricing-row">
                           <span class="pricing-label"><i class="bi bi-geo-alt me-1"></i>Address</span>
                           <span class="pricing-value text-end" style="font-size: 0.85rem">{{ addressLine || 'Address coming soon' }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label"><i class="bi bi-clock me-1"></i>Hours</span>
                           <span class="pricing-value">{{ hoursLong }}</span>
                        </div>
                        <div class="pricing-row" v-if="business.timezone">
                           <span class="pricing-label"><i class="bi bi-globe me-1"></i>Timezone</span>
                           <span class="pricing-value">{{ business.timezone }}</span>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                           <a href="#register" class="btn btn-danger rounded-1 fw-semibold">Become a Member</a>
                           <a :href="directionsUrl" target="_blank" rel="noopener noreferrer" class="btn btn-outline-danger rounded-1 fw-semibold">Get Directions</a>
                        </div>

                        <p class="text-muted small mt-3 mb-0"><strong>Tip:</strong> First-time visitors can try a walk-in session before committing to a plan.</p>
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
                  <h2 class="fw-bold mb-3">Fitness that fits your life,<br />your budget, your goals.</h2>
                  <p class="text-muted mb-3">{{ business.name }} was built on a simple belief: quality gym access should not be locked behind a premium price tag. We're here to serve our community with a training environment that's clean, safe, and built for results.</p>
                  <p class="text-muted mb-4">Our coaches are invested in your progress, our equipment is maintained to a high standard, and our pricing is straightforward - so you can focus on what matters: showing up and getting stronger.</p>

                  <div class="row g-3 pt-4" style="border-top: 1px solid #eee">
                     <div class="col-6 col-sm-3">
                        <div class="text-center">
                           <div class="fw-bold fs-3 text-danger">{{ ratePlans.length || '–' }}</div>
                           <div class="small text-muted">Membership Plans</div>
                        </div>
                     </div>
                     <div class="col-6 col-sm-3">
                        <div class="text-center">
                           <div class="fw-bold fs-3 text-danger">{{ ptProducts.length || '–' }}</div>
                           <div class="small text-muted">PT Packages</div>
                        </div>
                     </div>
                     <div class="col-6 col-sm-3">
                        <div class="text-center">
                           <div class="fw-bold fs-3 text-danger">{{ openHoursPerDay }}h</div>
                           <div class="small text-muted">Open Daily</div>
                        </div>
                     </div>
                     <div class="col-6 col-sm-3">
                        <div class="text-center">
                           <div class="fw-bold fs-3 text-danger">7</div>
                           <div class="small text-muted">Days a Week</div>
                        </div>
                     </div>
                  </div>
               </div>

               <!-- Right: Pillars  -->
               <div class="col-lg-6">
                  <div class="about-photo mb-3" :style="{ backgroundImage: `url(${images.about})` }">
                     <div class="about-photo-caption">
                        <i class="bi bi-quote text-danger"></i>
                        <span>Where the community trains.</span>
                     </div>
                  </div>
                  <div class="row g-3">
                     <div class="col-sm-6">
                        <div class="card h-100">
                           <div class="card-body">
                              <div class="program-icon">
                                 <i class="bi bi-patch-check-fill"></i>
                              </div>
                              <h6 class="fw-bold mb-1">Consistent Standards</h6>
                              <p class="text-muted small mb-0">Strict hygiene, safety, and equipment maintenance protocols followed every day.</p>
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
                              <p class="text-muted small mb-0">Rooted in the local neighborhood and built to serve real, everyday people.</p>
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
                              <p class="text-muted small mb-0">Fair rates, no hidden fees, and flexible plans that fit different budgets.</p>
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

      <!-- Pricing Section -->
      <section class="py-5 bg-light" id="pricing">
         <div class="container">
            <div class="row align-items-start align-items-md-center mb-5 gy-3">
               <div class="col-md">
                  <div class="section-eyebrow mb-2">Membership</div>
                  <h2 class="fw-bold mb-1">Simple, honest pricing</h2>
                  <p class="text-muted mb-0">Choose the plan that fits how often you train. No hidden fees.</p>
               </div>
               <div class="col-md-auto">
                  <span class="branch-status-badge branch-status-open">Open &bull; Accepting members</span>
               </div>
            </div>

            <!-- Membership Plans -->
            <h5 class="fw-bold mb-3"><i class="bi bi-card-checklist text-danger me-2"></i>Membership Plans</h5>
            <div class="vstack gap-3 mb-5" v-if="ratePlans.length">
               <div
                  v-for="plan in indexedRatePlans"
                  :key="'rp-' + plan.id"
                  class="plan-row"
                  :class="{ 'plan-row-featured': plan._idx === 1 }"
               >
                  <div class="plan-row-accent">
                     <div class="plan-row-icon"><i class="bi bi-card-checklist"></i></div>
                  </div>

                  <div class="plan-row-head">
                     <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h5 class="fw-bold mb-0">{{ plan.name }}</h5>
                        <span v-if="plan._idx === 1" class="branch-status-badge branch-status-open">Most Popular</span>
                     </div>
                     <p class="text-muted small mb-0" v-if="plan.description">{{ plan.description }}</p>
                     <p class="text-muted small mb-0" v-else>{{ plan.duration_days }}-day membership</p>
                  </div>

                  <div class="plan-row-meta">
                     <div class="plan-meta-item">
                        <div class="plan-meta-label">Duration</div>
                        <div class="plan-meta-value">{{ plan.duration_days }} day<span v-if="plan.duration_days != 1">s</span></div>
                     </div>
                     <div class="plan-meta-item">
                        <div class="plan-meta-label">Per day</div>
                        <div class="plan-meta-value">&#8369;{{ $filters.formatMoney(perDay(plan)) }}</div>
                     </div>
                  </div>

                  <div class="plan-row-price">
                     <div class="plan-price-amount">&#8369;{{ $filters.formatMoney(plan.price) }}</div>
                     <a :href="`#register?plan=${plan.id}`" class="btn btn-danger rounded-1 fw-semibold btn-sm py-2 px-3 mt-2" @click.prevent="selectPlanForRegister(plan.id)">Become a member</a>
                  </div>
               </div>
            </div>
            <div class="row g-4 mb-5" v-else>
               <div class="col-12">
                  <div class="card border-0 bg-white"><div class="card-body p-4 text-center text-muted"><i class="bi bi-hourglass-split fs-4 d-block mb-2 text-danger"></i>Membership plans will be published soon.</div></div>
               </div>
            </div>

            <!-- PT Packages -->
            <h5 class="fw-bold mb-3"><i class="bi bi-person-badge text-danger me-2"></i>Personal Training Packages</h5>
            <div class="row g-4" v-if="ptProducts.length">
               <div class="col-sm-6 col-lg-4 col-xl-3" v-for="(pt, idx) in ptProducts" :key="'pt-' + pt.id">
                  <div class="card h-100 overflow-hidden plan-card">
                     <div class="branch-img-placeholder has-image" :style="{ backgroundImage: `url(${ptImage(idx)})` }">
                        <span class="branch-status-badge branch-status-soon">{{ pt.session_count > 1 ? 'Package' : '1-on-1' }}</span>
                     </div>
                     <div class="card-body pb-2">
                        <h5 class="fw-bold mb-1">{{ pt.name }}</h5>
                        <p class="text-muted small mb-3" v-if="pt.description"><i class="bi bi-info-circle me-1"></i>{{ pt.description }}</p>
                        <p class="text-muted small mb-3" v-else><i class="bi bi-stopwatch me-1"></i>{{ pt.session_count }} coached session<span v-if="pt.session_count > 1">s</span></p>
                        <div class="pricing-row">
                           <span class="pricing-label">Price</span>
                           <span class="pricing-value">&#8369;{{ $filters.formatMoney(pt.price) }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label">Sessions</span>
                           <span class="pricing-value">{{ pt.session_count }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label">Per session</span>
                           <span class="pricing-value">&#8369;{{ $filters.formatMoney(perSession(pt)) }}</span>
                        </div>
                     </div>
                     <div class="card-footer">
                        <div class="d-grid gap-2">
                           <a href="#contact" class="btn btn-danger rounded-1 fw-semibold btn-sm py-2">Inquire about PT</a>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="row g-4" v-else>
               <div class="col-12">
                  <div class="card border-0 bg-white"><div class="card-body p-4 text-center text-muted"><i class="bi bi-hourglass-split fs-4 d-block mb-2 text-danger"></i>PT packages will be published soon.</div></div>
               </div>
            </div>

            <div class="franchise-cta mt-5">
               <div>
                  <h4 class="fw-bold text-white mb-1">Not ready to commit? Try a walk-in session.</h4>
                  <p class="text-white-50 mb-0 small">Daily passes available - train first, decide later.</p>
               </div>
               <a href="#contact" class="btn btn-danger px-4 py-2 fw-semibold rounded-1 flex-shrink-0"> Walk-in Today </a>
            </div>
         </div>
      </section>

      <!-- Programs -->
      <section class="py-5 bg-white" id="services">
         <div class="container">
            <div class="mb-5">
               <div class="section-eyebrow mb-2">What we offer</div>
               <h2 class="fw-bold mb-1">Programs for every goal</h2>
               <p class="text-muted mb-0">Whether you're starting out or chasing a PR, we have a track for you.</p>
            </div>
            <div class="row g-4">
               <div class="col-md-4">
                  <div class="card h-100 overflow-hidden program-card">
                     <div class="program-img" :style="{ backgroundImage: `url(${images.programs.strength})` }"></div>
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
                        <h5 class="fw-bold mb-2">Strength Training</h5>
                        <p class="text-muted small mb-0">Data-backed progressions, progressive overload, and form coaching.</p>
                     </div>
                  </div>
               </div>
               <div class="col-md-4">
                  <div class="card h-100 overflow-hidden program-card">
                     <div class="program-img" :style="{ backgroundImage: `url(${images.programs.conditioning})` }"></div>
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-fire"></i></div>
                        <h5 class="fw-bold mb-2">Fat Loss &amp; Conditioning</h5>
                        <p class="text-muted small mb-0">Circuit work, intervals, and sustainable weekly plans.</p>
                     </div>
                  </div>
               </div>
               <div class="col-md-4">
                  <div class="card h-100 overflow-hidden program-card">
                     <div class="program-img" :style="{ backgroundImage: `url(${images.programs.functional})` }"></div>
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                        <h5 class="fw-bold mb-2">Functional &amp; Performance</h5>
                        <p class="text-muted small mb-0">Mobility, endurance, and athletic movement training.</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- Member Experience / Features -->
      <section class="py-5 bg-light" id="features">
         <div class="container">
            <div class="mb-5">
               <div class="section-eyebrow mb-2">Member Experience</div>
               <h2 class="fw-bold mb-1">A modern gym, run modernly</h2>
               <p class="text-muted mb-0">Everything we do is tracked, organized, and made easy for our members and staff.</p>
            </div>
            <div class="row g-4">
               <div class="col-md-6 col-lg-3">
                  <div class="card h-100">
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-upc-scan"></i></div>
                        <h6 class="fw-bold mb-1">Kiosk QR Check-in</h6>
                        <p class="text-muted small mb-0">Scan your membership QR at the kiosk - no clipboard sign-ins, no waiting.</p>
                     </div>
                  </div>
               </div>
               <div class="col-md-6 col-lg-3">
                  <div class="card h-100">
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-qr-code-scan"></i></div>
                        <h6 class="fw-bold mb-1">Digital Membership</h6>
                        <p class="text-muted small mb-0">QR-coded membership card with auto-renewal reminders before expiry.</p>
                     </div>
                  </div>
               </div>
               <div class="col-md-6 col-lg-3">
                  <div class="card h-100">
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-clipboard-data"></i></div>
                        <h6 class="fw-bold mb-1">Tracked PT Sessions</h6>
                        <p class="text-muted small mb-0">Every coached session logged, so you and your trainer see real progress.</p>
                     </div>
                  </div>
               </div>
               <div class="col-md-6 col-lg-3">
                  <div class="card h-100">
                     <div class="card-body">
                        <div class="program-icon"><i class="bi bi-person-walking"></i></div>
                        <h6 class="fw-bold mb-1">Walk-ins Welcome</h6>
                        <p class="text-muted small mb-0">No commitment? No problem. Drop in on any open day and just train.</p>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Amenities strip -->
            <div class="card border-0 mt-4" v-if="amenities.length">
               <div class="card-body p-4">
                  <div class="d-flex align-items-center gap-2 mb-3">
                     <i class="bi bi-gem text-danger"></i>
                     <h6 class="fw-bold mb-0">Amenities at the gym</h6>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                     <span v-for="a in amenities" :key="a" class="amenity-pill">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>{{ a }}
                     </span>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- How Membership Works -->
      <section class="py-5 bg-white" id="membership">
         <div class="container">
            <div class="row g-5">
               <div class="col-lg-6">
                  <div class="section-eyebrow mb-2">Getting started</div>
                  <h2 class="fw-bold mb-2">How membership works</h2>
                  <p class="text-muted mb-4">Pick a plan that fits your schedule and budget, then walk in and train. Pricing is straightforward, coaching is optional, and our team will help you settle in on day one.</p>

                  <div class="step-item">
                     <div class="step-number flex-shrink-0">1</div>
                     <div>
                        <h6 class="fw-bold mb-1">Register online</h6>
                        <p class="text-muted small mb-0">Fill in the form below, choose your plan, and pick how you'd like to pay.</p>
                     </div>
                  </div>
                  <div class="step-item">
                     <div class="step-number flex-shrink-0">2</div>
                     <div>
                        <h6 class="fw-bold mb-1">Pay online or at the gym</h6>
                        <p class="text-muted small mb-0">GCash, Maya, or card via PayMongo - or settle on-site when you arrive.</p>
                     </div>
                  </div>
                  <div class="step-item">
                     <div class="step-number flex-shrink-0">3</div>
                     <div>
                        <h6 class="fw-bold mb-1">Get your QR card</h6>
                        <p class="text-muted small mb-0">Once payment is confirmed, your QR membership card is emailed to you.</p>
                     </div>
                  </div>
                  <div class="step-item">
                     <div class="step-number flex-shrink-0">4</div>
                     <div>
                        <h6 class="fw-bold mb-1">Train</h6>
                        <p class="text-muted small mb-0">Scan in at the kiosk and go. Add personal training whenever you're ready.</p>
                     </div>
                  </div>

                  <div class="d-flex flex-wrap gap-3 mt-4">
                     <a href="#pricing" class="btn btn-dark px-4 fw-semibold rounded-1">View Pricing</a>
                     <a href="#contact" class="btn btn-outline-secondary px-4 fw-semibold rounded-1">Ask a Question</a>
                  </div>
               </div>

               <div class="col-lg-6">
                  <div class="card">
                     <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">What you'll get</h5>
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
                                    <div class="fw-semibold" style="font-size: 0.85rem">Attendance Logs</div>
                                    <div class="text-muted" style="font-size: 0.75rem">Track every visit</div>
                                 </div>
                              </div>
                           </div>
                           <div class="col-6">
                              <div class="d-flex align-items-start gap-2">
                                 <i class="bi bi-check-circle-fill text-danger mt-1 flex-shrink-0"></i>
                                 <div>
                                    <div class="fw-semibold" style="font-size: 0.85rem">Renewal Reminders</div>
                                    <div class="text-muted" style="font-size: 0.75rem">Before membership expires</div>
                                 </div>
                              </div>
                           </div>
                           <div class="col-6">
                              <div class="d-flex align-items-start gap-2">
                                 <i class="bi bi-check-circle-fill text-danger mt-1 flex-shrink-0"></i>
                                 <div>
                                    <div class="fw-semibold" style="font-size: 0.85rem">Optional Coaching</div>
                                    <div class="text-muted" style="font-size: 0.75rem">Add PT add-on anytime</div>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="tip-box"><strong>Tip:</strong> Try a walk-in pass first to see if {{ business.name }} is the right fit before committing to a monthly plan.</div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- Register Section -->
      <section class="py-5 bg-light" id="register">
         <div class="container">
            <div class="section-eyebrow mb-2">Sign Up</div>
            <h2 class="fw-bold mb-1">Create your membership</h2>
            <p class="text-muted mb-5">Fill in your details, pick a plan, and choose how you'd like to pay. We'll see you at {{ business.name }}.</p>

            <div class="row g-4">
               <!-- Form -->
               <div class="col-lg-7">
                  <div class="card border-0 shadow-sm">
                     <div class="card-body p-4">
                        <form method="POST" action="/register" @submit.prevent="submitRegistration" novalidate>
                           <div v-if="register.bannerError" class="alert alert-danger small">{{ register.bannerError }}</div>

                           <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Personal info</h6>
                           <div class="row g-3 mb-3">
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Full name <span class="text-danger">*</span></label>
                                 <input v-model="register.form.name" type="text" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.name }" placeholder="Your full name" />
                                 <div class="invalid-feedback" v-if="registerErrors.name">{{ registerErrors.name[0] }}</div>
                              </div>
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                                 <input v-model="register.form.email" type="email" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.email }" placeholder="you@example.com" />
                                 <div class="invalid-feedback" v-if="registerErrors.email">{{ registerErrors.email[0] }}</div>
                              </div>
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                                 <input v-model="register.form.phone" type="tel" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.phone }" placeholder="0917..." />
                                 <div class="invalid-feedback" v-if="registerErrors.phone">{{ registerErrors.phone[0] }}</div>
                              </div>
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Date of birth</label>
                                 <input v-model="register.form.date_of_birth" type="date" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.date_of_birth }" />
                                 <div class="invalid-feedback" v-if="registerErrors.date_of_birth">{{ registerErrors.date_of_birth[0] }}</div>
                              </div>
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Gender</label>
                                 <select v-model="register.form.gender" class="form-select rounded-1" :class="{ 'is-invalid': registerErrors.gender }">
                                    <option value="">Prefer not to say</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                 </select>
                                 <div class="invalid-feedback" v-if="registerErrors.gender">{{ registerErrors.gender[0] }}</div>
                              </div>
                           </div>

                           <h6 class="text-uppercase text-muted fw-bold mt-4 mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Emergency contact</h6>
                           <div class="row g-3 mb-3">
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Contact name</label>
                                 <input v-model="register.form.emergency_contact_name" type="text" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.emergency_contact_name }" />
                                 <div class="invalid-feedback" v-if="registerErrors.emergency_contact_name">{{ registerErrors.emergency_contact_name[0] }}</div>
                              </div>
                              <div class="col-md-6">
                                 <label class="form-label small fw-semibold">Contact phone</label>
                                 <input v-model="register.form.emergency_contact_phone" type="tel" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.emergency_contact_phone }" />
                                 <div class="invalid-feedback" v-if="registerErrors.emergency_contact_phone">{{ registerErrors.emergency_contact_phone[0] }}</div>
                              </div>
                           </div>

                           <h6 class="text-uppercase text-muted fw-bold mt-4 mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Membership</h6>
                           <div class="row g-3 mb-3">
                              <div class="col-md-7">
                                 <label class="form-label small fw-semibold">Plan <span class="text-danger">*</span></label>
                                 <select v-model="register.form.rate_plan_id" class="form-select rounded-1" :class="{ 'is-invalid': registerErrors.rate_plan_id }">
                                    <option value="">Choose a plan</option>
                                    <option v-for="plan in membershipPlans" :key="'reg-rp-' + plan.id" :value="plan.id">
                                       {{ plan.name }} — &#8369;{{ $filters.formatMoney(plan.price) }} / {{ plan.duration_days }}d
                                    </option>
                                 </select>
                                 <div class="invalid-feedback" v-if="registerErrors.rate_plan_id">{{ registerErrors.rate_plan_id[0] }}</div>
                              </div>
                              <div class="col-md-5">
                                 <label class="form-label small fw-semibold">Preferred start date</label>
                                 <input v-model="register.form.preferred_start_date" type="date" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.preferred_start_date }" />
                                 <div class="invalid-feedback" v-if="registerErrors.preferred_start_date">{{ registerErrors.preferred_start_date[0] }}</div>
                              </div>
                              <div class="col-12">
                                 <label class="form-label small fw-semibold">Notes (optional)</label>
                                 <textarea v-model="register.form.notes" rows="3" class="form-control rounded-1" :class="{ 'is-invalid': registerErrors.notes }" placeholder="Anything we should know? (injuries, training goals, etc.)"></textarea>
                                 <div class="invalid-feedback" v-if="registerErrors.notes">{{ registerErrors.notes[0] }}</div>
                              </div>
                           </div>

                           <h6 class="text-uppercase text-muted fw-bold mt-4 mb-1" style="font-size: 0.72rem; letter-spacing: 1.2px">Discount</h6>
                           <p class="text-muted small mb-3">Saved to your profile and applied to renewals automatically.</p>
                           <div class="row g-3 mb-3">
                              <div class="col-md-4">
                                 <label class="payment-option h-100" :class="{ 'is-active': register.form.discount_type === '' }">
                                    <input type="radio" v-model="register.form.discount_type" value="" @change="onDiscountChange" />
                                    <div>
                                       <div class="fw-semibold"><i class="bi bi-x-circle me-1"></i>None</div>
                                       <div class="text-muted small">Regular rate</div>
                                    </div>
                                 </label>
                              </div>
                              <div class="col-md-4">
                                 <label class="payment-option h-100" :class="{ 'is-active': register.form.discount_type === 'student' }">
                                    <input type="radio" v-model="register.form.discount_type" value="student" @change="onDiscountChange" />
                                    <div>
                                       <div class="fw-semibold"><i class="bi bi-mortarboard me-1"></i>Student</div>
                                       <div class="text-muted small">20% off — bring valid school ID</div>
                                    </div>
                                 </label>
                              </div>
                              <div class="col-md-4">
                                 <label class="payment-option h-100" :class="{ 'is-active': register.form.discount_type === 'senior' }">
                                    <input type="radio" v-model="register.form.discount_type" value="senior" @change="onDiscountChange" />
                                    <div>
                                       <div class="fw-semibold"><i class="bi bi-person-badge me-1"></i>Senior</div>
                                       <div class="text-muted small">20% off — bring senior citizen ID</div>
                                    </div>
                                 </label>
                              </div>
                              <div class="col-12 invalid-feedback d-block" v-if="registerErrors.discount_type">{{ registerErrors.discount_type[0] }}</div>
                           </div>

                           <div v-if="hasDiscount && discountSummary" class="alert alert-info small mb-3 py-2">
                              <i class="bi bi-info-circle me-1"></i>
                              {{ discountSummary }}
                           </div>

                           <h6 class="text-uppercase text-muted fw-bold mt-4 mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Payment</h6>
                           <div class="row g-3 mb-3">
                              <div class="col-md-6">
                                 <label class="payment-option" :class="{ 'is-active': register.form.payment_method === 'online', 'opacity-50': hasDiscount }" :style="hasDiscount ? 'cursor: not-allowed;' : ''">
                                    <input type="radio" v-model="register.form.payment_method" value="online" :disabled="hasDiscount" />
                                    <div>
                                       <div class="fw-semibold"><i class="bi bi-credit-card-2-front me-1"></i>Pay online now</div>
                                       <div class="text-muted small" v-if="hasDiscount">Unavailable — staff must verify your ID on-site</div>
                                       <div class="text-muted small" v-else>GCash, Maya, card via PayMongo</div>
                                    </div>
                                 </label>
                              </div>
                              <div class="col-md-6">
                                 <label class="payment-option" :class="{ 'is-active': register.form.payment_method === 'on_site' }">
                                    <input type="radio" v-model="register.form.payment_method" value="on_site" />
                                    <div>
                                       <div class="fw-semibold"><i class="bi bi-shop me-1"></i>Pay at the gym</div>
                                       <div class="text-muted small">Activate when you arrive</div>
                                    </div>
                                 </label>
                              </div>
                              <div class="col-12 invalid-feedback d-block" v-if="registerErrors.payment_method">{{ registerErrors.payment_method[0] }}</div>
                           </div>

                           <div class="form-check mt-3 mb-3">
                              <input v-model="register.form.terms_accepted" class="form-check-input" :class="{ 'is-invalid': registerErrors.terms_accepted }" type="checkbox" id="terms_accepted" />
                              <label class="form-check-label small" for="terms_accepted">
                                 I agree to the <a href="/terms" target="_blank" class="text-danger">Terms and Conditions</a>
                                 and confirm my membership activates only after payment is confirmed.
                              </label>
                              <div class="invalid-feedback d-block" v-if="registerErrors.terms_accepted">{{ registerErrors.terms_accepted[0] }}</div>
                           </div>

                           <div class="invalid-feedback d-block" v-if="registerErrors.recaptcha">{{ registerErrors.recaptcha[0] }}</div>

                           <div class="d-grid mt-4">
                              <button type="submit" class="btn btn-danger fw-semibold rounded-1 py-2" :disabled="register.submitting">
                                 <span v-if="register.submitting"><i class="bi bi-hourglass-split me-1"></i>Submitting…</span>
                                 <span v-else><i class="bi bi-person-plus-fill me-1"></i>Submit Registration</span>
                              </button>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>

               <!-- Helper cards -->
               <div class="col-lg-5">
                  <div class="card mb-3">
                     <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">What happens next</h5>
                        <div class="step-item">
                           <div class="step-number flex-shrink-0">1</div>
                           <div>
                              <h6 class="fw-bold mb-1">Submit</h6>
                              <p class="text-muted small mb-0">Your registration is recorded immediately.</p>
                           </div>
                        </div>
                        <div class="step-item">
                           <div class="step-number flex-shrink-0">2</div>
                           <div>
                              <h6 class="fw-bold mb-1">Pay</h6>
                              <p class="text-muted small mb-0">Online via PayMongo, or on-site when you arrive.</p>
                           </div>
                        </div>
                        <div class="step-item">
                           <div class="step-number flex-shrink-0">3</div>
                           <div>
                              <h6 class="fw-bold mb-1">Activated</h6>
                              <p class="text-muted small mb-0">Once payment is confirmed, your QR card is emailed.</p>
                           </div>
                        </div>
                     </div>
                  </div>

                  <div class="card">
                     <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">What to bring on first visit</h5>
                        <ul class="list-unstyled mb-0">
                           <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Valid ID for membership records</li>
                           <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Payment for your chosen plan (if not paying online)</li>
                           <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Comfortable training clothes &amp; clean shoes</li>
                           <li class="mb-0"><i class="bi bi-check-circle-fill text-danger me-2"></i>Water bottle &amp; small towel</li>
                        </ul>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- Contact Section -->
      <section class="py-5 bg-light" id="contact">
         <div class="container">
            <div class="section-eyebrow mb-2">Get in touch</div>
            <h2 class="fw-bold mb-1">Contact us</h2>
            <p class="text-muted mb-5">Ask about memberships, promos, coaching, or anything else.</p>
            <div class="row g-4">
               <div class="col-lg-7">
                  <div class="card">
                     <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Send us a message</h5>
                        <form method="POST" action="#" @submit.prevent="onSubmit">
                           <div class="mb-3">
                              <label class="form-label small fw-semibold">Full Name</label>
                              <input v-model="form.name" type="text" class="form-control rounded-1" placeholder="Your name" />
                           </div>
                           <div class="mb-3">
                              <label class="form-label small fw-semibold">Email / Phone / Messenger</label>
                              <input v-model="form.contact" type="text" class="form-control rounded-1" placeholder="How we can reach you" />
                           </div>
                           <div class="mb-3">
                              <label class="form-label small fw-semibold">Inquiry</label>
                              <select v-model="form.topic" class="form-select rounded-1">
                                 <option>Membership</option>
                                 <option>Coaching / PT</option>
                                 <option>Walk-in Visit</option>
                                 <option>General Question</option>
                              </select>
                           </div>
                           <div class="mb-3">
                              <label class="form-label small fw-semibold">Message</label>
                              <textarea v-model="form.message" class="form-control rounded-1" rows="4" placeholder="Type your message..."></textarea>
                           </div>
                           <div class="d-grid">
                              <button type="submit" class="btn btn-danger fw-semibold rounded-1 py-2">Submit</button>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>

               <div class="col-lg-5">
                  <div class="card mb-4">
                     <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Quick links</h5>
                        <div class="d-grid gap-2">
                           <a href="#pricing" class="quick-links-btn"><i class="bi bi-tag-fill text-danger"></i>View Pricing</a>
                           <a href="#features" class="quick-links-btn"><i class="bi bi-stars text-danger"></i>Member Experience</a>
                           <a href="#membership" class="quick-links-btn"><i class="bi bi-person-badge-fill text-danger"></i>Membership Info</a>
                           <a href="#services" class="quick-links-btn"><i class="bi bi-activity text-danger"></i>Programs</a>
                        </div>
                     </div>
                  </div>

                  <div class="card">
                     <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Business hours</h5>
                        <div class="pricing-row">
                           <span class="pricing-label">Mon &ndash; Fri</span>
                           <span class="pricing-value">{{ hoursLong }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label">Saturday</span>
                           <span class="pricing-value">{{ hoursLong }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label">Sunday</span>
                           <span class="pricing-value">{{ hoursLong }}</span>
                        </div>
                        <div class="text-center pt-3 mt-2 text-muted" style="border-top: 1px solid #eee">
                           <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                           <small>{{ addressLine || 'Address coming soon' }}</small>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- Registration Success Modal -->
      <div class="modal fade" id="registerSuccessModal" tabindex="-1" ref="registerSuccessModal" aria-hidden="true">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold">Registration received</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" @click="resetRegister"></button>
               </div>
               <div class="modal-body text-center py-4">
                  <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem"></i>
                  <p class="text-muted mt-3 mb-3">{{ register.success }}</p>
                  <p class="text-muted small mb-0" v-if="addressLine">
                     <i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ addressLine }}
                  </p>
               </div>
               <div class="modal-footer border-0">
                  <button type="button" class="btn btn-outline-secondary rounded-1 fw-semibold" data-bs-dismiss="modal" @click="resetRegister">Submit another</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";

export default {
   props: {
      business: { type: Object, default: () => ({}) },
      ratePlans: { type: Array, default: () => [] },
      ptProducts: { type: Array, default: () => [] },
   },
   data: function () {
      return {
         form: { name: "", contact: "", topic: "Membership", message: "" },
         register: {
            form: this.emptyRegisterForm(),
            submitting: false,
            success: null,
            bannerError: null,
         },
         registerErrors: {},
         registerSuccessModal: null,
         images: {
            about: "https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1200&q=80",
            programs: {
               strength: "https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=80",
               conditioning: "https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?auto=format&fit=crop&w=900&q=80",
               functional: "https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=900&q=80",
            },
            pt: [
               "https://images.unsplash.com/photo-1574680096145-d05b474e2155?auto=format&fit=crop&w=900&q=80",
               "https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?auto=format&fit=crop&w=900&q=80",
               "https://images.unsplash.com/photo-1540497077202-7c8a3999166f?auto=format&fit=crop&w=900&q=80",
               "https://images.unsplash.com/photo-1549060284-1e2e8d0db59a?auto=format&fit=crop&w=900&q=80",
            ],
         },
      };
   },
   computed: {
      addressLine: function () {
         const parts = [this.business.address, this.business.city, this.business.province].filter(Boolean);
         return parts.join(", ");
      },
      amenities: function () {
         return Array.isArray(this.business.amenities) ? this.business.amenities.filter(Boolean) : [];
      },
      hoursLong: function () {
         const o = this.$filters.formatTime(this.business.opening_time);
         const c = this.$filters.formatTime(this.business.closing_time);
         if (o && c) return `${o} – ${c}`;
         return "Hours to be announced";
      },
      hoursShort: function () {
         const o = this.$filters.formatTime(this.business.opening_time, true);
         const c = this.$filters.formatTime(this.business.closing_time, true);
         if (o && c) return `${o}–${c}`;
         return "Open daily";
      },
      openHoursPerDay: function () {
         const open = this.parseClock(this.business.opening_time);
         const close = this.parseClock(this.business.closing_time);
         if (open == null || close == null) return "–";
         const diff = (close - open) / 60;
         return diff > 0 ? Math.round(diff) : "–";
      },
      heroBadge: function () {
         const loc = this.business.city || this.business.province;
         return loc ? `Your neighborhood gym in ${loc}` : "Your neighborhood gym";
      },
      directionsUrl: function () {
         const q = this.addressLine || this.business.name || "JPRIME Fitness";
         return "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(q);
      },
      indexedRatePlans: function () {
         return this.ratePlans.map((p, idx) => ({ ...p, _idx: idx }));
      },
      membershipPlans: function () {
         return this.ratePlans.filter((p) => !p.is_walk_in_only);
      },
      hasDiscount: function () {
         const t = this.register.form.discount_type;
         return t === "student" || t === "senior";
      },
      selectedRegisterPlan: function () {
         const id = this.register.form.rate_plan_id;
         if (!id) return null;
         return this.membershipPlans.find((p) => String(p.id) === String(id)) || null;
      },
      discountSummary: function () {
         if (!this.hasDiscount) return "";
         const label = this.register.form.discount_type === "student" ? "Student" : "Senior citizen";
         const plan = this.selectedRegisterPlan;
         if (!plan) {
            return `${label} discount: 20% off. Payment must be on-site so staff can verify your ID.`;
         }
         const base = parseFloat(plan.price || 0);
         const discounted = Math.round(base * 80) / 100;
         return `${label} discount: ₱${this.$filters.formatMoney(base)} → ₱${this.$filters.formatMoney(discounted)} (20% off). Payment must be on-site so staff can verify your ID.`;
      },
   },
   mounted: function () {
      this.registerSuccessModal = new Modal(this.$refs.registerSuccessModal);
   },
   beforeUnmount: function () {
      this.registerSuccessModal?.dispose();
   },
   methods: {
      emptyRegisterForm: function () {
         return {
            name: "",
            email: "",
            phone: "",
            date_of_birth: "",
            gender: "",
            emergency_contact_name: "",
            emergency_contact_phone: "",
            rate_plan_id: "",
            preferred_start_date: "",
            notes: "",
            payment_method: "online",
            discount_type: "",
            terms_accepted: false,
         };
      },
      onDiscountChange: function () {
         if (this.hasDiscount) {
            this.register.form.payment_method = "on_site";
         }
      },
      perDay: function (plan) {
         const price = parseFloat(plan.price || 0);
         const days = parseInt(plan.duration_days || 0, 10);
         return days > 0 ? price / days : 0;
      },
      perSession: function (pt) {
         const price = parseFloat(pt.price || 0);
         const n = parseInt(pt.session_count || 0, 10);
         return n > 0 ? price / n : 0;
      },
      parseClock: function (value) {
         if (!value) return null;
         const [h, m] = String(value).split(":").map((x) => parseInt(x, 10));
         if (isNaN(h)) return null;
         return h * 60 + (isNaN(m) ? 0 : m);
      },
      ptImage: function (idx) {
         return this.images.pt[idx % this.images.pt.length];
      },
      onSubmit: function () {
         alert("Thanks! We'll get back to you shortly.");
      },
      selectPlanForRegister: function (planId) {
         this.register.form.rate_plan_id = planId;
         this.register.success = null;
         const target = document.getElementById("register");
         if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
      },
      resetRegister: function () {
         this.register.form = this.emptyRegisterForm();
         this.register.success = null;
         this.register.bannerError = null;
         this.registerErrors = {};
         this.registerSuccessModal?.hide();
      },
      getRecaptchaToken: function () {
         const siteKey = window.JPrime && window.JPrime.recaptchaSiteKey;
         if (!siteKey || !window.grecaptcha) return Promise.resolve("");
         return new Promise((resolve) => {
            window.grecaptcha.ready(function () {
               window.grecaptcha
                  .execute(siteKey, { action: "register" })
                  .then((token) => resolve(token || ""))
                  .catch(() => resolve(""));
            });
         });
      },
      submitRegistration: function () {
         this.register.submitting = true;
         this.register.bannerError = null;
         this.registerErrors = {};

         const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

         this.getRecaptchaToken()
            .then((recaptchaToken) => {
               const payload = {
                  ...this.register.form,
                  date_of_birth: this.register.form.date_of_birth || null,
                  gender: this.register.form.gender || null,
                  emergency_contact_name: this.register.form.emergency_contact_name || null,
                  emergency_contact_phone: this.register.form.emergency_contact_phone || null,
                  preferred_start_date: this.register.form.preferred_start_date || null,
                  notes: this.register.form.notes || null,
                  discount_type: this.register.form.discount_type || null,
                  recaptcha_token: recaptchaToken,
               };

               return fetch("/register", {
                  method: "POST",
                  headers: {
                     "Content-Type": "application/json",
                     Accept: "application/json",
                     "X-CSRF-TOKEN": csrf,
                     "X-Requested-With": "XMLHttpRequest",
                  },
                  body: JSON.stringify(payload),
               });
            })
            .then((res) => res.json().catch(() => ({})).then((body) => ({ res, body })))
            .then(({ res, body }) => {
               if (res.status === 422) {
                  this.registerErrors = body.errors || {};
                  this.register.bannerError = body.message || "Please correct the highlighted fields.";
                  return;
               }

               if (!res.ok) {
                  this.register.bannerError = body.message || "Something went wrong. Please try again.";
                  return;
               }

               if (body.payment && body.payment.checkout_url) {
                  window.location.assign(body.payment.checkout_url);
                  return;
               }

               this.register.success = body.message || "We received your registration.";
               this.registerSuccessModal?.show();
            })
            .catch(() => {
               this.register.bannerError = "Network error — please check your connection and try again.";
            })
            .finally(() => {
               this.register.submitting = false;
            });
      },
   },
};
</script>
