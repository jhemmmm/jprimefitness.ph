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
               <button v-if="canAllocatePackages" class="btn btn-danger btn-sm" @click="openPackageModal">
                  <i class="bi bi-plus-circle me-1"></i>Add PT Package
               </button>
               <button v-if="canLogUsage" class="btn btn-outline-success btn-sm" @click="openUsageModal" :disabled="activePackages.length === 0">
                  <i class="bi bi-check2-square me-1"></i>Log PT Session Use
               </button>
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
                     <th>Branch</th>
                     <th>Balance</th>
                     <th>Status</th>
                     <th>Assigned</th>
                     <th>Created By</th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="pkg in packages" :key="pkg.id">
                     <td>
                        <div class="fw-semibold">{{ pkg.pt_product?.name || "—" }}</div>
                        <div class="small text-muted" v-if="pkg.notes">{{ pkg.notes }}</div>
                     </td>
                     <td class="small">{{ pkg.branch?.name || "—" }}</td>
                     <td class="small">
                        <span class="fw-semibold">{{ pkg.remaining_sessions }}</span>
                        <span class="text-muted"> / {{ pkg.total_sessions }}</span>
                     </td>
                     <td><span class="m-badge" :class="packageStatusClass(pkg.status)">{{ $filters.capitalize(pkg.status) }}</span></td>
                     <td class="small">
                        <div>{{ $filters.formatDate(pkg.assigned_at) }}</div>
                        <div class="text-muted" v-if="pkg.expires_at">Expires {{ $filters.formatDate(pkg.expires_at) }}</div>
                     </td>
                     <td class="small text-muted">{{ pkg.created_by?.name || "—" }}</td>
                  </tr>
               </tbody>
            </table>
         </div>

         <div class="d-md-none mb-4">
            <div class="member-card" v-for="pkg in packages" :key="'pt-' + pkg.id">
               <div class="member-card-top">
                  <div>
                     <div class="fw-semibold">{{ pkg.pt_product?.name || "—" }}</div>
                     <div class="text-muted small">{{ pkg.branch?.name || "—" }}</div>
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
                        <th>Branch</th>
                        <th>Sessions</th>
                        <th>Confirmed By</th>
                        <th>Recorded By</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="usage in usageEntries" :key="usage.id">
                        <td class="small">{{ $filters.formatDateTime(usage.used_at) }}</td>
                        <td>
                           <div class="fw-semibold">{{ usage.package.pt_product?.name || "—" }}</div>
                           <div class="small text-muted" v-if="usage.notes">{{ usage.notes }}</div>
                        </td>
                        <td class="small">{{ usage.package.branch?.name || "—" }}</td>
                        <td class="small">{{ usage.sessions_used }}</td>
                        <td class="small">{{ usage.confirmed_by || "—" }}</td>
                        <td class="small text-muted">{{ usage.recorded_by?.name || "—" }}</td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="usage in usageEntries" :key="'usage-' + usage.id">
                  <div class="member-card-top">
                     <div>
                        <div class="fw-semibold">{{ usage.package.pt_product?.name || "—" }}</div>
                        <div class="text-muted small">{{ $filters.formatDateTime(usage.used_at) }}</div>
                     </div>
                     <span class="m-badge m-badge--plan-active">{{ usage.sessions_used }} used</span>
                  </div>
                  <div class="member-card-footer">
                     <span>{{ usage.package.branch?.name || "—" }}</span>
                     <span class="text-muted small">{{ usage.confirmed_by || usage.recorded_by?.name || "—" }}</span>
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
                        <label class="form-label form-label-sm fw-semibold">Branch</label>
                        <select class="form-select" v-model="packageForm.branch_id" :class="{ 'is-invalid': packageErrors.branch_id }">
                           <option value="">Select a branch...</option>
                           <option v-for="branch in availableBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="packageErrors.branch_id">{{ packageErrors.branch_id }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">PT Product</label>
                        <select class="form-select" v-model="packageForm.pt_product_id" :class="{ 'is-invalid': packageErrors.pt_product_id }">
                           <option value="">Select a product...</option>
                           <option v-for="product in availableProducts" :key="product.id" :value="product.id">
                              {{ product.name }} ({{ product.session_count }} sessions)
                           </option>
                        </select>
                        <div class="invalid-feedback" v-if="packageErrors.pt_product_id">{{ packageErrors.pt_product_id }}</div>
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
                  <button type="button" class="btn btn-danger btn-sm" @click="submitPackage" :disabled="savingPackage || !packageForm.branch_id || !packageForm.pt_product_id">
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
                           <option value="">Select an active package...</option>
                           <option v-for="pkg in activePackages" :key="pkg.id" :value="pkg.id">
                              {{ pkg.pt_product?.name }} • {{ pkg.remaining_sessions }}/{{ pkg.total_sessions }} left
                           </option>
                        </select>
                        <div class="invalid-feedback" v-if="usageErrors.member_pt_package_id">{{ usageErrors.member_pt_package_id }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm fw-semibold">Sessions Used</label>
                        <input type="number" min="1" class="form-control" v-model.number="usageForm.sessions_used" :class="{ 'is-invalid': usageErrors.sessions_used }" />
                        <div class="invalid-feedback" v-if="usageErrors.sessions_used">{{ usageErrors.sessions_used }}</div>
                     </div>
                     <div class="col-md-8">
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

   data() {
      return {
         savingPackage: false,
         savingUsage: false,
         saved: false,
         generalError: "",
         packageErrors: {},
         usageErrors: {},
         packageForm: {
            branch_id: "",
            pt_product_id: "",
            assigned_at: new Date().toISOString().slice(0, 10),
            expires_at: "",
            notes: "",
         },
         usageForm: {
            member_pt_package_id: "",
            sessions_used: 1,
            used_at: new Date().toISOString().slice(0, 16),
            confirmed_by: "",
            notes: "",
         },
         packageModalInst: null,
         usageModalInst: null,
      };
   },

   mounted() {
      this.packageModalInst = new Modal(this.$refs.packageModal);
      this.usageModalInst = new Modal(this.$refs.usageModal);
   },

   watch: {
      member: {
         immediate: true,
         handler() {
            this.resetPackageForm();
            this.resetUsageForm();
         },
      },
      "packageForm.branch_id"(value) {
         if (!this.availableProducts.some((product) => product.id === this.packageForm.pt_product_id)) {
            this.packageForm.pt_product_id = value && this.availableProducts.length ? this.availableProducts[0].id : "";
         }
      },
   },

   computed: {
      availableBranches() {
         return this.member.branches || [];
      },

      availableProducts() {
         const branch = this.availableBranches.find((item) => item.id === this.packageForm.branch_id);
         return branch?.pt_products || [];
      },

      packages() {
         return [...(this.member.member_pt_packages || [])].sort((left, right) => {
            const leftDate = left.assigned_at || left.created_at || "";
            const rightDate = right.assigned_at || right.created_at || "";
            return String(rightDate).localeCompare(String(leftDate));
         });
      },

      activePackages() {
         return this.packages.filter((pkg) => pkg.status === "active" && Number(pkg.remaining_sessions) > 0);
      },

      usageEntries() {
         return this.packages
            .flatMap((pkg) => (pkg.usages || []).map((usage) => ({ ...usage, package: pkg })))
            .sort((left, right) => String(right.used_at || right.created_at || "").localeCompare(String(left.used_at || left.created_at || "")));
      },

      statCards() {
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

      canAllocatePackages() {
         return this.is("super admin") || this.is("admin") || this.is("manager");
      },

      canLogUsage() {
         return this.canAllocatePackages || this.is("staff");
      },
   },

   methods: {
      resetPackageForm() {
         const defaultBranchId = this.availableBranches[0]?.id || "";
         this.packageForm = {
            branch_id: defaultBranchId,
            pt_product_id: "",
            assigned_at: new Date().toISOString().slice(0, 10),
            expires_at: "",
            notes: "",
         };

         const firstProduct = this.availableProducts[0];
         this.packageForm.pt_product_id = firstProduct ? firstProduct.id : "";
      },

      resetUsageForm() {
         this.usageForm = {
            member_pt_package_id: this.activePackages[0]?.id || "",
            sessions_used: 1,
            used_at: new Date().toISOString().slice(0, 16),
            confirmed_by: this.member.name || "",
            notes: "",
         };
      },

      openPackageModal() {
         this.generalError = "";
         this.packageErrors = {};
         this.resetPackageForm();
         this.packageModalInst.show();
      },

      openUsageModal() {
         this.generalError = "";
         this.usageErrors = {};
         this.resetUsageForm();
         this.usageModalInst.show();
      },

      normalizeErrors(errors) {
         return Object.fromEntries(Object.entries(errors || {}).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]));
      },

      submitPackage() {
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

      submitUsage() {
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

      packageStatusClass(status) {
         return {
            active: "m-badge--plan-active",
            consumed: "m-badge--plan-expired",
            cancelled: "m-badge--plan-cancelled",
         }[status] || "m-badge--plan-expired";
      },
   },
};
</script>
