<template>
   <div class="pricing-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Pricing & Rates</h4>
            <p class="text-muted small mb-0">Manage single-location membership pricing and PT package rates for {{ currentBusinessName }}</p>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-lg-3" v-for="card in statCards" :key="card.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="card.iconBg">
                  <i class="bi" :class="[card.icon, card.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ card.label }}</div>
                  <div class="stat-card-value" v-if="loading">
                     <div class="skeleton-box" style="width: 52px; height: 18px; border-radius: 5px"></div>
                  </div>
                  <div class="stat-card-value" v-else>{{ card.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="panel-card mb-4">
         <div class="panel-card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <span class="panel-card-title">Membership Rates</span>
            <button class="btn btn-danger btn-sm px-3" v-if="canManagePricing" @click="openCreateMembershipModal" :disabled="pricing.available_membership_rate_plans.length === 0">
               <i class="bi bi-plus-lg me-1"></i>
               Add Membership Rate
            </button>
         </div>

         <div v-if="loading" class="p-4 text-muted">Loading membership rates...</div>
         <div v-else-if="pricing.membership_rates.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-card-checklist empty-icon"></i>
            <p class="mt-2 mb-1">No membership rates configured yet.</p>
         </div>
         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Manager Commission</th>
                        <th>Status</th>
                        <th>Effectivity</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="rate in pricing.membership_rates" :key="'membership-' + rate.id">
                        <td>
                           <div class="fw-semibold">{{ rate.name }}</div>
                           <div class="text-muted small">{{ rate.description || "No description" }}</div>
                        </td>
                        <td class="small">{{ membershipDurationLabel(rate.duration_days) }}</td>
                        <td class="fw-semibold">₱{{ $filters.formatMoney(rate.price) }}</td>
                        <td class="small">{{ $filters.formatMoney(rate.manager_commission_rate) }}%</td>
                        <td>
                           <span :class="['m-badge', rate.is_active ? 'm-badge--active' : 'm-badge--inactive']">{{ rate.is_active ? "Active" : "Inactive" }}</span>
                        </td>
                        <td class="small text-muted">
                           <div>From: {{ formatDate(rate.effective_from) }}</div>
                           <div>Until: {{ formatDate(rate.effective_until) }}</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end" v-if="canManagePricing">
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditMembershipModal(rate)"><i class="bi bi-pencil tbl-icon"></i></button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete('membership', rate)"><i class="bi bi-trash tbl-icon"></i></button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="rate in pricing.membership_rates" :key="'membership-mobile-' + rate.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div>
                           <div class="member-card-name">{{ rate.name }}</div>
                           <div class="member-card-sub">{{ rate.description || "No description" }}</div>
                        </div>
                     </div>
                     <div class="dropdown" v-if="canManagePricing">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditMembershipModal(rate)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li>
                              <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete('membership', rate)"><i class="bi bi-trash me-2"></i>Delete</a>
                           </li>
                        </ul>
                     </div>
                  </div>

                  <div class="member-card-tags ps-0">
                     <span :class="['m-badge', rate.is_active ? 'm-badge--active' : 'm-badge--inactive']">{{ rate.is_active ? "Active" : "Inactive" }}</span>
                     <span class="m-badge m-badge--plan">{{ membershipDurationLabel(rate.duration_days) }}</span>
                  </div>

                  <div class="small text-muted">Price: ₱{{ $filters.formatMoney(rate.price) }}</div>
                  <div class="small text-muted mt-1">Manager Commission: {{ $filters.formatMoney(rate.manager_commission_rate) }}%</div>
                  <div class="small text-muted mt-1">From: {{ formatDate(rate.effective_from) }}</div>
                  <div class="small text-muted mt-1">Until: {{ formatDate(rate.effective_until) }}</div>

                  <div class="member-card-footer mt-3">
                     <span>Membership Rate</span>
                     <span class="member-card-num">#{{ rate.id }}</span>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <span class="panel-card-title">PT Rates</span>
            <button class="btn btn-danger btn-sm px-3" v-if="canManagePricing" @click="openCreatePtModal" :disabled="pricing.available_pt_products.length === 0">
               <i class="bi bi-plus-lg me-1"></i>
               Add PT Rate
            </button>
         </div>

         <div v-if="loading" class="p-4 text-muted">Loading PT rates...</div>
         <div v-else-if="pricing.pt_rates.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-person-badge-fill empty-icon"></i>
            <p class="mt-2 mb-1">No PT rates configured yet.</p>
         </div>
         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Package</th>
                        <th>Sessions</th>
                        <th>Price</th>
                        <th>Coach Commission</th>
                        <th>Status</th>
                        <th>Effectivity</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="rate in pricing.pt_rates" :key="'pt-' + rate.id">
                        <td>
                           <div class="fw-semibold">{{ rate.name }}</div>
                           <div class="text-muted small">{{ rate.description || rate.category || "No description" }}</div>
                        </td>
                        <td class="small">{{ rate.session_count }} session{{ rate.session_count !== 1 ? "s" : "" }}</td>
                        <td class="fw-semibold">₱{{ $filters.formatMoney(rate.price) }}</td>
                        <td class="small">{{ $filters.formatMoney(rate.coach_commission_rate) }}%</td>
                        <td>
                           <span :class="['m-badge', rate.is_active ? 'm-badge--active' : 'm-badge--inactive']">{{ rate.is_active ? "Active" : "Inactive" }}</span>
                        </td>
                        <td class="small text-muted">
                           <div>From: {{ formatDate(rate.effective_from) }}</div>
                           <div>Until: {{ formatDate(rate.effective_until) }}</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end" v-if="canManagePricing">
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditPtModal(rate)"><i class="bi bi-pencil tbl-icon"></i></button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete('pt', rate)"><i class="bi bi-trash tbl-icon"></i></button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="rate in pricing.pt_rates" :key="'pt-mobile-' + rate.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div>
                           <div class="member-card-name">{{ rate.name }}</div>
                           <div class="member-card-sub">{{ rate.description || rate.category || "No description" }}</div>
                        </div>
                     </div>
                     <div class="dropdown" v-if="canManagePricing">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditPtModal(rate)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li>
                              <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete('pt', rate)"><i class="bi bi-trash me-2"></i>Delete</a>
                           </li>
                        </ul>
                     </div>
                  </div>

                  <div class="member-card-tags ps-0">
                     <span :class="['m-badge', rate.is_active ? 'm-badge--active' : 'm-badge--inactive']">{{ rate.is_active ? "Active" : "Inactive" }}</span>
                     <span class="m-badge m-badge--plan">{{ rate.session_count }} session{{ rate.session_count !== 1 ? "s" : "" }}</span>
                  </div>

                  <div class="small text-muted">Price: ₱{{ $filters.formatMoney(rate.price) }}</div>
                  <div class="small text-muted mt-1">Coach Commission: {{ $filters.formatMoney(rate.coach_commission_rate) }}%</div>
                  <div class="small text-muted mt-1">From: {{ formatDate(rate.effective_from) }}</div>
                  <div class="small text-muted mt-1">Until: {{ formatDate(rate.effective_until) }}</div>

                  <div class="member-card-footer mt-3">
                     <span>PT Rate</span>
                     <span class="member-card-num">#{{ rate.id }}</span>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" id="membershipRateModal" tabindex="-1" ref="membershipRateModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ membershipModalMode === "create" ? "Add Membership Rate" : "Edit Membership Rate" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div v-if="membershipFormError" class="alert alert-danger py-2 small mb-3">{{ membershipFormError }}</div>
                  <div class="mb-3" v-if="membershipModalMode === 'create'">
                     <label class="form-label form-label-sm">Plan <span class="text-danger">*</span></label>
                     <select class="form-select" v-model="membershipForm.id" :class="{ 'is-invalid': membershipFormErrors.rate_plan_id }">
                        <option value="" disabled>Select rate plan</option>
                        <option v-for="option in pricing.available_membership_rate_plans" :key="option.id" :value="option.id">{{ option.name }}</option>
                     </select>
                     <div class="invalid-feedback" v-if="membershipFormErrors.rate_plan_id">{{ membershipFormErrors.rate_plan_id }}</div>
                  </div>
                  <div class="mb-3" v-else>
                     <label class="form-label form-label-sm">Plan</label>
                     <input type="text" class="form-control" :value="membershipForm.name" disabled />
                  </div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Price <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="membershipForm.price" :class="{ 'is-invalid': membershipFormErrors.price }" />
                        <div class="invalid-feedback" v-if="membershipFormErrors.price">{{ membershipFormErrors.price }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Manager Commission Rate</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-control" v-model="membershipForm.manager_commission_rate" :class="{ 'is-invalid': membershipFormErrors.manager_commission_rate }" />
                        <div class="invalid-feedback" v-if="membershipFormErrors.manager_commission_rate">{{ membershipFormErrors.manager_commission_rate }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Effective From</label>
                        <input type="date" class="form-control" v-model="membershipForm.effective_from" :class="{ 'is-invalid': membershipFormErrors.effective_from }" />
                        <div class="invalid-feedback" v-if="membershipFormErrors.effective_from">{{ membershipFormErrors.effective_from }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Effective Until</label>
                        <input type="date" class="form-control" v-model="membershipForm.effective_until" :class="{ 'is-invalid': membershipFormErrors.effective_until }" />
                        <div class="invalid-feedback" v-if="membershipFormErrors.effective_until">{{ membershipFormErrors.effective_until }}</div>
                     </div>
                  </div>
                  <div class="form-check form-switch mt-3">
                     <input class="form-check-input" type="checkbox" id="membershipRateActive" v-model="membershipForm.is_active" />
                     <label class="form-check-label" for="membershipRateActive">Active</label>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitMembershipRate" :disabled="submittingMembership">
                     <span v-if="submittingMembership" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ membershipModalMode === "create" ? "Create Rate" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" id="ptRateModal" tabindex="-1" ref="ptRateModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ ptModalMode === "create" ? "Add PT Rate" : "Edit PT Rate" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div v-if="ptFormError" class="alert alert-danger py-2 small mb-3">{{ ptFormError }}</div>
                  <div class="mb-3" v-if="ptModalMode === 'create'">
                     <label class="form-label form-label-sm">Package <span class="text-danger">*</span></label>
                     <select class="form-select" v-model="ptForm.id" :class="{ 'is-invalid': ptFormErrors.pt_product_id }">
                        <option value="" disabled>Select PT package</option>
                        <option v-for="option in pricing.available_pt_products" :key="option.id" :value="option.id">{{ option.name }}</option>
                     </select>
                     <div class="invalid-feedback" v-if="ptFormErrors.pt_product_id">{{ ptFormErrors.pt_product_id }}</div>
                  </div>
                  <div class="mb-3" v-else>
                     <label class="form-label form-label-sm">Package</label>
                     <input type="text" class="form-control" :value="ptForm.name" disabled />
                  </div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Price <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="ptForm.price" :class="{ 'is-invalid': ptFormErrors.price }" />
                        <div class="invalid-feedback" v-if="ptFormErrors.price">{{ ptFormErrors.price }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Coach Commission Rate</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-control" v-model="ptForm.coach_commission_rate" :class="{ 'is-invalid': ptFormErrors.coach_commission_rate }" />
                        <div class="invalid-feedback" v-if="ptFormErrors.coach_commission_rate">{{ ptFormErrors.coach_commission_rate }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Effective From</label>
                        <input type="date" class="form-control" v-model="ptForm.effective_from" :class="{ 'is-invalid': ptFormErrors.effective_from }" />
                        <div class="invalid-feedback" v-if="ptFormErrors.effective_from">{{ ptFormErrors.effective_from }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Effective Until</label>
                        <input type="date" class="form-control" v-model="ptForm.effective_until" :class="{ 'is-invalid': ptFormErrors.effective_until }" />
                        <div class="invalid-feedback" v-if="ptFormErrors.effective_until">{{ ptFormErrors.effective_until }}</div>
                     </div>
                  </div>
                  <div class="form-check form-switch mt-3">
                     <input class="form-check-input" type="checkbox" id="ptRateActive" v-model="ptForm.is_active" />
                     <label class="form-check-label" for="ptRateActive">Active</label>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitPtRate" :disabled="submittingPt">
                     <span v-if="submittingPt" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ ptModalMode === "create" ? "Create Rate" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" id="pricingDeleteModal" tabindex="-1" ref="pricingDeleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Pricing</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p class="mb-1">Are you sure you want to clear this pricing entry?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.name }}</p>
                  <p class="text-muted small mb-0">{{ deleteTarget.type === "membership" ? "Membership rate" : "PT rate" }}</p>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="deletePricing" :disabled="deleting">
                     <span v-if="deleting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     Clear
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDate } from "../../dates";

export default {
   props: {
      canManagePricing: {
         type: Boolean,
         default: false,
      },
   },
   data: function () {
      return {
         loading: true,
         pricing: {
            membership_rates: [],
            available_membership_rate_plans: [],
            pt_rates: [],
            available_pt_products: [],
            stats: {
               membership_configured: 0,
               membership_active: 0,
               pt_configured: 0,
               pt_active: 0,
            },
         },
         membershipRateModal: null,
         ptRateModal: null,
         pricingDeleteModal: null,
         membershipModalMode: "create",
         ptModalMode: "create",
         submittingMembership: false,
         submittingPt: false,
         deleting: false,
         membershipFormError: "",
         ptFormError: "",
         membershipFormErrors: {},
         ptFormErrors: {},
         membershipForm: this.emptyMembershipForm(),
         ptForm: this.emptyPtForm(),
         deleteTarget: null,
      };
   },
   computed: {
      currentBusinessName: function () {
         return window.JPrime?.profile?.name || "your business";
      },
      statCards: function () {
         return [
            { label: "Configured Membership Rates", value: this.pricing.stats.membership_configured, icon: "bi-card-checklist", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Active Membership Rates", value: this.pricing.stats.membership_active, icon: "bi-check-circle-fill", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Configured PT Rates", value: this.pricing.stats.pt_configured, icon: "bi-person-badge-fill", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Active PT Rates", value: this.pricing.stats.pt_active, icon: "bi-graph-up-arrow", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },
   mounted: function () {
      this.membershipRateModal = new Modal(this.$refs.membershipRateModal);
      this.ptRateModal = new Modal(this.$refs.ptRateModal);
      this.pricingDeleteModal = new Modal(this.$refs.pricingDeleteModal);
      this.fetchPricing();
   },
   methods: {
      formatDate,
      emptyMembershipForm: function () {
         return {
            id: "",
            name: "",
            price: "",
            manager_commission_rate: "0.00",
            is_active: true,
            effective_from: "",
            effective_until: "",
         };
      },
      emptyPtForm: function () {
         return {
            id: "",
            name: "",
            price: "",
            coach_commission_rate: "40.00",
            is_active: true,
            effective_from: "",
            effective_until: "",
         };
      },
      fetchPricing: function () {
         this.loading = true;

         axios
            .get("/panel/pricing/data")
            .then((response) => {
               this.pricing = response.data;
            })
            .finally(() => {
               this.loading = false;
            });
      },
      membershipDurationLabel: function (durationDays) {
         if (!durationDays || durationDays <= 0) {
            return "Flexible";
         }

         if (durationDays % 30 === 0) {
            const months = durationDays / 30;

            return `${months} month${months !== 1 ? "s" : ""}`;
         }

         return `${durationDays} days`;
      },
      openCreateMembershipModal: function () {
         this.membershipModalMode = "create";
         this.membershipFormError = "";
         this.membershipFormErrors = {};
         this.membershipForm = this.emptyMembershipForm();
         this.membershipRateModal.show();
      },
      openEditMembershipModal: function (rate) {
         this.membershipModalMode = "edit";
         this.membershipFormError = "";
         this.membershipFormErrors = {};
         this.membershipForm = {
            id: rate.id,
            name: rate.name,
            price: rate.price,
            manager_commission_rate: rate.manager_commission_rate,
            is_active: !!rate.is_active,
            effective_from: rate.effective_from || "",
            effective_until: rate.effective_until || "",
         };
         this.membershipRateModal.show();
      },
      openCreatePtModal: function () {
         this.ptModalMode = "create";
         this.ptFormError = "";
         this.ptFormErrors = {};
         this.ptForm = this.emptyPtForm();
         this.ptRateModal.show();
      },
      openEditPtModal: function (rate) {
         this.ptModalMode = "edit";
         this.ptFormError = "";
         this.ptFormErrors = {};
         this.ptForm = {
            id: rate.id,
            name: rate.name,
            price: rate.price,
            coach_commission_rate: rate.coach_commission_rate,
            is_active: !!rate.is_active,
            effective_from: rate.effective_from || "",
            effective_until: rate.effective_until || "",
         };
         this.ptRateModal.show();
      },
      submitMembershipRate: function () {
         if (this.membershipModalMode === "create" && !this.membershipForm.id) {
            this.membershipFormErrors = { rate_plan_id: "Please select a rate plan." };
            return;
         }

         this.submittingMembership = true;
         this.membershipFormError = "";
         this.membershipFormErrors = {};

         const payload = {
            price: this.membershipForm.price,
            manager_commission_rate: this.membershipForm.manager_commission_rate,
            is_active: this.membershipForm.is_active,
            effective_from: this.membershipForm.effective_from || null,
            effective_until: this.membershipForm.effective_until || null,
         };

         const request = this.membershipModalMode === "create"
            ? axios.post(`/panel/pricing/rate-plans/${this.membershipForm.id}`, payload)
            : axios.put(`/panel/pricing/rate-plans/${this.membershipForm.id}`, payload);

         request
            .then(() => {
               this.membershipRateModal.hide();
               this.fetchPricing();
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  this.membershipFormErrors = this.normalizeErrors(error.response.data.errors || {});
                  return;
               }

               this.membershipFormError = "Something went wrong. Please try again.";
            })
            .finally(() => {
               this.submittingMembership = false;
            });
      },
      submitPtRate: function () {
         if (this.ptModalMode === "create" && !this.ptForm.id) {
            this.ptFormErrors = { pt_product_id: "Please select a PT package." };
            return;
         }

         this.submittingPt = true;
         this.ptFormError = "";
         this.ptFormErrors = {};

         const payload = {
            price: this.ptForm.price,
            coach_commission_rate: this.ptForm.coach_commission_rate,
            is_active: this.ptForm.is_active,
            effective_from: this.ptForm.effective_from || null,
            effective_until: this.ptForm.effective_until || null,
         };

         const request = this.ptModalMode === "create"
            ? axios.post(`/panel/pricing/pt-products/${this.ptForm.id}`, payload)
            : axios.put(`/panel/pricing/pt-products/${this.ptForm.id}`, payload);

         request
            .then(() => {
               this.ptRateModal.hide();
               this.fetchPricing();
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  this.ptFormErrors = this.normalizeErrors(error.response.data.errors || {});
                  return;
               }

               this.ptFormError = "Something went wrong. Please try again.";
            })
            .finally(() => {
               this.submittingPt = false;
            });
      },
      confirmDelete: function (type, rate) {
         this.deleteTarget = { id: rate.id, name: rate.name, type: type };
         this.pricingDeleteModal.show();
      },
      deletePricing: function () {
         if (!this.deleteTarget) {
            return;
         }

         this.deleting = true;

         const url = this.deleteTarget.type === "membership"
            ? `/panel/pricing/rate-plans/${this.deleteTarget.id}`
            : `/panel/pricing/pt-products/${this.deleteTarget.id}`;

         axios
            .delete(url)
            .then(() => {
               this.pricingDeleteModal.hide();
               this.deleteTarget = null;
               this.fetchPricing();
            })
            .finally(() => {
               this.deleting = false;
            });
      },
      normalizeErrors: function (errors) {
         return Object.fromEntries(
            Object.entries(errors).map((entry) => [entry[0], Array.isArray(entry[1]) ? entry[1][0] : entry[1]]),
         );
      },
   },
};
</script>
