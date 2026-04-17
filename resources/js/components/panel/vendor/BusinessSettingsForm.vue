<template>
   <div>
      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Business settings saved successfully.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-4 business-settings-form-grid">
         <div class="col-xl-8">
            <div class="business-settings-stack">
               <section id="business-identity" class="panel-card business-settings-section">
                  <div class="panel-card-header align-items-start flex-column flex-md-row">
                     <div>
                        <div class="panel-card-title">Business identity</div>
                        <div class="panel-card-sub">Set the core profile details staff and members rely on every day.</div>
                     </div>
                     <span class="business-settings-section-index">01</span>
                  </div>
                  <div class="panel-card-body">
                     <div class="row g-3">
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Business Name <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" v-model="form.name" :disabled="identityFieldsLocked" />
                           <div class="invalid-feedback" v-if="errors.name">{{ errors.name[0] }}</div>
                        </div>
                        <div class="col-md-3">
                           <label class="form-label form-label-sm fw-semibold">Status <span class="text-danger">*</span></label>
                           <select class="form-select" :class="{ 'is-invalid': errors.status }" v-model="form.status" :disabled="identityFieldsLocked">
                              <option value="open">Open</option>
                              <option value="closed">Closed</option>
                              <option value="coming_soon">Coming Soon</option>
                           </select>
                           <div class="invalid-feedback" v-if="errors.status">{{ errors.status[0] }}</div>
                        </div>
                        <div class="col-md-3">
                           <label class="form-label form-label-sm fw-semibold">Country Code <span class="text-danger">*</span></label>
                           <select class="form-select" :class="{ 'is-invalid': errors.country_code }" v-model="form.country_code" :disabled="identityFieldsLocked">
                              <option v-for="country in countryOptions" :key="country.value" :value="country.value">{{ country.label }}</option>
                           </select>
                           <div class="invalid-feedback" v-if="errors.country_code">{{ errors.country_code[0] }}</div>
                        </div>
                        <div class="col-12">
                           <div class="business-settings-toggle-card">
                              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                 <div>
                                    <div class="fw-semibold">Pay overwork hours</div>
                                    <div class="text-muted small">
                                       When enabled, payroll previews and saved payrolls will pay attendance hours above 8 hours per day at the normal hourly rate.
                                    </div>
                                 </div>
                                 <div class="form-check form-switch m-0">
                                    <input id="payOverworkHours" class="form-check-input" type="checkbox" v-model="form.pay_overwork_hours" />
                                    <label class="form-check-label visually-hidden" for="payOverworkHours">Pay overwork hours</label>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </section>

               <section id="business-location" class="panel-card business-settings-section">
                  <div class="panel-card-header align-items-start flex-column flex-md-row">
                     <div>
                        <div class="panel-card-title">Location & hours</div>
                        <div class="panel-card-sub">Organize how the branch appears on maps, schedules, and public pages.</div>
                     </div>
                     <span class="business-settings-section-index">02</span>
                  </div>
                  <div class="panel-card-body">
                     <div class="row g-3">
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">City <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.city }" v-model="form.city" />
                           <div class="invalid-feedback" v-if="errors.city">{{ errors.city[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Province</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.province }" v-model="form.province" />
                           <div class="invalid-feedback" v-if="errors.province">{{ errors.province[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Full Address</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.address }" v-model="form.address" />
                           <div class="invalid-feedback" v-if="errors.address">{{ errors.address[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Opening Time</label>
                           <input type="time" class="form-control" :class="{ 'is-invalid': errors.opening_time }" v-model="form.opening_time" />
                           <div class="invalid-feedback" v-if="errors.opening_time">{{ errors.opening_time[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Closing Time</label>
                           <input type="time" class="form-control" :class="{ 'is-invalid': errors.closing_time }" v-model="form.closing_time" />
                           <div class="invalid-feedback" v-if="errors.closing_time">{{ errors.closing_time[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Timezone</label>
                           <select class="form-select" :class="{ 'is-invalid': errors.timezone }" v-model="form.timezone">
                              <option v-for="timezone in timezoneOptions" :key="timezone.value" :value="timezone.value">{{ timezone.label }}</option>
                           </select>
                           <div class="invalid-feedback" v-if="errors.timezone">{{ errors.timezone[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Google Maps URL</label>
                           <input type="url" class="form-control" :class="{ 'is-invalid': errors.map_url }" v-model="form.map_url" />
                           <div class="form-text">Paste the public map link members can use for directions.</div>
                           <div class="invalid-feedback" v-if="errors.map_url">{{ errors.map_url[0] }}</div>
                        </div>
                     </div>
                  </div>
               </section>

               <section id="business-contact" class="panel-card business-settings-section">
                  <div class="panel-card-header align-items-start flex-column flex-md-row">
                     <div>
                        <div class="panel-card-title">Contact & social</div>
                        <div class="panel-card-sub">Keep every direct channel in one place for quick updates.</div>
                     </div>
                     <span class="business-settings-section-index">03</span>
                  </div>
                  <div class="panel-card-body">
                     <div class="row g-3">
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Phone</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="form.phone" />
                           <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Email</label>
                           <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" v-model="form.email" />
                           <div class="invalid-feedback" v-if="errors.email">{{ errors.email[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Facebook URL</label>
                           <input type="url" class="form-control" :class="{ 'is-invalid': errors.facebook_url }" v-model="form.facebook_url" />
                           <div class="invalid-feedback" v-if="errors.facebook_url">{{ errors.facebook_url[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Messenger URL</label>
                           <input type="url" class="form-control" :class="{ 'is-invalid': errors.messenger_url }" v-model="form.messenger_url" />
                           <div class="invalid-feedback" v-if="errors.messenger_url">{{ errors.messenger_url[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">WhatsApp URL</label>
                           <input type="url" class="form-control" :class="{ 'is-invalid': errors.whatsapp_url }" v-model="form.whatsapp_url" />
                           <div class="invalid-feedback" v-if="errors.whatsapp_url">{{ errors.whatsapp_url[0] }}</div>
                        </div>
                     </div>
                  </div>
               </section>

               <section id="business-media" class="panel-card business-settings-section">
                  <div class="panel-card-header align-items-start flex-column flex-md-row">
                     <div>
                        <div class="panel-card-title">Amenities & media</div>
                        <div class="panel-card-sub">Show what the facility offers and keep gallery content easy to manage.</div>
                     </div>
                     <span class="business-settings-section-index">04</span>
                  </div>
                  <div class="panel-card-body">
                     <div class="row g-3">
                        <div class="col-lg-7">
                           <label class="form-label form-label-sm fw-semibold">Amenities</label>
                           <textarea
                              class="form-control"
                              rows="5"
                              v-model="form.amenities_text"
                              :class="{ 'is-invalid': errors.amenities }"
                              placeholder="Separate amenities with commas or line breaks"
                           ></textarea>
                           <div class="form-text">Examples: lockers, shower rooms, vending area, cardio zone, recovery space.</div>
                           <div class="invalid-feedback" v-if="errors.amenities">{{ errors.amenities[0] }}</div>
                        </div>
                        <div class="col-lg-5">
                           <div class="business-settings-media-card h-100">
                              <div class="small text-uppercase text-muted fw-semibold mb-2">Photo gallery</div>
                              <div class="business-settings-media-count">{{ galleryPhotoCount }}</div>
                              <div class="text-muted small mb-3">
                                 {{ galleryPhotoCount === 1 ? "photo is currently available for the public gallery." : "photos are currently available for the public gallery." }}
                              </div>
                              <a href="/panel/business/photos" class="btn btn-outline-secondary btn-sm business-settings-media-link">
                                 <i class="bi bi-images me-1"></i>
                                 Manage Photos
                              </a>
                           </div>
                        </div>
                        <div class="col-12" v-if="parsedAmenities.length">
                           <div class="small text-muted mb-2">Preview</div>
                           <div class="d-flex flex-wrap gap-2">
                              <span class="m-badge m-badge--plan" v-for="amenity in parsedAmenities" :key="amenity">{{ amenity }}</span>
                           </div>
                        </div>
                     </div>
                  </div>
               </section>

               <section id="business-copy" class="panel-card business-settings-section">
                  <div class="panel-card-header align-items-start flex-column flex-md-row">
                     <div>
                        <div class="panel-card-title">Website copy</div>
                        <div class="panel-card-sub">Write the homepage and about-section copy without hunting across the form.</div>
                     </div>
                     <span class="business-settings-section-index">05</span>
                  </div>
                  <div class="panel-card-body">
                     <div class="row g-3">
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Hero Badge</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.hero_badge }" v-model="form.hero_badge" />
                           <div class="invalid-feedback" v-if="errors.hero_badge">{{ errors.hero_badge[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Hero Title</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.hero_title }" v-model="form.hero_title" />
                           <div class="invalid-feedback" v-if="errors.hero_title">{{ errors.hero_title[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Hero Highlight</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.hero_highlight }" v-model="form.hero_highlight" />
                           <div class="invalid-feedback" v-if="errors.hero_highlight">{{ errors.hero_highlight[0] }}</div>
                        </div>
                        <div class="col-12">
                           <label class="form-label form-label-sm fw-semibold">Hero Description</label>
                           <textarea class="form-control" rows="3" :class="{ 'is-invalid': errors.hero_description }" v-model="form.hero_description"></textarea>
                           <div class="invalid-feedback" v-if="errors.hero_description">{{ errors.hero_description[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">About Heading</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.about_heading }" v-model="form.about_heading" />
                           <div class="invalid-feedback" v-if="errors.about_heading">{{ errors.about_heading[0] }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="form-label form-label-sm fw-semibold">Membership Note</label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.membership_note }" v-model="form.membership_note" />
                           <div class="invalid-feedback" v-if="errors.membership_note">{{ errors.membership_note[0] }}</div>
                        </div>
                        <div class="col-12">
                           <label class="form-label form-label-sm fw-semibold">About Description</label>
                           <textarea class="form-control" rows="4" :class="{ 'is-invalid': errors.about_description }" v-model="form.about_description"></textarea>
                           <div class="invalid-feedback" v-if="errors.about_description">{{ errors.about_description[0] }}</div>
                        </div>
                     </div>
                  </div>
               </section>

               <div class="d-flex justify-content-end d-xl-none">
                  <button type="button" class="btn btn-danger px-4" @click="save" :disabled="saving || !hasPendingChanges">
                     <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                     Save Settings
                  </button>
               </div>
            </div>
         </div>

         <div class="col-xl-4">
            <div class="business-settings-sidebar-sticky">
               <div class="panel-card business-settings-summary-card">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Editing overview</div>
                        <div class="panel-card-sub">Track what matters while moving through the sections.</div>
                     </div>
                  </div>
                  <div class="panel-card-body">
                     <div class="business-settings-summary-status">
                        <span :class="['m-badge', statusBadgeClass]">{{ statusLabel }}</span>
                        <span class="small text-muted">{{ hasPendingChanges ? "Unsaved changes ready to publish." : "Everything matches the saved profile." }}</span>
                     </div>

                     <div class="business-settings-metrics">
                        <div class="business-settings-metric">
                           <div class="business-settings-metric-label">Hours</div>
                           <div class="business-settings-metric-value">{{ businessHoursLabel }}</div>
                        </div>
                        <div class="business-settings-metric">
                           <div class="business-settings-metric-label">Timezone</div>
                           <div class="business-settings-metric-value">{{ form.timezone || "-" }}</div>
                        </div>
                        <div class="business-settings-metric">
                           <div class="business-settings-metric-label">Social links</div>
                           <div class="business-settings-metric-value">{{ socialLinkCount }}</div>
                        </div>
                        <div class="business-settings-metric">
                           <div class="business-settings-metric-label">Amenities</div>
                           <div class="business-settings-metric-value">{{ parsedAmenities.length }}</div>
                        </div>
                        <div class="business-settings-metric">
                           <div class="business-settings-metric-label">Gallery</div>
                           <div class="business-settings-metric-value">{{ galleryPhotoCount }}</div>
                        </div>
                        <div class="business-settings-metric">
                           <div class="business-settings-metric-label">Website copy</div>
                           <div class="business-settings-metric-value">{{ websiteCopyStatus }}</div>
                        </div>
                     </div>

                     <button type="button" class="btn btn-danger w-100 mt-3 d-none d-xl-inline-flex justify-content-center align-items-center" @click="save" :disabled="saving || !hasPendingChanges">
                        <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                        Save Settings
                     </button>

                     <div class="form-text mt-2 text-center" v-if="!saving && !hasPendingChanges">No pending changes.</div>
                     <div class="form-text mt-2" v-if="identityFieldsLocked">Business name, status, and country code can only be changed by a super admin.</div>
                  </div>
               </div>

               <div class="panel-card business-settings-shortcuts-card mt-3">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Jump to a section</div>
                        <div class="panel-card-sub">Use these shortcuts if you only need to update one part of the profile.</div>
                     </div>
                  </div>
                  <div class="panel-card-body d-grid gap-2">
                     <button type="button" class="business-settings-shortcut" v-for="section in sectionLinks" :key="section.id" @click="scrollToSection(section.id)">
                        <span class="business-settings-shortcut-title">{{ section.label }}</span>
                        <span class="business-settings-shortcut-note">{{ section.note }}</span>
                     </button>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
const regionNames = typeof Intl !== "undefined" && typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "region" }) : null;

const COUNTRY_CODES = [
   "PH",
   "US",
   "CA",
   "AU",
   "NZ",
   "GB",
   "IE",
   "SG",
   "MY",
   "ID",
   "TH",
   "VN",
   "JP",
   "KR",
   "HK",
   "TW",
   "CN",
   "IN",
   "AE",
   "SA",
   "QA",
   "KW",
   "BH",
   "OM",
   "DE",
   "FR",
   "ES",
   "IT",
   "NL",
   "CH",
   "SE",
   "NO",
   "DK",
   "ZA",
   "BR",
   "MX",
];

const FALLBACK_TIMEZONES = [
   "UTC",
   "Asia/Manila",
   "Asia/Singapore",
   "Asia/Hong_Kong",
   "Asia/Tokyo",
   "Asia/Seoul",
   "Asia/Bangkok",
   "Asia/Kuala_Lumpur",
   "Asia/Jakarta",
   "Asia/Dubai",
   "Asia/Riyadh",
   "Europe/London",
   "Europe/Paris",
   "Europe/Berlin",
   "America/New_York",
   "America/Chicago",
   "America/Los_Angeles",
   "Australia/Sydney",
];

const supportedTimezones = typeof Intl !== "undefined" && typeof Intl.supportedValuesOf === "function" ? Intl.supportedValuesOf("timeZone") : [];

const COUNTRY_OPTIONS = COUNTRY_CODES.map((code) => ({
   value: code,
   label: `${regionNames?.of(code) || code} (${code})`,
}));

const TIMEZONE_OPTIONS = (supportedTimezones.length ? supportedTimezones : FALLBACK_TIMEZONES)
   .map((timezone) => ({
      value: timezone,
      label: timezone,
   }))
   .sort((left, right) => left.label.localeCompare(right.label));

const SECTION_LINKS = [
   {
      id: "business-identity",
      label: "Business identity",
      note: "Name, status, country, and payroll behavior.",
   },
   {
      id: "business-location",
      label: "Location & hours",
      note: "Address, maps, timezone, and operating schedule.",
   },
   {
      id: "business-contact",
      label: "Contact & social",
      note: "Direct contact details and messaging links.",
   },
   {
      id: "business-media",
      label: "Amenities & media",
      note: "Facility amenities plus gallery management.",
   },
   {
      id: "business-copy",
      label: "Website copy",
      note: "Homepage messaging and about-section content.",
   },
];

function ensureSelectOption(options, value, formatter = null) {
   if (!value) {
      return options;
   }

   if (options.some((option) => option.value === value)) {
      return options;
   }

   return [
      {
         value,
         label: formatter ? formatter(value) : value,
      },
      ...options,
   ];
}

export default {
   props: {
      profile: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.profile),
      };
   },

   watch: {
      profile: function (value) {
         this.form = this.getForm(value);
      },
   },

   computed: {
      sectionLinks: function () {
         return SECTION_LINKS;
      },
      countryOptions: function () {
         return ensureSelectOption(COUNTRY_OPTIONS, (this.form.country_code || "").toUpperCase(), (value) => value);
      },
      timezoneOptions: function () {
         return ensureSelectOption(TIMEZONE_OPTIONS, this.form.timezone, (value) => value);
      },
      parsedAmenities: function () {
         return this.form.amenities_text
            .split(/[\n,]+/)
            .map((value) => value.trim())
            .filter(Boolean);
      },
      identityFieldsLocked: function () {
         return !this.is("super admin");
      },
      statusLabel: function () {
         return this.$filters.capitalize(this.form.status || "open");
      },
      statusBadgeClass: function () {
         return this.$filters.statusBadge(this.form.status) || "m-badge--draft";
      },
      galleryPhotoCount: function () {
         return Array.isArray(this.profile.photos) ? this.profile.photos.length : 0;
      },
      socialLinkCount: function () {
         return [this.form.facebook_url, this.form.messenger_url, this.form.whatsapp_url, this.form.map_url].filter(Boolean).length;
      },
      businessHoursLabel: function () {
         if (!this.form.opening_time && !this.form.closing_time) {
            return "Not set";
         }

         return [this.formatShortTime(this.form.opening_time), this.formatShortTime(this.form.closing_time)].filter(Boolean).join(" - ");
      },
      websiteCopyStatus: function () {
         const completedFields = [
            this.form.hero_badge,
            this.form.hero_title,
            this.form.hero_highlight,
            this.form.hero_description,
            this.form.about_heading,
            this.form.about_description,
            this.form.membership_note,
         ].filter((value) => String(value || "").trim()).length;

         if (completedFields >= 5) {
            return "Ready";
         }

         if (completedFields > 0) {
            return `${completedFields}/7 set`;
         }

         return "Needs copy";
      },
      hasPendingChanges: function () {
         return JSON.stringify(this.buildPayload()) !== JSON.stringify(this.normalizePayload(this.profile));
      },
   },

   methods: {
      getForm: function (profile) {
         return {
            name: profile.name || "",
            status: profile.status || "open",
            country_code: profile.country_code || "PH",
            pay_overwork_hours: Boolean(profile.pay_overwork_hours),
            city: profile.city || "",
            province: profile.province || "",
            address: profile.address || "",
            phone: profile.phone || "",
            email: profile.email || "",
            map_url: profile.map_url || "",
            opening_time: profile.opening_time ? String(profile.opening_time).slice(0, 5) : "",
            closing_time: profile.closing_time ? String(profile.closing_time).slice(0, 5) : "",
            facebook_url: profile.facebook_url || "",
            messenger_url: profile.messenger_url || "",
            whatsapp_url: profile.whatsapp_url || "",
            timezone: profile.timezone || "Asia/Manila",
            amenities_text: Array.isArray(profile.amenities) ? profile.amenities.join(", ") : "",
            hero_badge: profile.hero_badge || "",
            hero_title: profile.hero_title || "",
            hero_highlight: profile.hero_highlight || "",
            hero_description: profile.hero_description || "",
            about_heading: profile.about_heading || "",
            about_description: profile.about_description || "",
            membership_note: profile.membership_note || "",
         };
      },

      normalizePayload: function (source, overrides = {}) {
         return {
            name: source.name || "",
            status: source.status || "open",
            country_code: String(source.country_code || "PH").toUpperCase(),
            pay_overwork_hours: Boolean(source.pay_overwork_hours),
            city: source.city || "",
            province: source.province || "",
            address: source.address || "",
            phone: source.phone || "",
            email: source.email || "",
            timezone: source.timezone || "Asia/Manila",
            amenities: Array.isArray(source.amenities) ? source.amenities.filter(Boolean) : [],
            opening_time: source.opening_time ? String(source.opening_time).slice(0, 5) : "",
            closing_time: source.closing_time ? String(source.closing_time).slice(0, 5) : "",
            facebook_url: source.facebook_url || "",
            messenger_url: source.messenger_url || "",
            whatsapp_url: source.whatsapp_url || "",
            map_url: source.map_url || "",
            hero_badge: source.hero_badge || null,
            hero_title: source.hero_title || null,
            hero_highlight: source.hero_highlight || null,
            hero_description: source.hero_description || null,
            about_heading: source.about_heading || null,
            about_description: source.about_description || null,
            membership_note: source.membership_note || null,
            ...overrides,
         };
      },

      buildPayload: function (overrides = {}) {
         return this.normalizePayload(
            {
               ...this.form,
               amenities: this.parsedAmenities,
            },
            overrides
         );
      },

      formatShortTime: function (value) {
         if (!value) {
            return "";
         }

         const [rawHour = "0", minute = "00"] = String(value).split(":");
         let hour = parseInt(rawHour, 10);

         if (Number.isNaN(hour)) {
            return "";
         }

         const suffix = hour >= 12 ? "PM" : "AM";

         hour = hour % 12 || 12;

         return `${hour}:${minute} ${suffix}`;
      },

      scrollToSection: function (sectionId) {
         const section = document.getElementById(sectionId);

         if (!section) {
            return;
         }

         section.scrollIntoView({
            behavior: "smooth",
            block: "start",
         });
      },

      save: function () {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};

         axios
            .put("/panel/business/settings", this.buildPayload())
            .then((response) => {
               this.saved = true;
               this.$emit("updated", response.data);
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  this.errors = error.response.data.errors || {};
               } else {
                  this.generalError = error.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => {
               this.saving = false;
            });
      },
   },
};
</script>
