<template>
   <div>
      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Settings saved successfully.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-4">
         <div class="col-lg-3">
            <div class="list-group">
               <button type="button" class="list-group-item list-group-item-action" :class="{ active: activeSection === section.id }" v-for="section in sections" :key="section.id" @click="activeSection = section.id">
                  <div class="fw-semibold">{{ section.label }}<i class="bi bi-exclamation-circle-fill text-danger ms-2" v-if="sectionHasErrors(section)"></i></div>
                  <div class="small opacity-75">{{ section.note }}</div>
               </button>
            </div>
         </div>

         <div class="col-lg-9">
            <section class="panel-card business-settings-section" v-show="activeSection === 'business-identity'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Business identity</div>
                     <div class="panel-card-sub">Set the business name, country, and payroll behavior.</div>
                  </div>
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

            <section class="panel-card business-settings-section" v-show="activeSection === 'business-location'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Location & hours</div>
                     <div class="panel-card-sub">Maintain the operating location and schedule used across the panel.</div>
                  </div>
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
                        <label class="form-label form-label-sm fw-semibold">Timezone</label>
                        <select class="form-select" :class="{ 'is-invalid': errors.timezone }" v-model="form.timezone">
                           <option v-for="timezone in timezoneOptions" :key="timezone.value" :value="timezone.value">{{ timezone.label }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="errors.timezone">{{ errors.timezone[0] }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Weekly Operating Hours</label>
                        <div class="business-settings-hours-grid" :class="{ 'is-invalid': errors.operating_hours }">
                           <div class="business-settings-hours-row" v-for="(day, index) in form.operating_hours" :key="day.key">
                              <div class="business-settings-hours-day">{{ day.day }}</div>
                              <div>
                                 <label class="form-label form-label-sm text-muted" :for="`opening-${day.key}`">Opening</label>
                                 <input
                                    :id="`opening-${day.key}`"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': firstError(`operating_hours.${index}.opening_time`) }"
                                    v-model="day.opening_time"
                                 />
                                 <div class="invalid-feedback" v-if="firstError(`operating_hours.${index}.opening_time`)">
                                    {{ firstError(`operating_hours.${index}.opening_time`) }}
                                 </div>
                              </div>
                              <div>
                                 <label class="form-label form-label-sm text-muted" :for="`closing-${day.key}`">Closing</label>
                                 <input
                                    :id="`closing-${day.key}`"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': firstError(`operating_hours.${index}.closing_time`) }"
                                    v-model="day.closing_time"
                                 />
                                 <div class="invalid-feedback" v-if="firstError(`operating_hours.${index}.closing_time`)">
                                    {{ firstError(`operating_hours.${index}.closing_time`) }}
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="invalid-feedback d-block" v-if="errors.operating_hours">{{ errors.operating_hours[0] }}</div>
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

            <section class="panel-card business-settings-section" v-show="activeSection === 'social-links'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Social links</div>
                     <div class="panel-card-sub">Shown as icons in the public site footer. Leave a link blank to hide its icon.</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <div class="row g-3">
                     <div class="col-md-6" v-for="(label, network) in socialNetworks" :key="network">
                        <label class="form-label form-label-sm fw-semibold"><i :class="`bi bi-${network} me-1`"></i>{{ label }}</label>
                        <input type="url" class="form-control" :class="{ 'is-invalid': firstError(`social_links.${network}`) }" v-model="form.social_links[network]" :placeholder="`https://${network}.com/…`" />
                        <div class="invalid-feedback" v-if="firstError(`social_links.${network}`)">{{ firstError(`social_links.${network}`) }}</div>
                     </div>
                  </div>
               </div>
            </section>

            <section class="panel-card business-settings-section" v-if="activeSection === 'roles'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Roles &amp; permissions</div>
                     <div class="panel-card-sub">Who can do what in the panel. Changes here save immediately.</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <roles-permissions-section />
               </div>
            </section>

            <div class="d-flex flex-wrap justify-content-end align-items-center gap-3 mt-4" v-if="activeSection !== 'roles'">
               <span class="form-text m-0" v-if="identityFieldsLocked">Business name and country code can only be changed by a super admin.</span>
               <span class="form-text m-0" v-else-if="!saving && !hasPendingChanges">No pending changes.</span>
               <button type="button" class="btn btn-danger px-4" @click="save" :disabled="saving || !hasPendingChanges">
                  <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                  Save Settings
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import RolesPermissionsSection from "./RolesPermissionsSection.vue";

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

const OPERATING_DAYS = [
   { key: "monday", day: "Monday" },
   { key: "tuesday", day: "Tuesday" },
   { key: "wednesday", day: "Wednesday" },
   { key: "thursday", day: "Thursday" },
   { key: "friday", day: "Friday" },
   { key: "saturday", day: "Saturday" },
   { key: "sunday", day: "Sunday" },
];

// `fields` are the payload keys each section owns, used to flag validation errors in the nav.
const SECTIONS = [
   {
      id: "business-identity",
      label: "Business identity",
      note: "Name, country, and payroll behavior.",
      fields: ["name", "country_code", "pay_overwork_hours", "payroll_withholding_tax_enabled", "payroll_government_contributions_enabled"],
   },
   {
      id: "business-location",
      label: "Location & hours",
      note: "Address, timezone, amenities, and schedule.",
      fields: ["city", "province", "address", "timezone", "operating_hours", "amenities"],
   },
   {
      id: "social-links",
      label: "Social links",
      note: "Icons in the public site footer.",
      fields: ["social_links"],
   },
   {
      id: "roles",
      label: "Roles & permissions",
      note: "Who can do what in the panel.",
      fields: [],
   },
];

function socialLinks(source) {
   return Object.fromEntries(Object.keys(window.JPrime?.socialNetworks || {}).map((network) => [network, source.social_links?.[network] || ""]));
}

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
   components: { RolesPermissionsSection },

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
         activeSection: SECTIONS[0].id,
         form: this.getForm(this.profile),
      };
   },

   watch: {
      profile: function (value) {
         this.form = this.getForm(value);
      },
   },

   computed: {
      sections: function () {
         return SECTIONS;
      },
      socialNetworks: function () {
         return window.JPrime?.socialNetworks || {};
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
            timezone: profile.timezone || "Asia/Manila",
            operating_hours: this.getOperatingHours(profile),
            amenities_text: Array.isArray(profile.amenities) ? profile.amenities.join(", ") : "",
            social_links: socialLinks(profile),
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
            operating_hours: this.getOperatingHours(source),
            social_links: socialLinks(source),
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

      getOperatingHours: function (source) {
         const entries = Array.isArray(source.operating_hours) ? source.operating_hours : [];

         return OPERATING_DAYS.map((day) => {
            const entry = entries.find((candidate) => candidate.day === day.day || candidate.key === day.key) || {};
            const weekdayOpeningTime = source.opening_time ? String(source.opening_time).slice(0, 5) : "06:00";
            const weekdayClosingTime = source.closing_time ? String(source.closing_time).slice(0, 5) : "23:00";
            const openingTime = day.key === "saturday" || day.key === "sunday" ? "08:00" : weekdayOpeningTime;

            return {
               ...day,
               opening_time: entry.opening_time ? String(entry.opening_time).slice(0, 5) : openingTime,
               closing_time: entry.closing_time ? String(entry.closing_time).slice(0, 5) : weekdayClosingTime,
            };
         });
      },

      firstError: function (key) {
         return Array.isArray(this.errors[key]) ? this.errors[key][0] : "";
      },

      sectionHasErrors: function (section) {
         return Object.keys(this.errors).some((key) => section.fields.includes(key.split(".")[0]));
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
                  this.activeSection = SECTIONS.find((section) => this.sectionHasErrors(section))?.id || this.activeSection;
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
