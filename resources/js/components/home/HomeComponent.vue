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
                           <a href="#membership" class="btn btn-danger rounded-1 fw-semibold">Become a Member</a>
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
                        <div class="plan-meta-value">&#8369;{{ formatMoney(perDay(plan)) }}</div>
                     </div>
                  </div>

                  <div class="plan-row-price">
                     <div class="plan-price-amount">&#8369;{{ formatMoney(plan.price) }}</div>
                     <a href="#contact" class="btn btn-danger rounded-1 fw-semibold btn-sm py-2 px-3 mt-2">Become a member</a>
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
                           <span class="pricing-value">&#8369;{{ formatMoney(pt.price) }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label">Sessions</span>
                           <span class="pricing-value">{{ pt.session_count }}</span>
                        </div>
                        <div class="pricing-row">
                           <span class="pricing-label">Per session</span>
                           <span class="pricing-value">&#8369;{{ formatMoney(perSession(pt)) }}</span>
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
                        <h6 class="fw-bold mb-1">Drop by</h6>
                        <p class="text-muted small mb-0">Visit the gym or message us with any questions.</p>
                     </div>
                  </div>
                  <div class="step-item">
                     <div class="step-number flex-shrink-0">2</div>
                     <div>
                        <h6 class="fw-bold mb-1">Choose your plan</h6>
                        <p class="text-muted small mb-0">Walk-in, monthly, or bundled session packs.</p>
                     </div>
                  </div>
                  <div class="step-item">
                     <div class="step-number flex-shrink-0">3</div>
                     <div>
                        <h6 class="fw-bold mb-1">Get enrolled</h6>
                        <p class="text-muted small mb-0">We register you on the spot - your QR membership card is ready immediately.</p>
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
   </div>
</template>

<script>
export default {
   props: {
      business: { type: Object, default: () => ({}) },
      ratePlans: { type: Array, default: () => [] },
      ptProducts: { type: Array, default: () => [] },
   },
   data() {
      return {
         form: { name: "", contact: "", topic: "Membership", message: "" },
         // Swap these for /images/home/*.jpg once local photos are added.
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
      addressLine() {
         const parts = [this.business.address, this.business.city, this.business.province].filter(Boolean);
         return parts.join(", ");
      },
      amenities() {
         return Array.isArray(this.business.amenities) ? this.business.amenities.filter(Boolean) : [];
      },
      hoursLong() {
         const o = this.formatTime(this.business.opening_time);
         const c = this.formatTime(this.business.closing_time);
         if (o && c) return `${o} – ${c}`;
         return "Hours to be announced";
      },
      hoursShort() {
         const o = this.formatTime(this.business.opening_time, true);
         const c = this.formatTime(this.business.closing_time, true);
         if (o && c) return `${o}–${c}`;
         return "Open daily";
      },
      openHoursPerDay() {
         const open = this.parseClock(this.business.opening_time);
         const close = this.parseClock(this.business.closing_time);
         if (open == null || close == null) return "–";
         const diff = (close - open) / 60;
         return diff > 0 ? Math.round(diff) : "–";
      },
      heroBadge() {
         const loc = this.business.city || this.business.province;
         return loc ? `Your neighborhood gym in ${loc}` : "Your neighborhood gym";
      },
      directionsUrl() {
         const q = this.addressLine || this.business.name || "JPRIME Fitness";
         return "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(q);
      },
      indexedRatePlans() {
         return this.ratePlans.map((p, idx) => ({ ...p, _idx: idx }));
      },
   },
   methods: {
      formatMoney(value) {
         if (value === null || value === undefined || value === "") return "0";
         return parseFloat(value).toLocaleString("en-PH", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
      },
      perDay(plan) {
         const price = parseFloat(plan.price || 0);
         const days = parseInt(plan.duration_days || 0, 10);
         return days > 0 ? price / days : 0;
      },
      perSession(pt) {
         const price = parseFloat(pt.price || 0);
         const n = parseInt(pt.session_count || 0, 10);
         return n > 0 ? price / n : 0;
      },
      formatTime(value, short = false) {
         if (!value) return "";
         const [hStr, mStr] = String(value).split(":");
         let h = parseInt(hStr, 10);
         const m = parseInt(mStr || "0", 10);
         if (isNaN(h)) return "";
         const am = h < 12;
         const display = h % 12 === 0 ? 12 : h % 12;
         if (short) return `${display}${am ? "am" : "pm"}`;
         const mm = m.toString().padStart(2, "0");
         return `${display}:${mm} ${am ? "AM" : "PM"}`;
      },
      parseClock(value) {
         if (!value) return null;
         const [h, m] = String(value).split(":").map((x) => parseInt(x, 10));
         if (isNaN(h)) return null;
         return h * 60 + (isNaN(m) ? 0 : m);
      },
      ptImage(idx) {
         const list = this.images.pt;
         return list[idx % list.length];
      },
      onSubmit() {
         alert("Thanks! We'll get back to you shortly.");
      },
   },
};
</script>
