<template>
   <div>
      <div class="card border-0 shadow-sm">
         <div class="card-body p-4">
            <form method="POST" action="/register" @submit.prevent="submit" novalidate>
               <div v-if="bannerError" class="alert alert-danger small">{{ bannerError }}</div>

               <template v-if="renew">
                  <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Your membership</h6>
                  <div class="row g-3 mb-3">
                     <div class="col-12">
                        <label class="form-label small fw-semibold">Email on your membership <span class="text-danger">*</span></label>
                        <input v-model="form.email" type="email" class="form-control rounded-1" :class="{ 'is-invalid': errors.email }" readonly />
                        <div class="invalid-feedback" v-if="errors.email">{{ errors.email[0] }}</div>
                        <div class="form-text" v-else>Matched to your existing record - no need to fill in your details again.</div>
                     </div>
                  </div>
               </template>

               <template v-else>
                  <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Personal info</h6>
                  <div class="row g-3 mb-3">
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full name <span class="text-danger">*</span></label>
                        <input v-model="form.name" type="text" class="form-control rounded-1" :class="{ 'is-invalid': errors.name }" placeholder="Your full name" />
                        <div class="invalid-feedback" v-if="errors.name">{{ errors.name[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                        <input v-model="form.email" type="email" class="form-control rounded-1" :class="{ 'is-invalid': errors.email }" placeholder="you@example.com" />
                        <div class="invalid-feedback" v-if="errors.email">{{ errors.email[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                        <input v-model="form.phone" type="tel" class="form-control rounded-1" :class="{ 'is-invalid': errors.phone }" placeholder="0917..." />
                        <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Date of birth <span class="text-danger">*</span></label>
                        <input v-model="form.date_of_birth" type="date" class="form-control rounded-1" :class="{ 'is-invalid': errors.date_of_birth }" />
                        <div class="invalid-feedback" v-if="errors.date_of_birth">{{ errors.date_of_birth[0] }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label small fw-semibold">Address <span class="text-danger">*</span></label>
                        <input v-model="form.address" type="text" class="form-control rounded-1" :class="{ 'is-invalid': errors.address }" placeholder="Street, Barangay, City" />
                        <div class="invalid-feedback" v-if="errors.address">{{ errors.address[0] }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label small fw-semibold">Gender</label>
                        <select v-model="form.gender" class="form-select rounded-1" :class="{ 'is-invalid': errors.gender }">
                           <option value="">Prefer not to say</option>
                           <option value="male">Male</option>
                           <option value="female">Female</option>
                           <option value="other">Other</option>
                        </select>
                        <div class="invalid-feedback" v-if="errors.gender">{{ errors.gender[0] }}</div>
                     </div>
                  </div>

                  <h6 class="text-uppercase text-muted fw-bold mt-4 mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Emergency contact</h6>
                  <div class="row g-3 mb-3">
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Contact name <span class="text-danger">*</span></label>
                        <input v-model="form.emergency_contact_name" type="text" class="form-control rounded-1" :class="{ 'is-invalid': errors.emergency_contact_name }" />
                        <div class="invalid-feedback" v-if="errors.emergency_contact_name">{{ errors.emergency_contact_name[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Contact phone <span class="text-danger">*</span></label>
                        <input v-model="form.emergency_contact_phone" type="tel" class="form-control rounded-1" :class="{ 'is-invalid': errors.emergency_contact_phone }" />
                        <div class="invalid-feedback" v-if="errors.emergency_contact_phone">{{ errors.emergency_contact_phone[0] }}</div>
                     </div>
                  </div>
               </template>

               <h6 class="text-uppercase text-muted fw-bold mt-4 mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Membership</h6>
               <div class="row g-3 mb-3">
                  <div :class="renew ? 'col-12' : 'col-md-7'">
                     <label class="form-label small fw-semibold">Plan <span class="text-danger">*</span></label>
                     <select v-model="form.rate_plan_id" class="form-select rounded-1" :class="{ 'is-invalid': errors.rate_plan_id }">
                        <option value="">Choose a plan</option>
                        <option v-for="plan in membershipPlans" :key="'reg-rp-' + plan.id" :value="plan.id">{{ plan.name }} - &#8369;{{ $filters.formatMoney(plan.price) }} / {{ plan.duration_days }}d</option>
                     </select>
                     <div class="invalid-feedback" v-if="errors.rate_plan_id">{{ errors.rate_plan_id[0] }}</div>
                     <div class="form-text" v-if="renew">Your new plan starts the day after your current membership ends, or today if it has already expired.</div>
                  </div>
                  <template v-if="!renew">
                     <div class="col-md-5">
                        <label class="form-label small fw-semibold">Preferred start date</label>
                        <input v-model="form.preferred_start_date" type="date" class="form-control rounded-1" :class="{ 'is-invalid': errors.preferred_start_date }" />
                        <div class="invalid-feedback" v-if="errors.preferred_start_date">{{ errors.preferred_start_date[0] }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label small fw-semibold">Notes (optional)</label>
                        <textarea v-model="form.notes" rows="3" class="form-control rounded-1" :class="{ 'is-invalid': errors.notes }" placeholder="Anything we should know? (injuries, training goals, etc.)"></textarea>
                        <div class="invalid-feedback" v-if="errors.notes">{{ errors.notes[0] }}</div>
                     </div>
                  </template>
               </div>

               <h6 class="text-uppercase text-muted fw-bold mt-4 mb-1" style="font-size: 0.72rem; letter-spacing: 1.2px">Discount</h6>
               <p class="text-muted small mb-3" v-if="renew">{{ initialDiscount ? "Your discount on file is applied automatically - " : "Discounted renewals are " }}paid at the front desk so staff can check your ID.</p>
               <p class="text-muted small mb-3" v-else>Saved to your profile and applied to renewals automatically.</p>
               <div class="row g-3 mb-3">
                  <div class="col-6" v-for="option in discountOptions" :key="option.value">
                     <label class="payment-option h-100" :class="{ 'is-active': form.discount_type === option.value }">
                        <input type="radio" v-model="form.discount_type" :value="option.value" @change="onDiscountChange" />
                        <div>
                           <div class="fw-semibold"><i :class="`bi ${option.icon} me-1`"></i>{{ $filters.discountLabel(option.value) || "None" }}</div>
                           <div class="text-muted small">{{ option.hint }}</div>
                        </div>
                     </label>
                  </div>
                  <div class="col-12 invalid-feedback d-block" v-if="errors.discount_type">{{ errors.discount_type[0] }}</div>
               </div>

               <div v-if="hasDiscount && discountSummary" class="alert alert-info small mb-3 py-2">
                  <i class="bi bi-info-circle me-1"></i>
                  {{ discountSummary }}
               </div>

               <h6 class="text-uppercase text-muted fw-bold mt-4 mb-3" style="font-size: 0.72rem; letter-spacing: 1.2px">Payment</h6>
               <div class="row g-3 mb-3">
                  <div class="col-md-6">
                     <label class="payment-option" :class="{ 'is-active': form.payment_method === 'online', 'opacity-50': hasDiscount }" :style="hasDiscount ? 'cursor: not-allowed;' : ''">
                        <input type="radio" v-model="form.payment_method" value="online" :disabled="hasDiscount" />
                        <div>
                           <div class="fw-semibold"><i class="bi bi-credit-card-2-front me-1"></i>Pay online now</div>
                           <div class="text-muted small" v-if="hasDiscount">Unavailable - staff must verify your ID on-site</div>
                           <div class="text-muted small" v-else>GCash, Maya or Instant Pay</div>
                        </div>
                     </label>
                  </div>
                  <div class="col-md-6">
                     <label class="payment-option" :class="{ 'is-active': form.payment_method === 'on_site' }">
                        <input type="radio" v-model="form.payment_method" value="on_site" />
                        <div>
                           <div class="fw-semibold"><i class="bi bi-shop me-1"></i>Pay at the gym</div>
                           <div class="text-muted small">Activate when you arrive</div>
                        </div>
                     </label>
                  </div>
                  <div class="col-12 invalid-feedback d-block" v-if="errors.payment_method">{{ errors.payment_method[0] }}</div>
               </div>

               <div class="form-check mt-3 mb-3">
                  <input v-model="form.terms_accepted" class="form-check-input" :class="{ 'is-invalid': errors.terms_accepted }" type="checkbox" id="terms_accepted" />
                  <label class="form-check-label small" for="terms_accepted">
                     I agree to the <a href="/terms" target="_blank" class="text-danger">Terms and Conditions</a>
                     and confirm my membership activates only after payment is confirmed.
                  </label>
                  <div class="invalid-feedback d-block" v-if="errors.terms_accepted">{{ errors.terms_accepted[0] }}</div>
               </div>

               <div class="invalid-feedback d-block" v-if="errors.recaptcha">{{ errors.recaptcha[0] }}</div>

               <div class="d-grid mt-4">
                  <button type="submit" class="btn btn-danger fw-semibold rounded-1 py-2" :disabled="submitting">
                     <span v-if="submitting"><i class="bi bi-hourglass-split me-1"></i>Submitting…</span>
                     <span v-else-if="renew"><i class="bi bi-arrow-repeat me-1"></i>Renew Membership</span>
                     <span v-else><i class="bi bi-person-plus-fill me-1"></i>Submit Registration</span>
                  </button>
               </div>
            </form>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="successModal" aria-hidden="true">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold">{{ renew ? "Renewal received" : "Registration received" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" @click="reset"></button>
               </div>
               <div class="modal-body text-center py-4">
                  <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem"></i>
                  <p class="text-muted mt-3 mb-3">{{ success }}</p>
                  <p class="text-muted small mb-0" v-if="addressLine"><i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ addressLine }}</p>
               </div>
               <div class="modal-footer border-0">
                  <button type="button" class="btn btn-outline-secondary rounded-1 fw-semibold" data-bs-dismiss="modal" @click="reset">{{ renew ? "Done" : "Submit another" }}</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { getRecaptchaToken } from "../../recaptcha";

// Public sign-up form. `renew` hides the personal-info fields: POST /register then
// matches the email to the existing member and only creates the new subscription.
export default {
   props: {
      business: { type: Object, default: () => ({}) },
      ratePlans: { type: Array, default: () => [] },
      renew: { type: Boolean, default: false },
      initialEmail: { type: String, default: "" },
      initialDiscount: { type: String, default: "" },
   },
   data: function () {
      return {
         form: this.emptyForm(),
         submitting: false,
         success: null,
         bannerError: null,
         errors: {},
         successModal: null,
      };
   },
   computed: {
      addressLine: function () {
         return this.$filters.addressLine(this.business);
      },
      membershipPlans: function () {
         return this.ratePlans.filter((p) => !p.is_walk_in_only);
      },
      discountOptions: function () {
         // types come from the backend (window.JPrime.discountLabels); only the icon/ID hint is local copy
         const extras = {
            student: { icon: "bi-mortarboard", hint: "20% off - bring valid school ID" },
            senior: { icon: "bi-person-badge", hint: "20% off - bring senior citizen ID" },
            pwd: { icon: "bi-universal-access", hint: "20% off - bring PWD ID" },
         };
         return [
            { value: "", icon: "bi-x-circle", hint: "Regular rate" },
            ...Object.keys(this.$filters.discountLabels()).map((value) => ({ value, icon: "bi-percent", hint: "20% off - bring valid ID", ...extras[value] })),
         ];
      },
      hasDiscount: function () {
         return Boolean(this.$filters.discountLabel(this.form.discount_type));
      },
      selectedPlan: function () {
         const id = this.form.rate_plan_id;
         if (!id) return null;
         return this.membershipPlans.find((p) => String(p.id) === String(id)) || null;
      },
      discountSummary: function () {
         if (!this.hasDiscount) return "";
         const label = this.$filters.discountLabel(this.form.discount_type);
         const plan = this.selectedPlan;
         if (!plan) {
            return `${label} discount: 20% off. Payment must be on-site so staff can verify your ID.`;
         }
         const base = parseFloat(plan.price || 0);
         const discounted = Math.round(base * 80) / 100;
         return `${label} discount: ₱${this.$filters.formatMoney(base)} → ₱${this.$filters.formatMoney(discounted)} (20% off). Payment must be on-site so staff can verify your ID.`;
      },
   },
   mounted: function () {
      this.successModal = new Modal(this.$refs.successModal);
   },
   beforeUnmount: function () {
      this.successModal?.dispose();
   },
   methods: {
      emptyForm: function () {
         return {
            name: "",
            email: this.initialEmail || "",
            phone: "",
            address: "",
            date_of_birth: "",
            gender: "",
            emergency_contact_name: "",
            emergency_contact_phone: "",
            rate_plan_id: "",
            preferred_start_date: "",
            notes: "",
            payment_method: this.initialDiscount ? "on_site" : "online",
            discount_type: this.initialDiscount || "",
            terms_accepted: false,
         };
      },
      selectPlan: function (planId) {
         this.form.rate_plan_id = planId;
      },
      onDiscountChange: function () {
         if (this.hasDiscount) {
            this.form.payment_method = "on_site";
         }
      },
      reset: function () {
         this.form = this.emptyForm();
         this.success = null;
         this.bannerError = null;
         this.errors = {};
         this.successModal?.hide();
      },
      scrollToProblem: function () {
         this.$nextTick(() => {
            const target = this.$el.querySelector(".is-invalid, .alert-danger");
            if (target) target.scrollIntoView({ behavior: "smooth", block: "center" });
         });
      },
      submit: function () {
         this.submitting = true;
         this.bannerError = null;
         this.errors = {};

         getRecaptchaToken("register")
            .then((recaptchaToken) =>
               // the renew page keeps its signed query string so the server knows which member this is
               axios.post(this.renew ? "/renew" + window.location.search : "/register", {
                  ...this.form,
                  date_of_birth: this.form.date_of_birth || null,
                  gender: this.form.gender || null,
                  emergency_contact_name: this.form.emergency_contact_name || null,
                  emergency_contact_phone: this.form.emergency_contact_phone || null,
                  preferred_start_date: this.form.preferred_start_date || null,
                  notes: this.form.notes || null,
                  discount_type: this.form.discount_type || null,
                  recaptcha_token: recaptchaToken,
               }),
            )
            .then((res) => {
               if (res.data?.payment?.checkout_url) {
                  window.location.assign(res.data.payment.checkout_url);
                  return;
               }

               this.success = res.data?.message || "We received your registration.";
               this.successModal?.show();
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.errors = err.response.data.errors || {};
                  this.bannerError = err.response.data.message || "Please correct the highlighted fields.";
               } else {
                  this.bannerError = err.response?.data?.message || "Network error - please check your connection and try again.";
               }
               this.scrollToProblem();
            })
            .finally(() => {
               this.submitting = false;
            });
      },
   },
};
</script>
