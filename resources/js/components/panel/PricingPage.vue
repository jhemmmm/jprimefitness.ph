<template>
   <div class="pricing-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Pricing & Rates</h4>
            <p class="text-muted small mb-0">Manage branch-specific membership pricing and PT package rates for {{ currentBranchName }}</p>
         </div>
      </div>

      <div v-if="!branchesData.length" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-tag-fill fs-1 d-block mb-2 opacity-25"></i>
         <div>No accessible branches found.</div>
      </div>

      <div v-else-if="!selectedBranch" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-25"></i>
         <div class="fw-semibold mb-1">Select a branch</div>
         <div class="small">Choose a specific branch from the sidebar to manage pricing and rates.</div>
      </div>

      <template v-else>
         <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-primary-soft">
                     <i class="bi bi-card-checklist text-primary"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Configured Membership Rates</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 52px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>{{ pricing.stats.membership_configured }}</div>
                  </div>
               </div>
            </div>
            <div class="col-6 col-lg-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-success-soft">
                     <i class="bi bi-check-circle-fill text-success"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Active Membership Rates</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 52px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>{{ pricing.stats.membership_active }}</div>
                  </div>
               </div>
            </div>
            <div class="col-6 col-lg-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-warning-soft">
                     <i class="bi bi-person-badge-fill text-warning"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Configured PT Rates</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 52px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>{{ pricing.stats.pt_configured }}</div>
                  </div>
               </div>
            </div>
            <div class="col-6 col-lg-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-danger-soft">
                     <i class="bi bi-graph-up-arrow text-danger"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Active PT Rates</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 52px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>{{ pricing.stats.pt_active }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="panel-card mb-4">
            <div class="panel-card-header d-flex justify-content-between align-items-center">
               <span class="panel-card-title">Membership Rates</span>
               <button class="btn btn-danger btn-sm px-3" v-if="canManagePricing" @click="openCreateMembershipModal" :disabled="pricing.available_membership_rate_plans.length === 0">
                  <i class="bi bi-plus-lg me-1"></i>
                  Add Membership Rate
               </button>
            </div>

            <div v-if="loading" class="table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Branch Price</th>
                        <th>Status</th>
                        <th>Effectivity</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 4" :key="'membership-sk-' + i">
                        <td>
                           <div class="skeleton-box mb-1" style="width: 140px; height: 14px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 100px; height: 11px; border-radius: 4px"></div>
                        </td>
                        <td><div class="skeleton-box" style="width: 88px; height: 12px; border-radius: 4px"></div></td>
                        <td><div class="skeleton-box" style="width: 74px; height: 12px; border-radius: 4px"></div></td>
                        <td><div class="skeleton-box" style="width: 84px; height: 22px; border-radius: 999px"></div></td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 96px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 96px; height: 12px; border-radius: 4px"></div>
                        </td>
                        <td><div class="skeleton-box ms-auto" style="width: 70px; height: 32px; border-radius: 6px"></div></td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div v-else-if="pricing.membership_rates.length === 0" class="text-center py-5 text-muted">
               <i class="bi bi-card-checklist empty-icon"></i>
               <p class="mt-2 mb-1">No membership rates configured for this branch.</p>
            </div>

            <div v-else class="table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Branch Price</th>
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
                        <td class="fw-semibold">₱{{ $filters.formatMoney(rate.branch_price) }}</td>
                        <td>
                           <span :class="['m-badge', rate.branch_is_active ? 'm-badge--active' : 'm-badge--inactive']">
                              {{ rate.branch_is_active ? "Active" : "Inactive" }}
                           </span>
                        </td>
                        <td class="small text-muted">
                           <div>From: {{ $filters.formatDate(rate.effective_from) }}</div>
                           <div>Until: {{ $filters.formatDate(rate.effective_until) }}</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end" v-if="canManagePricing">
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditMembershipModal(rate)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete('membership', rate)">
                                 <i class="bi bi-trash tbl-icon"></i>
                              </button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>

         <div class="panel-card">
            <div class="panel-card-header d-flex justify-content-between align-items-center">
               <span class="panel-card-title">PT Rates</span>
               <button class="btn btn-danger btn-sm px-3" v-if="canManagePricing" @click="openCreatePtModal" :disabled="pricing.available_pt_products.length === 0">
                  <i class="bi bi-plus-lg me-1"></i>
                  Add PT Rate
               </button>
            </div>

            <div v-if="loading" class="table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Package</th>
                        <th>Sessions</th>
                        <th>Branch Price</th>
                        <th>Coach Commission</th>
                        <th>Status</th>
                        <th>Effectivity</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 4" :key="'pt-sk-' + i">
                        <td>
                           <div class="skeleton-box mb-1" style="width: 126px; height: 14px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 92px; height: 11px; border-radius: 4px"></div>
                        </td>
                        <td><div class="skeleton-box" style="width: 58px; height: 12px; border-radius: 4px"></div></td>
                        <td><div class="skeleton-box" style="width: 74px; height: 12px; border-radius: 4px"></div></td>
                        <td><div class="skeleton-box" style="width: 70px; height: 12px; border-radius: 4px"></div></td>
                        <td><div class="skeleton-box" style="width: 84px; height: 22px; border-radius: 999px"></div></td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 96px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 96px; height: 12px; border-radius: 4px"></div>
                        </td>
                        <td><div class="skeleton-box ms-auto" style="width: 70px; height: 32px; border-radius: 6px"></div></td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div v-else-if="pricing.pt_rates.length === 0" class="text-center py-5 text-muted">
               <i class="bi bi-person-badge-fill empty-icon"></i>
               <p class="mt-2 mb-1">No PT rates configured for this branch.</p>
            </div>

            <div v-else class="table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Package</th>
                        <th>Sessions</th>
                        <th>Branch Price</th>
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
                        <td class="fw-semibold">₱{{ $filters.formatMoney(rate.branch_price) }}</td>
                        <td class="small">{{ $filters.formatMoney(rate.coach_commission_rate) }}%</td>
                        <td>
                           <span :class="['m-badge', rate.branch_is_active ? 'm-badge--active' : 'm-badge--inactive']">
                              {{ rate.branch_is_active ? "Active" : "Inactive" }}
                           </span>
                        </td>
                        <td class="small text-muted">
                           <div>From: {{ $filters.formatDate(rate.effective_from) }}</div>
                           <div>Until: {{ $filters.formatDate(rate.effective_until) }}</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end" v-if="canManagePricing">
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditPtModal(rate)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete('pt', rate)">
                                 <i class="bi bi-trash tbl-icon"></i>
                              </button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>
      </template>

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
                  <div class="mb-3">
                     <label class="form-label form-label-sm">Branch Price <span class="text-danger">*</span></label>
                     <input type="number" min="0" step="0.01" class="form-control" v-model="membershipForm.price" :class="{ 'is-invalid': membershipFormErrors.price }" />
                     <div class="invalid-feedback" v-if="membershipFormErrors.price">{{ membershipFormErrors.price }}</div>
                  </div>
                  <div class="row g-3">
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
                     <label class="form-check-label" for="membershipRateActive">Active for this branch</label>
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
                        <label class="form-label form-label-sm">Branch Price <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="ptForm.price" :class="{ 'is-invalid': ptFormErrors.price }" />
                        <div class="invalid-feedback" v-if="ptFormErrors.price">{{ ptFormErrors.price }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Coach Commission Rate <span class="text-danger">*</span></label>
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
                     <label class="form-check-label" for="ptRateActive">Active for this branch</label>
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
                  <p class="mb-1">Are you sure you want to delete this pricing entry?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.name }}</p>
                  <p class="text-muted small mb-0">{{ deleteTarget.type === "membership" ? "Membership rate" : "PT rate" }}</p>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="deletePricing" :disabled="deleting">
                     <span v-if="deleting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     Delete
                  </button>
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
      branchesData: {
         type: Array,
         default: function () {
            return [];
         },
      },
      canManagePricing: {
         type: Boolean,
         default: false,
      },
   },
   data: function () {
      var storedBranchId = localStorage.getItem("selectedBranch");

      return {
         loading: true,
         selectedBranch: storedBranchId && storedBranchId !== "null" ? parseInt(storedBranchId, 10) || "" : "",
         pricing: {
            branch: null,
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
      currentBranchName: function () {
         return this.pricing.branch?.name || this.branchesData.find((branch) => branch.id === this.selectedBranch)?.name || "the selected branch";
      },
   },
   mounted: function () {
      if (this.selectedBranch && !this.branchesData.some((branch) => branch.id === this.selectedBranch)) {
         this.selectedBranch = "";
      }

      this.membershipRateModal = new Modal(this.$refs.membershipRateModal);
      this.ptRateModal = new Modal(this.$refs.ptRateModal);
      this.pricingDeleteModal = new Modal(this.$refs.pricingDeleteModal);

      if (this.selectedBranch) {
         this.fetchPricing();
      } else {
         this.loading = false;
      }
   },
   methods: {
      emptyMembershipForm: function () {
         return {
            id: "",
            name: "",
            price: "",
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
         if (!this.selectedBranch) {
            return;
         }

         this.loading = true;

         axios
            .get(`/panel/pricing/branches/${this.selectedBranch}`)
            .then((response) => {
               this.pricing = response.data;
               this.loading = false;
            })
            .catch(() => {
               this.loading = false;
            });
      },
      membershipDurationLabel: function (durationDays) {
         if (!durationDays || durationDays <= 0) {
            return "Flexible";
         }

         if (durationDays % 30 === 0) {
            var months = durationDays / 30;

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
            price: rate.branch_price,
            is_active: !!rate.branch_is_active,
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
            price: rate.branch_price,
            coach_commission_rate: rate.coach_commission_rate,
            is_active: !!rate.branch_is_active,
            effective_from: rate.effective_from || "",
            effective_until: rate.effective_until || "",
         };
         this.ptRateModal.show();
      },
      submitMembershipRate: function () {
         if (this.membershipModalMode === "create" && !this.membershipForm.id) {
            this.membershipFormErrors = {
               rate_plan_id: "Please select a rate plan.",
            };

            return;
         }

         this.submittingMembership = true;
         this.membershipFormError = "";
         this.membershipFormErrors = {};

         var payload = {
            price: this.membershipForm.price,
            is_active: this.membershipForm.is_active,
            effective_from: this.membershipForm.effective_from || null,
            effective_until: this.membershipForm.effective_until || null,
         };

         var request =
            this.membershipModalMode === "create"
               ? axios.post(`/panel/pricing/branches/${this.selectedBranch}/rate-plans/${this.membershipForm.id}`, payload)
               : axios.put(`/panel/pricing/branches/${this.selectedBranch}/rate-plans/${this.membershipForm.id}`, payload);

         request
            .then(() => {
               this.membershipRateModal.hide();
               this.fetchPricing();
            })
            .catch((error) => {
               if (error.response && error.response.status === 422) {
                  this.membershipFormErrors = this.normalizeErrors(error.response.data.errors || {});
               } else {
                  this.membershipFormError = "Something went wrong. Please try again.";
               }
            })
            .finally(() => {
               this.submittingMembership = false;
            });
      },
      submitPtRate: function () {
         if (this.ptModalMode === "create" && !this.ptForm.id) {
            this.ptFormErrors = {
               pt_product_id: "Please select a PT package.",
            };

            return;
         }

         this.submittingPt = true;
         this.ptFormError = "";
         this.ptFormErrors = {};

         var payload = {
            price: this.ptForm.price,
            coach_commission_rate: this.ptForm.coach_commission_rate,
            is_active: this.ptForm.is_active,
            effective_from: this.ptForm.effective_from || null,
            effective_until: this.ptForm.effective_until || null,
         };

         var request =
            this.ptModalMode === "create"
               ? axios.post(`/panel/pricing/branches/${this.selectedBranch}/pt-products/${this.ptForm.id}`, payload)
               : axios.put(`/panel/pricing/branches/${this.selectedBranch}/pt-products/${this.ptForm.id}`, payload);

         request
            .then(() => {
               this.ptRateModal.hide();
               this.fetchPricing();
            })
            .catch((error) => {
               if (error.response && error.response.status === 422) {
                  this.ptFormErrors = this.normalizeErrors(error.response.data.errors || {});
               } else {
                  this.ptFormError = "Something went wrong. Please try again.";
               }
            })
            .finally(() => {
               this.submittingPt = false;
            });
      },
      confirmDelete: function (type, rate) {
         this.deleteTarget = {
            id: rate.id,
            name: rate.name,
            type: type,
         };
         this.pricingDeleteModal.show();
      },
      deletePricing: function () {
         if (!this.deleteTarget) {
            return;
         }

         this.deleting = true;

         var url =
            this.deleteTarget.type === "membership"
               ? `/panel/pricing/branches/${this.selectedBranch}/rate-plans/${this.deleteTarget.id}`
               : `/panel/pricing/branches/${this.selectedBranch}/pt-products/${this.deleteTarget.id}`;

         axios
            .delete(url)
            .then(() => {
               this.pricingDeleteModal.hide();
               this.deleteTarget = null;
               this.fetchPricing();
            })
            .catch(() => {})
            .finally(() => {
               this.deleting = false;
            });
      },
      normalizeErrors: function (errors) {
         return Object.fromEntries(
            Object.entries(errors).map(function (entry) {
               return [entry[0], Array.isArray(entry[1]) ? entry[1][0] : entry[1]];
            }),
         );
      },
   },
};
</script>
