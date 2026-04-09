<template>
   <div class="p-3">
      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>PT sessions updated successfully.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-3 mb-4">
         <div class="col-md-4" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="stat.iconBg"><i class="bi" :class="[stat.icon, stat.iconColor]"></i></div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value">{{ stat.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="d-flex flex-wrap justify-content-end gap-2 mb-4" v-if="canAllocatePackages || canLogUsage">
         <button v-if="canAllocatePackages" class="btn btn-danger btn-sm" @click="openPackageModal"><i class="bi bi-plus-circle me-1"></i>Add PT Package</button>
         <button v-if="canLogUsage" class="btn btn-outline-success btn-sm" @click="openUsageModal" :disabled="activePackages.length === 0"><i class="bi bi-check2-square me-1"></i>Log PT Session Use</button>
      </div>

      <div v-if="packages.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-stopwatch fs-1 d-block mb-2 opacity-25"></i>
         <div>No PT session packages found.</div>
      </div>

      <template v-else>
         <div class="d-none d-md-block mb-4">
            <table class="table table-striped table-hover align-middle mb-0">
               <thead class="table-light">
                  <tr>
                     <th>Product</th>
                     <th>Coach</th>
                     <th>Balance</th>
                     <th>Commission</th>
                     <th>Status</th>
                     <th>Assigned</th>
                     <th>Created By</th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="pkg in packages" :key="pkg.id">
                     <td>
                        <div class="fw-semibold">{{ pkg.pt_product?.name || "-" }}</div>
                        <div class="small text-muted" v-if="pkg.notes">{{ pkg.notes }}</div>
                     </td>
                     <td class="small">{{ pkg.coach?.name || "-" }}</td>
                     <td class="small">
                        <span class="fw-semibold">{{ pkg.remaining_sessions }}</span>
                        <span class="text-muted"> / {{ pkg.total_sessions }}</span>
                     </td>
                     <td class="small">
                        <div class="fw-semibold">₱{{ $filters.formatMoney(pkg.coach_commission_amount || 0) }}</div>
                        <div class="text-muted small">{{ commissionLabel(pkg) }}</div>
                     </td>
                     <td>
                        <span class="m-badge" :class="packageStatusClass(pkg.status)">{{ $filters.capitalize(pkg.status) }}</span>
                     </td>
                     <td class="small">
                        <div>{{ $filters.formatDate(pkg.assigned_at) }}</div>
                        <div class="text-muted" v-if="pkg.expires_at">Expires {{ $filters.formatDate(pkg.expires_at) }}</div>
                     </td>
                     <td class="small text-muted">{{ pkg.created_by?.name || "-" }}</td>
                  </tr>
               </tbody>
            </table>
         </div>

         <div class="d-md-none mb-4">
            <div class="member-card" v-for="pkg in packages" :key="'pt-' + pkg.id">
               <div class="member-card-top">
                  <div>
                     <div class="fw-semibold">{{ pkg.pt_product?.name || "-" }}</div>
                     <div class="text-muted small" v-if="pkg.coach?.name">Coach: {{ pkg.coach.name }}</div>
                     <div class="text-muted small">Commission: ₱{{ $filters.formatMoney(pkg.coach_commission_amount || 0) }} · {{ commissionLabel(pkg) }}</div>
                  </div>
                  <span class="m-badge" :class="packageStatusClass(pkg.status)">{{ $filters.capitalize(pkg.status) }}</span>
               </div>
               <div class="member-card-footer">
                  <span>{{ pkg.remaining_sessions }}/{{ pkg.total_sessions }} left</span>
                  <span class="text-muted small">{{ $filters.formatDate(pkg.assigned_at) }}</span>
               </div>
            </div>
         </div>

         <div>
            <div class="fw-semibold mb-3">Usage History</div>
            <div v-if="usageEntries.length === 0" class="text-center py-4 text-muted border rounded">
               <div>No PT session usage logged yet.</div>
            </div>
            <div v-else class="d-none d-md-block">
               <table class="table table-striped table-hover align-middle mb-0">
                  <thead class="table-light">
                     <tr>
                        <th>Used At</th>
                        <th>Product</th>
                        <th>Sessions</th>
                        <th>Coach</th>
                        <th>Confirmed By</th>
                        <th>Recorded By</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="usage in usageEntries" :key="usage.id">
                        <td class="small">{{ $filters.formatDateTime(usage.used_at) }}</td>
                        <td>
                           <div class="fw-semibold">{{ usage.package.pt_product?.name || "-" }}</div>
                           <div class="small text-muted" v-if="usage.notes">{{ usage.notes }}</div>
                        </td>
                        <td class="small">{{ usage.sessions_used }}</td>
                        <td class="small">{{ usage.coach?.name || usage.package.coach?.name || "-" }}</td>
                        <td class="small">{{ usage.confirmed_by || "-" }}</td>
                        <td class="small text-muted">{{ usage.recorded_by?.name || "-" }}</td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="usage in usageEntries" :key="'usage-' + usage.id">
                  <div class="member-card-top">
                     <div>
                        <div class="fw-semibold">{{ usage.package.pt_product?.name || "-" }}</div>
                        <div class="text-muted small">{{ $filters.formatDateTime(usage.used_at) }}</div>
                     </div>
                     <span class="m-badge m-badge--plan-active">{{ usage.sessions_used }} used</span>
                  </div>
                  <div class="member-card-footer">
                     <span>{{ usage.coach?.name || usage.package.coach?.name || usage.confirmed_by || usage.recorded_by?.name || "-" }}</span>
                  </div>
               </div>
            </div>
         </div>
      </template>

      <div class="modal fade" tabindex="-1" ref="packageModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Add PT Package</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">PT Product</label>
                        <select class="form-select" v-model="packageForm.pt_product_id" :class="{ 'is-invalid': packageErrors.pt_product_id }">
                           <option disabled value="">Select a product...</option>
                           <option v-for="product in availableProducts" :key="product.id" :value="product.id">{{ product.name }} ({{ product.session_count }} sessions)</option>
                        </select>
                        <div class="invalid-feedback" v-if="packageErrors.pt_product_id">{{ packageErrors.pt_product_id }}</div>
                        <div class="form-text" v-if="selectedPackageProduct">
                           Price: ₱{{ $filters.formatMoney(selectedPackageProduct.pivot?.price || 0) }} · Coach commission: ₱{{ $filters.formatMoney(packageCommissionPreview) }}
                           <span class="text-muted">({{ Number(selectedPackageProduct.pivot?.coach_commission_rate || 0) }}%)</span>
                        </div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Coach</label>
                        <select class="form-select" v-model="packageForm.coach_id" :class="{ 'is-invalid': packageErrors.coach_id }">
                           <option value="">Assign later...</option>
                           <option v-for="coach in availablePackageCoaches" :key="coach.id" :value="coach.id">{{ coach.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="packageErrors.coach_id">{{ packageErrors.coach_id }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Assigned Date</label>
                        <input type="date" class="form-control" v-model="packageForm.assigned_at" :class="{ 'is-invalid': packageErrors.assigned_at }" />
                        <div class="invalid-feedback" v-if="packageErrors.assigned_at">{{ packageErrors.assigned_at }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Expires At</label>
                        <input type="date" class="form-control" v-model="packageForm.expires_at" :class="{ 'is-invalid': packageErrors.expires_at }" />
                        <div class="invalid-feedback" v-if="packageErrors.expires_at">{{ packageErrors.expires_at }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Notes</label>
                        <textarea class="form-control" rows="2" v-model="packageForm.notes" :class="{ 'is-invalid': packageErrors.notes }" placeholder="Optional sales or package notes"></textarea>
                        <div class="invalid-feedback" v-if="packageErrors.notes">{{ packageErrors.notes }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" @click="submitPackage" :disabled="savingPackage || !packageForm.pt_product_id">
                     <span class="spinner-border spinner-border-sm me-1" v-if="savingPackage"></span>
                     Add Package
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="usageModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Log PT Session Use</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="row g-3">
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Active Package</label>
                        <select class="form-select" v-model="usageForm.member_pt_package_id" :class="{ 'is-invalid': usageErrors.member_pt_package_id }">
                           <option disabled value="">Select an active package...</option>
                           <option v-for="pkg in activePackages" :key="pkg.id" :value="pkg.id">{{ pkg.pt_product?.name }} • {{ pkg.remaining_sessions }}/{{ pkg.total_sessions }} left</option>
                        </select>
                        <div class="invalid-feedback" v-if="usageErrors.member_pt_package_id">{{ usageErrors.member_pt_package_id }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm fw-semibold">Sessions Used</label>
                        <input type="number" min="1" class="form-control" v-model.number="usageForm.sessions_used" :class="{ 'is-invalid': usageErrors.sessions_used }" />
                        <div class="invalid-feedback" v-if="usageErrors.sessions_used">{{ usageErrors.sessions_used }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm fw-semibold">Coach</label>
                        <select class="form-select" v-model="usageForm.coach_id" :class="{ 'is-invalid': usageErrors.coach_id }">
                           <option disabled value="">Use package coach...</option>
                           <option v-for="coach in availableUsageCoaches" :key="coach.id" :value="coach.id">{{ coach.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="usageErrors.coach_id">{{ usageErrors.coach_id }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm fw-semibold">Used At</label>
                        <input type="datetime-local" class="form-control" v-model="usageForm.used_at" :class="{ 'is-invalid': usageErrors.used_at }" />
                        <div class="invalid-feedback" v-if="usageErrors.used_at">{{ usageErrors.used_at }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Confirmed By</label>
                        <input type="text" class="form-control" v-model="usageForm.confirmed_by" :class="{ 'is-invalid': usageErrors.confirmed_by }" placeholder="Member signature or confirmation" />
                        <div class="invalid-feedback" v-if="usageErrors.confirmed_by">{{ usageErrors.confirmed_by }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Notes</label>
                        <input type="text" class="form-control" v-model="usageForm.notes" :class="{ 'is-invalid': usageErrors.notes }" placeholder="Optional trainer/session note" />
                        <div class="invalid-feedback" v-if="usageErrors.notes">{{ usageErrors.notes }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-outline-success btn-sm" @click="submitUsage" :disabled="savingUsage || !usageForm.member_pt_package_id">
                     <span class="spinner-border spinner-border-sm me-1" v-if="savingUsage"></span>
                     Use Session
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
      member: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         savingPackage: false,
         savingUsage: false,
        saved: false,
        generalError: "",
        packageErrors: {},
        usageErrors: {},
        packageForm: {
            pt_product_id: "",
            coach_id: "",
            assigned_at: new Date().toISOString().slice(0, 10),
            expires_at: "",
            notes: "",
         },
         usageForm: {
            member_pt_package_id: "",
            coach_id: "",
            sessions_used: 1,
            used_at: new Date().toISOString().slice(0, 16),
            confirmed_by: "",
            notes: "",
         },
         packageModalInst: null,
         usageModalInst: null,
      };
   },

   mounted: function () {
      this.packageModalInst = new Modal(this.$refs.packageModal);
      this.usageModalInst = new Modal(this.$refs.usageModal);
   },

   watch: {
      member: {
         immediate: true,
         handler: function () {
            this.resetPackageForm();
            this.resetUsageForm();
         },
      },
      "packageForm.pt_product_id"() {
         if (!this.availablePackageCoaches.some((coach) => coach.id === this.packageForm.coach_id)) {
            this.packageForm.coach_id = "";
         }
      },
      "usageForm.member_pt_package_id"() {
         if (this.selectedUsagePackage?.coach_id) {
            this.usageForm.coach_id = this.selectedUsagePackage.coach_id;
            return;
         }

         if (!this.availableUsageCoaches.some((coach) => coach.id === this.usageForm.coach_id)) {
            this.usageForm.coach_id = "";
         }
      },
   },

   computed: {
      availableProducts: function () {
         return this.member.location?.pt_products || window.JPrime?.profile?.pt_products || [];
      },

      availableCoaches: function () {
         return this.member.available_coaches || [];
      },

      availablePackageCoaches: function () {
         return this.availableCoaches;
      },

      selectedPackageProduct: function () {
         return this.availableProducts.find((product) => product.id === this.packageForm.pt_product_id) || null;
      },

      packageCommissionPreview: function () {
         const price = Number(this.selectedPackageProduct?.pivot?.price || 0);
         const rate = Number(this.selectedPackageProduct?.pivot?.coach_commission_rate || 0);

         return Math.max(0, (price * rate) / 100);
      },

      packages: function () {
         return [...(this.member.member_pt_packages || [])].sort((left, right) => {
            const leftDate = left.assigned_at || left.created_at || "";
            const rightDate = right.assigned_at || right.created_at || "";
            return String(rightDate).localeCompare(String(leftDate));
         });
      },

      activePackages: function () {
         return this.packages.filter((pkg) => pkg.status === "active" && Number(pkg.remaining_sessions) > 0);
      },

      selectedUsagePackage: function () {
         return this.activePackages.find((pkg) => pkg.id === this.usageForm.member_pt_package_id) || null;
      },

      availableUsageCoaches: function () {
         return this.availableCoaches;
      },

      usageEntries: function () {
         return this.packages.flatMap((pkg) => (pkg.usages || []).map((usage) => ({ ...usage, package: pkg }))).sort((left, right) => String(right.used_at || right.created_at || "").localeCompare(String(left.used_at || left.created_at || "")));
      },

      statCards: function () {
         return [
            { label: "Active Packages", value: this.activePackages.length, icon: "bi-box-seam", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            {
               label: "Remaining Sessions",
               value: this.packages.reduce((total, pkg) => total + Number(pkg.remaining_sessions || 0), 0),
               icon: "bi-lightning-charge",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
            {
               label: "Sessions Used",
               value: this.usageEntries.reduce((total, usage) => total + Number(usage.sessions_used || 0), 0),
               icon: "bi-activity",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
         ];
      },

      canAllocatePackages: function () {
         return this.is("super admin") || this.is("admin") || this.is("manager");
      },

      canLogUsage: function () {
         return this.canAllocatePackages || this.is("staff");
      },
   },

   methods: {
      resetPackageForm: function () {
         this.packageForm = {
            pt_product_id: "",
            coach_id: "",
            assigned_at: new Date().toISOString().slice(0, 10),
            expires_at: "",
            notes: "",
         };

         const firstProduct = this.availableProducts[0];
         this.packageForm.pt_product_id = firstProduct ? firstProduct.id : "";
      },

      resetUsageForm: function () {
         this.usageForm = {
            member_pt_package_id: this.activePackages[0]?.id || "",
            coach_id: this.activePackages[0]?.coach_id || "",
            sessions_used: 1,
            used_at: new Date().toISOString().slice(0, 16),
            confirmed_by: this.member.name || "",
            notes: "",
         };
      },

      openPackageModal: function () {
         this.generalError = "";
         this.packageErrors = {};
         this.resetPackageForm();
         this.packageModalInst.show();
      },

      openUsageModal: function () {
         this.generalError = "";
         this.usageErrors = {};
         this.resetUsageForm();
         this.usageModalInst.show();
      },

      normalizeErrors: function (errors) {
         return Object.fromEntries(Object.entries(errors || {}).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]));
      },

      submitPackage: function () {
         this.savingPackage = true;
         this.saved = false;
         this.generalError = "";
         this.packageErrors = {};

         axios
            .post(`/panel/members/${this.member.id}/pt-packages`, this.packageForm)
            .then((res) => {
               this.saved = true;
               this.$emit("updated", res.data);
               this.packageModalInst.hide();
               this.resetPackageForm();
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.packageErrors = this.normalizeErrors(err.response.data.errors);
               } else {
                  this.generalError = err.response?.data?.message || "Failed to add PT package.";
               }
            })
            .finally(() => (this.savingPackage = false));
      },

      submitUsage: function () {
         this.savingUsage = true;
         this.saved = false;
         this.generalError = "";
         this.usageErrors = {};

         axios
            .post(`/panel/members/${this.member.id}/pt-session-usages`, this.usageForm)
            .then((res) => {
               this.saved = true;
               this.$emit("updated", res.data);
               this.usageModalInst.hide();
               this.resetUsageForm();
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.usageErrors = this.normalizeErrors(err.response.data.errors);
               } else {
                  this.generalError = err.response?.data?.message || "Failed to log PT session usage.";
               }
            })
            .finally(() => (this.savingUsage = false));
      },

      packageStatusClass: function (status) {
         return (
            {
               active: "m-badge--plan-active",
               consumed: "m-badge--plan-expired",
               cancelled: "m-badge--plan-cancelled",
            }[status] || "m-badge--plan-expired"
         );
      },

      commissionLabel: function (pkg) {
         const labels = {
            unassigned: "Awaiting coach assignment",
            pending: "Earns after all sessions are used",
            earned: "Ready for payroll payout",
            paid: "Already paid out",
         };

         return labels[pkg.coach_commission_status] || "Not tracked";
      },
   },
};
</script>
