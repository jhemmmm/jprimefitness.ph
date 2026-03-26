<template>
    <div class="content">
        <!-- Page Header -->
        <div class="branch-page-header">
            <div class="container">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0" style="font-size: 0.8rem">
                        <li class="breadcrumb-item">
                            <a href="/" class="text-white-50 text-decoration-none">Home</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="/#branches" class="text-white-50 text-decoration-none">Branches</a>
                        </li>
                        <li class="breadcrumb-item active text-white">{{ branchData.cityShort }}</li>
                    </ol>
                </nav>

                <div class="d-flex align-items-center flex-wrap gap-3">
                    <div>
                        <div class="section-eyebrow mb-1" style="color: #888">JPRIME Fitness Network</div>
                        <h1 class="fw-bold text-white mb-0 fs-2">{{ branchData.name }}</h1>
                    </div>
                    <span :class="['branch-status-badge', 'branch-status-' + branchData.status, 'ms-md-auto']" style="position: static">{{ branchData.statusLabel }}</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="py-5 bg-white">
            <div class="container">
                <div class="row g-4 g-xl-5 align-items-start">
                    <!-- LEFT COLUMN -->
                    <div class="col-lg-7">
                        <!-- Branch Photo -->
                        <div class="branch-detail-photo mb-4">
                            <span :class="['branch-status-badge', 'branch-status-' + branchData.status]">{{ branchData.statusLabel }}</span>
                        </div>

                        <!-- Quick Info Chips -->
                        <div class="row g-2 mb-4">
                            <div class="col-sm-4">
                                <div class="branch-info-chip">
                                    <i class="bi bi-clock-fill text-danger fs-5"></i>
                                    <div>
                                        <div class="text-muted" style="font-size: 0.7rem; line-height: 1.2">Hours</div>
                                        <div class="fw-semibold">{{ branchData.hours }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="branch-info-chip">
                                    <i class="bi bi-geo-alt-fill text-danger fs-5"></i>
                                    <div>
                                        <div class="text-muted" style="font-size: 0.7rem; line-height: 1.2">Location</div>
                                        <div class="fw-semibold">{{ branchData.city }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="branch-info-chip">
                                    <i class="bi bi-telephone-fill text-danger fs-5"></i>
                                    <div>
                                        <div class="text-muted" style="font-size: 0.7rem; line-height: 1.2">Contact</div>
                                        <div class="fw-semibold">{{ branchData.phone }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Membership Rates -->
                        <div class="card mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-1">Membership Rates</h5>
                                <p class="text-muted small mb-4">All rates are specific to this branch.</p>
                                <div class="row g-3">
                                    <!-- Walk-in -->
                                    <div class="col-sm-4">
                                        <div class="branch-rate-card">
                                            <div class="branch-rate-label">Walk-in</div>
                                            <div class="branch-rate-price">{{ branchData.rates.daily }}</div>
                                            <div class="branch-rate-period">per day</div>
                                            <ul class="branch-rate-perks">
                                                <li>Full gym access</li>
                                                <li>Shower facility</li>
                                                <li>No lock-in</li>
                                            </ul>
                                            <a href="#contact" class="btn btn-outline-danger btn-sm w-100 rounded-1 mt-auto">Choose</a>
                                        </div>
                                    </div>

                                    <!-- Monthly -->
                                    <div class="col-sm-4">
                                        <div class="branch-rate-card branch-rate-card-featured">
                                            <span class="badge bg-danger position-absolute top-0 start-50 translate-middle" style="font-size: 0.65rem">Most Popular</span>
                                            <div class="branch-rate-label mt-2">Monthly</div>
                                            <div class="branch-rate-price">{{ branchData.rates.monthly }}</div>
                                            <div class="branch-rate-period">per month</div>
                                            <ul class="branch-rate-perks">
                                                <li>Full gym access</li>
                                                <li>Shower facility</li>
                                                <li>Locker use</li>
                                                <li>1 free PT consult</li>
                                            </ul>
                                            <a href="#contact" class="btn btn-danger btn-sm w-100 rounded-1 mt-auto">Choose</a>
                                        </div>
                                    </div>

                                    <!-- Personal Training -->
                                    <div class="col-sm-4">
                                        <div class="branch-rate-card">
                                            <div class="branch-rate-label">Personal Training</div>
                                            <div class="branch-rate-price">{{ branchData.rates.pt }}</div>
                                            <div class="branch-rate-period">per session</div>
                                            <ul class="branch-rate-perks">
                                                <li>1-on-1 with coach</li>
                                                <li>Custom program</li>
                                                <li>Progress tracking</li>
                                            </ul>
                                            <a href="#contact" class="btn btn-outline-danger btn-sm w-100 rounded-1 mt-auto">Book a Session</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Facilities & Equipment -->
                        <div class="card mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Facilities &amp; Equipment</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <span v-for="amenity in branchData.amenities" :key="amenity" class="branch-amenity-badge"> <i class="bi bi-check-circle-fill text-success"></i>{{ amenity }} </span>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Training Info -->
                        <div class="card mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="program-icon flex-shrink-0">
                                        <i class="bi bi-person-badge-fill"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1">Personal Training Available</h5>
                                        <p class="text-muted small mb-3">This branch has certified personal trainers available. Sessions are booked separately from your membership.</p>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div>
                                                <div class="text-muted" style="font-size: 0.72rem">Rate</div>
                                                <div class="fw-bold text-danger">{{ branchData.rates.pt }} / session</div>
                                            </div>
                                            <div>
                                                <div class="text-muted" style="font-size: 0.72rem">Session Length</div>
                                                <div class="fw-bold">60 minutes</div>
                                            </div>
                                            <div>
                                                <div class="text-muted" style="font-size: 0.72rem">Availability</div>
                                                <div class="fw-bold">By appointment</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Promotion -->
                        <div v-if="branchData.promo" class="branch-promo-card mb-4">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.72rem; letter-spacing: 1px; opacity: 0.7; text-transform: uppercase">Active Promo</div>
                                    <h5 class="fw-bold text-white mb-1">{{ branchData.promo.title }}</h5>
                                    <p class="mb-0 small" style="opacity: 0.85">{{ branchData.promo.desc }}</p>
                                </div>
                                <a href="#contact" class="btn btn-light rounded-1 fw-semibold btn-sm flex-shrink-0 align-self-center">Claim Promo</a>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div id="contact" class="d-flex flex-wrap gap-3">
                            <a :href="branchData.messenger" target="_blank" rel="noopener noreferrer" class="btn btn-danger px-4 py-2 fw-semibold rounded-1"> <i class="bi bi-messenger me-2"></i>Message on Messenger </a>
                            <a :href="branchData.whatsapp" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success px-4 py-2 fw-semibold rounded-1"> <i class="bi bi-whatsapp me-2"></i>WhatsApp </a>
                            <a href="/#branches" class="btn btn-outline-secondary px-4 py-2 fw-semibold rounded-1"> <i class="bi bi-arrow-left me-2"></i>Back to Branches </a>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN - sticky map + hours -->
                    <div class="col-lg-5">
                        <div class="sticky-top" style="top: 82px">
                            <!-- Map -->
                            <div class="branch-map-wrapper mb-3">
                                <iframe :src="branchData.mapSrc" :title="branchData.name + ' Map'" allowfullscreen loading="lazy"></iframe>
                            </div>

                            <!-- Address + Hours card -->
                            <div class="card">
                                <div class="card-body p-4">
                                    <div class="d-flex gap-3 mb-4">
                                        <i class="bi bi-geo-alt-fill text-danger fs-5 flex-shrink-0 mt-1"></i>
                                        <div>
                                            <div class="fw-semibold mb-1">Address</div>
                                            <p class="text-muted small mb-0">
                                                <template v-for="(line, i) in branchData.addressLines" :key="i"> {{ line }}<br v-if="i < branchData.addressLines.length - 1" /> </template>
                                            </p>
                                        </div>
                                    </div>

                                    <a :href="branchData.mapsLink" target="_blank" rel="noopener noreferrer" class="btn btn-danger w-100 rounded-1 fw-semibold mb-4"> <i class="bi bi-map-fill me-2"></i>Get Directions </a>

                                    <h6 class="fw-bold mb-3">Operating Hours</h6>
                                    <div v-for="(row, i) in branchData.schedule" :key="row.day" :class="['branch-hours-row', { today: i === todayIndex }]">
                                        <span>{{ row.day }}</span>
                                        <span>{{ row.hours }}</span>
                                    </div>

                                    <div class="mt-4 pt-3 border-top d-grid gap-2">
                                        <a :href="branchData.messenger" target="_blank" rel="noopener noreferrer" class="quick-links-btn"> <i class="bi bi-messenger"></i>Message us on Messenger </a>
                                        <a :href="branchData.whatsapp" target="_blank" rel="noopener noreferrer" class="quick-links-btn" style="color: #22c55e"> <i class="bi bi-whatsapp"></i>Chat on WhatsApp </a>
                                    </div>
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
        // Full branch model with ratePlans & ptProducts, JSON-serialised by Laravel
        branch: {
            type: Object,
            required: true,
        },
    },

    computed: {
        branchData() {
            const b = this.branch;

            const STATUS_LABELS = { open: "Open", soon: "Opening Soon", closed: "Closed" };

            // Look up a rate plan price by slug from the branch_rate_plans pivot
            const findRate = (slug) => {
                const rp = (b.rate_plans ?? []).find((r) => r.slug === slug);
                return rp ? "₱" + Number(rp.pivot.price).toLocaleString() : "—";
            };

            // Look up PT product price by slug from the branch_pt_prices pivot
            const findPt = (slug) => {
                const pt = (b.pt_products ?? []).find((p) => p.slug === slug);
                return pt ? "₱" + Number(pt.pivot.price).toLocaleString() : "—";
            };

            // Derive a single hours string from operating_hours for the quick chip
            const firstDay = (b.operating_hours ?? [])[0];
            const hours = firstDay?.hours ?? "N/A";

            // Derive Google Maps embed URL from the directions URL
            const mapSrc = b.map_url ? b.map_url.replace("https://www.google.com/maps/search/?api=1&query=", "https://maps.google.com/maps?q=") + "&output=embed" : null;

            return {
                name: b.name,
                status: b.status,
                statusLabel: STATUS_LABELS[b.status] ?? b.status,
                cityShort: b.city,
                city: b.city + (b.province ? ", " + b.province : ""),
                phone: b.phone ?? "N/A",
                hours,
                addressLines: b.address ? b.address.split(", ").map((p, i, a) => (i === a.length - 1 ? p : p + ",")) : [],
                messenger: b.messenger_url,
                whatsapp: b.whatsapp_url,
                mapSrc,
                mapsLink: b.map_url,
                rates: {
                    daily: findRate("daily-pass"),
                    monthly: findRate("monthly"),
                    pt: findPt("per-session"),
                },
                amenities: b.amenities ?? [],
                promo: null, // removed from schema
                schedule: b.operating_hours ?? [],
            };
        },

        // Index of today in the schedule array: 0=Mon … 6=Sun
        todayIndex() {
            const day = new Date().getDay(); // 0=Sun, 1=Mon, …, 6=Sat
            return day === 0 ? 6 : day - 1;
        },
    },
};
</script>
