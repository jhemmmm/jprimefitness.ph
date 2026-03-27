<template>
   <div class="p-4">
      <div class="row justify-content-center">
         <div class="col-lg-9">
            <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Branch settings saved successfully.</div>
            <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

            <div class="row g-3">
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
import { TIMEZONE_OPTIONS, ensureSelectOption } from "../_vendor/branchFormOptions";

export default {
   props: {
      branch: { type: Object, required: true },
   },

   emits: ["updated"],

   data() {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.branch),
      };
   },

   watch: {
      branch(value) {
         this.form = this.getForm(value);
      },
   },

   computed: {
      timezoneOptions() {
         return ensureSelectOption(TIMEZONE_OPTIONS, this.form.timezone, (value) => value);
      },
      parsedAmenities() {
         return this.form.amenities_text
            .split(/[\n,]+/)
            .map((value) => value.trim())
            .filter(Boolean);
      },
   },

   methods: {
      getForm(branch) {
         return {
            timezone: branch.timezone || "Asia/Manila",
            amenities_text: Array.isArray(branch.amenities) ? branch.amenities.join(", ") : "",
         };
      },

      buildPayload(overrides = {}) {
         return {
            name: this.branch.name || "",
            status: this.branch.status || "open",
            country_code: this.branch.country_code || "PH",
            city: this.branch.city || "",
            province: this.branch.province || "",
            address: this.branch.address || "",
            phone: this.branch.phone || "",
            email: this.branch.email || "",
            timezone: this.branch.timezone || "Asia/Manila",
            amenities: Array.isArray(this.branch.amenities) ? this.branch.amenities : [],
            opening_time: this.branch.opening_time ? String(this.branch.opening_time).slice(0, 5) : "",
            closing_time: this.branch.closing_time ? String(this.branch.closing_time).slice(0, 5) : "",
            facebook_url: this.branch.facebook_url || "",
            messenger_url: this.branch.messenger_url || "",
            whatsapp_url: this.branch.whatsapp_url || "",
            map_url: this.branch.map_url || "",
            payroll_settings: this.branch.payroll_settings || { pay_frequency: "semi_monthly", income_tax_mode: "manual", contributions: [] },
            ...overrides,
         };
      },

      async save() {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};

         try {
            const response = await axios.put(
               `/panel/branches/${this.branch.id}`,
               this.buildPayload({
                  timezone: this.form.timezone,
                  amenities: this.parsedAmenities,
               }),
            );

            this.saved = true;
            this.$emit("updated", response.data);
            setTimeout(() => (this.saved = false), 3000);
         } catch (error) {
            if (error.response?.status === 422) {
               this.errors = error.response.data.errors || {};
            } else {
               this.generalError = error.response?.data?.message || "Something went wrong.";
            }
         } finally {
            this.saving = false;
         }
      },
   },
};
</script>
