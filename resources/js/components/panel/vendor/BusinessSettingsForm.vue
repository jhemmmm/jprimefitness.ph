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
                        <div class="panel-card-sub">Set the business name, country, and payroll behavior.</div>
                     </div>
                     <span class="business-settings-section-index">01</span>
                  </div>
                  <div class="panel-card-body">
                     <div class="row g-3">
                        <div class="col-md-8">
                           <label class="form-label form-label-sm fw-semibold">Business Name <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" v-model="form.name" :disabled="identityFieldsLocked" />
                           <div class="invalid-feedback" v-if="errors.name">{{ errors.name[0] }}</div>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-12">
                           <div class="business-settings-toggle-card">
                              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                 <div>
                                    <div class="fw-semibold">Withholding tax</div>
                                    <div class="text-muted small">
                                       When enabled, new payrolls and recalculated drafts will apply the app's calculated withholding tax.
                                    </div>
                                 </div>
                                 <div class="form-check form-switch m-0">
                                    <input id="payrollWithholdingTaxEnabled" class="form-check-input" type="checkbox" v-model="form.payroll_withholding_tax_enabled" />
                                    <label class="form-check-label visually-hidden" for="payrollWithholdingTaxEnabled">Withholding tax</label>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="col-12">
                           <div class="business-settings-toggle-card">
                              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                 <div>
                                    <div class="fw-semibold">Government contributions</div>
                                    <div class="text-muted small">
                                       When enabled, new payrolls and recalculated drafts will snapshot SSS, PhilHealth, and Pag-IBIG deductions and employer shares.
                                    </div>
                                 </div>
                                 <div class="form-check form-switch m-0">
                                    <input
                                       id="payrollGovernmentContributionsEnabled"
                                       class="form-check-input"
                                       type="checkbox"
                                       v-model="form.payroll_government_contributions_enabled"
                                    />
                                    <label class="form-check-label visually-hidden" for="payrollGovernmentContributionsEnabled">Government contributions</label>
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
                        <div class="panel-card-sub">Maintain the operating location and schedule used across the panel.</div>
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
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Opening Time</label>
                           <input type="time" class="form-control" :class="{ 'is-invalid': errors.opening_time }" v-model="form.opening_time" />
                           <div class="invalid-feedback" v-if="errors.opening_time">{{ errors.opening_time[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Closing Time</label>
                           <input type="time" class="form-control" :class="{ 'is-invalid': errors.closing_time }" v-model="form.closing_time" />
                           <div class="invalid-feedback" v-if="errors.closing_time">{{ errors.closing_time[0] }}</div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label form-label-sm fw-semibold">Timezone</label>
                           <select class="form-select" :class="{ 'is-invalid': errors.timezone }" v-model="form.timezone">
                              <option v-for="timezone in timezoneOptions" :key="timezone.value" :value="timezone.value">{{ timezone.label }}</option>
                           </select>
                           <div class="invalid-feedback" v-if="errors.timezone">{{ errors.timezone[0] }}</div>
                        </div>
                        <div class="col-12">
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
                        <div class="col-12" v-if="parsedAmenities.length">
                           <div class="small text-muted mb-2">Preview</div>
                           <div class="d-flex flex-wrap gap-2">
                              <span class="m-badge m-badge--plan" v-for="amenity in parsedAmenities" :key="amenity">{{ amenity }}</span>
                           </div>
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
                        <span class="m-badge m-badge--open">Active</span>
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
                           <div class="business-settings-metric-label">Amenities</div>
                           <div class="business-settings-metric-value">{{ parsedAmenities.length }}</div>
                        </div>
                     </div>

                     <button type="button" class="btn btn-danger w-100 mt-3 d-none d-xl-inline-flex justify-content-center align-items-center" @click="save" :disabled="saving || !hasPendingChanges">
                        <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                        Save Settings
                     </button>

                     <div class="form-text mt-2 text-center" v-if="!saving && !hasPendingChanges">No pending changes.</div>
                     <div class="form-text mt-2" v-if="identityFieldsLocked">Business name and country code can only be changed by a super admin.</div>
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

const COUNTRY_CODES = ["PH", "US", "CA", "AU", "NZ", "GB", "SG", "MY", "ID", "TH", "VN", "JP", "KR", "HK", "TW", "CN", "IN", "AE"];

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
   "Europe/London",
   "America/New_York",
   "America/Los_Angeles",
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
      note: "Name, country, and payroll behavior.",
   },
   {
      id: "business-location",
      label: "Location & hours",
      note: "Address, timezone, amenities, and schedule.",
   },
];

function ensureSelectOption(options, value, formatter = null) {
   if (!value || options.some((option) => option.value === value)) {
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
      businessHoursLabel: function () {
         if (!this.form.opening_time && !this.form.closing_time) {
            return "Not set";
         }

         return [this.formatShortTime(this.form.opening_time), this.formatShortTime(this.form.closing_time)].filter(Boolean).join(" - ");
      },
      hasPendingChanges: function () {
         return JSON.stringify(this.buildPayload()) !== JSON.stringify(this.normalizePayload(this.profile));
      },
   },

   methods: {
      getForm: function (profile) {
         return {
            name: profile.name || "",
            country_code: profile.country_code || "PH",
            pay_overwork_hours: Boolean(profile.pay_overwork_hours),
            payroll_withholding_tax_enabled: Boolean(profile.payroll_withholding_tax_enabled),
            payroll_government_contributions_enabled: Boolean(profile.payroll_government_contributions_enabled),
            city: profile.city || "",
            province: profile.province || "",
            address: profile.address || "",
            opening_time: profile.opening_time ? String(profile.opening_time).slice(0, 5) : "",
            closing_time: profile.closing_time ? String(profile.closing_time).slice(0, 5) : "",
            timezone: profile.timezone || "Asia/Manila",
            amenities_text: Array.isArray(profile.amenities) ? profile.amenities.join(", ") : "",
         };
      },

      normalizePayload: function (source, overrides = {}) {
         return {
            name: source.name || "",
            country_code: String(source.country_code || "PH").toUpperCase(),
            pay_overwork_hours: Boolean(source.pay_overwork_hours),
            payroll_withholding_tax_enabled: Boolean(source.payroll_withholding_tax_enabled),
            payroll_government_contributions_enabled: Boolean(source.payroll_government_contributions_enabled),
            city: source.city || "",
            province: source.province || "",
            address: source.address || "",
            timezone: source.timezone || "Asia/Manila",
            amenities: Array.isArray(source.amenities) ? source.amenities.filter(Boolean) : [],
            opening_time: source.opening_time ? String(source.opening_time).slice(0, 5) : "",
            closing_time: source.closing_time ? String(source.closing_time).slice(0, 5) : "",
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
