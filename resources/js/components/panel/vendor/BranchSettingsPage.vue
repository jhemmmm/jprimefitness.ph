<template>
   <div class="p-4">
      <div class="row justify-content-center">
         <div class="col-lg-9">
            <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Branch settings saved successfully.</div>
            <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

            <div class="row g-3">
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Branch Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" v-model="form.name" :disabled="!is('super admin')" />
                  <div class="invalid-feedback" v-if="errors.name">{{ errors.name[0] }}</div>
               </div>
               <div class="col-md-3">
                  <label class="form-label form-label-sm fw-semibold">Status <span class="text-danger">*</span></label>
                  <select class="form-select" :class="{ 'is-invalid': errors.status }" v-model="form.status" :disabled="!is('super admin')">
                     <option value="open">Open</option>
                     <option value="closed">Closed</option>
                     <option value="coming_soon">Coming Soon</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.status">{{ errors.status[0] }}</div>
               </div>
               <div class="col-md-3">
                  <label class="form-label form-label-sm fw-semibold">Country Code <span class="text-danger">*</span></label>
                  <select class="form-select" :class="{ 'is-invalid': errors.country_code }" v-model="form.country_code" :disabled="!is('super admin')">
                     <option v-for="country in countryOptions" :key="country.value" :value="country.value">{{ country.label }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.country_code">{{ errors.country_code[0] }}</div>
               </div>
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
                  <label class="form-label form-label-sm fw-semibold">Phone</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="form.phone" />
                  <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone[0] }}</div>
               </div>
               <div class="col-md-4">
                  <label class="form-label form-label-sm fw-semibold">Email</label>
                  <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" v-model="form.email" />
                  <div class="invalid-feedback" v-if="errors.email">{{ errors.email[0] }}</div>
               </div>
               <div class="col-md-4">
                  <label class="form-label form-label-sm fw-semibold">Google Maps URL</label>
                  <input type="url" class="form-control" :class="{ 'is-invalid': errors.map_url }" v-model="form.map_url" />
                  <div class="invalid-feedback" v-if="errors.map_url">{{ errors.map_url[0] }}</div>
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
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Timezone</label>
                  <select class="form-select" :class="{ 'is-invalid': errors.timezone }" v-model="form.timezone">
                     <option v-for="timezone in timezoneOptions" :key="timezone.value" :value="timezone.value">{{ timezone.label }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.timezone">{{ errors.timezone[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Current Gallery Size</label>
                  <input type="text" class="form-control" :value="`${Array.isArray(branch.photos) ? branch.photos.length : 0} photo(s)`" disabled />
               </div>
               <div class="col-12">
                  <label class="form-label form-label-sm fw-semibold">Amenities</label>
                  <textarea class="form-control" rows="4" v-model="form.amenities_text" :class="{ 'is-invalid': errors.amenities }" placeholder="Separate amenities with commas or line breaks"></textarea>
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

            <div class="mt-4 d-flex justify-content-end">
               <button class="btn btn-danger px-4" @click="save" :disabled="saving">
                  <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                  Save Settings
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { COUNTRY_OPTIONS, TIMEZONE_OPTIONS, ensureSelectOption } from "../_vendor/branchFormOptions";

export default {
   props: {
      branch: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.branch),
      };
   },

   watch: {
      branch: function (value) {
         this.form = this.getForm(value);
      },
   },

   computed: {
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
   },

   methods: {
      getForm: function (branch) {
         return {
            name: branch.name || "",
            status: branch.status || "open",
            country_code: branch.country_code || "PH",
            city: branch.city || "",
            province: branch.province || "",
            address: branch.address || "",
            phone: branch.phone || "",
            email: branch.email || "",
            map_url: branch.map_url || "",
            opening_time: branch.opening_time ? String(branch.opening_time).slice(0, 5) : "",
            closing_time: branch.closing_time ? String(branch.closing_time).slice(0, 5) : "",
            facebook_url: branch.facebook_url || "",
            messenger_url: branch.messenger_url || "",
            whatsapp_url: branch.whatsapp_url || "",
            timezone: branch.timezone || "Asia/Manila",
            amenities_text: Array.isArray(branch.amenities) ? branch.amenities.join(", ") : "",
         };
      },

      buildPayload: function (overrides = {}) {
         return {
            name: this.form.name,
            status: this.form.status,
            country_code: (this.form.country_code || "PH").toUpperCase(),
            city: this.form.city,
            province: this.form.province,
            address: this.form.address,
            phone: this.form.phone,
            email: this.form.email,
            timezone: this.form.timezone,
            amenities: this.parsedAmenities,
            opening_time: this.form.opening_time,
            closing_time: this.form.closing_time,
            facebook_url: this.form.facebook_url,
            messenger_url: this.form.messenger_url,
            whatsapp_url: this.form.whatsapp_url,
            map_url: this.form.map_url,
            ...overrides,
         };
      },

      save: function () {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};

         axios
            .put(`/panel/branches/${this.branch.id}`, this.buildPayload())
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
