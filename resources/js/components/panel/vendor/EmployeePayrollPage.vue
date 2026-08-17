<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Summary Stats -->
      <div class="row g-3 mb-4" v-if="loading">
         <div class="col-6 col-md-3" v-for="i in 4" :key="'sk-s-' + i">
            <div class="stat-card">
               <div class="skeleton-box rounded-circle flex-shrink-0" style="width: 40px; height: 40px"></div>
               <div class="stat-card-body">
                  <div class="skeleton-box mb-2" style="height: 11px; width: 65%"></div>
                  <div class="skeleton-box" style="height: 18px; width: 40%"></div>
               </div>
            </div>
         </div>
      </div>
      <div class="row g-3 mb-4" v-else-if="payrolls.length">
         <div class="col-6 col-md-3" v-for="s in summaryCards" :key="s.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="s.iconBg"><i class="bi" :class="[s.icon, s.iconColor]"></i></div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ s.label }}</div>
                  <div class="stat-card-value small">₱{{ $filters.formatMoney(s.value) }}</div>
               </div>
            </div>
         </div>
      </div>

      <!-- Actions bar -->
      <div class="d-flex justify-content-between align-items-center mb-3">
         <div class="text-muted small" v-if="!loading">{{ payrolls.length }} payroll record{{ payrolls.length !== 1 ? "s" : "" }}</div>
         <div class="skeleton-box" v-else style="height: 14px; width: 110px; border-radius: 4px"></div>
         <a v-if="canCreatePayroll" class="btn btn-danger btn-sm" :href="createUrl"><i class="bi bi-plus-lg me-1"></i>Create Payroll</a>
      </div>

      <!-- Loading -->
      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 3" :key="i" style="height: 60px; border-radius: 6px; margin-bottom: 8px"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="payrolls.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
         <div>No payrolls yet. Create the first one.</div>
      </div>

      <!-- Payroll list -->
      <div v-else>
         <!-- Desktop table -->
         <div class="d-none d-md-block">
            <table class="table table-striped table-hover align-middle mb-0">
               <thead class="table-light">
                  <tr>
                     <th>Period</th>
                     <th class="text-end">Gross</th>
                     <th class="text-end">Deductions</th>
                     <th class="text-end fw-bold">Net</th>
                     <th class="text-end">Paid</th>
                     <th class="text-end">Balance</th>
                     <th>Status</th>
                     <th class="col-actions"></th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="p in payrolls" :key="p.id">
                     <td class="small">
                        <div class="fw-semibold">{{ formatDate(p.period_start) }} – {{ formatDate(p.period_end) }}</div>
                        <div class="text-muted" style="font-size: 0.75rem">{{ p.notes }}</div>
                        <div class="text-muted" style="font-size: 0.75rem" v-if="p.regular_hours > 0">
                           Regular: {{ formatHours(p.regular_hours) }}h · ₱{{ $filters.formatMoney(p.regular_pay_amount) }}
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem" v-if="p.overwork_hours > 0">
                           Overwork: {{ formatHours(p.overwork_hours) }}h · ₱{{ $filters.formatMoney(p.overwork_pay_amount) }}
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem" v-if="hasManualGrossAdjustment(p.manual_gross_adjustment_amount)">
                           Manual gross adj.:
                           {{ p.manual_gross_adjustment_amount > 0 ? "+" : "-" }}₱{{ $filters.formatMoney(Math.abs(Number(p.manual_gross_adjustment_amount || 0))) }}
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem" v-if="p.employee_contributions_total > 0">
                           Employee contrib.: {{ formatContributionSummary(p.employee_contributions) }}
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem" v-if="p.employer_contributions_total > 0">
                           Employer share: {{ formatContributionSummary(p.employer_contributions) }}
                        </div>
                     </td>
                     <td class="text-end small">₱{{ $filters.formatMoney(p.gross_amount) }}</td>
                     <td class="text-end small text-danger">{{ p.employee_deductions_total > 0 ? "-₱" + $filters.formatMoney(p.employee_deductions_total) : "-" }}</td>
                     <td class="text-end fw-bold small">₱{{ $filters.formatMoney(p.net_amount) }}</td>
                     <td class="text-end small text-success">{{ p.total_paid > 0 ? "₱" + $filters.formatMoney(p.total_paid) : "-" }}</td>
                     <td class="text-end small" :class="p.remaining_balance > 0 ? 'text-danger' : 'text-success'">
                        {{ p.remaining_balance > 0 ? "₱" + $filters.formatMoney(p.remaining_balance) : "✓" }}
                     </td>
                     <td>
                        <span :class="['m-badge', $filters.statusBadge(p.status)]">{{ $filters.capitalize(p.status) }}</span>
                     </td>
                     <td>
                        <div class="d-flex gap-1">
                           <a class="btn btn-sm btn-outline-danger" title="Download Payslip" :href="getPayslipUrl(p)">
                              <i class="bi bi-file-earmark-pdf tbl-icon"></i>
                           </a>
                           <button v-if="canManage && p.status === 'draft'" class="btn btn-sm btn-outline-success" title="Approve" @click="approvePayroll(p)" :disabled="approving === p.id">
                              <i class="bi bi-check-lg tbl-icon"></i>
                           </button>
                           <button v-if="canManage && canAddPayout(p)" class="btn btn-sm btn-outline-primary" title="Add Payout" @click="openPayoutModal(p)">
                              <i class="bi bi-cash tbl-icon"></i>
                           </button>
                           <a v-if="canManage && p.status === 'draft'" class="btn btn-sm btn-outline-secondary" title="Edit" :href="editUrl(p)">
                              <i class="bi bi-pencil tbl-icon"></i>
                           </a>
                           <button v-if="canManage && p.status === 'draft'" class="btn btn-sm btn-outline-warning" title="Cancel" @click="confirmCancel(p)">
                              <i class="bi bi-x-circle tbl-icon"></i>
                           </button>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>

         <!-- Mobile cards -->
         <div class="d-md-none">
            <div class="member-card" v-for="p in payrolls" :key="'pm' + p.id">
               <div class="member-card-top">
                  <div>
                     <div class="fw-semibold small">{{ formatDate(p.period_start) }} – {{ formatDate(p.period_end) }}</div>
                     <div class="text-muted" style="font-size: 0.75rem">
                        Net: <strong>₱{{ $filters.formatMoney(p.net_amount) }}</strong>
                     </div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                     <span :class="['m-badge', $filters.statusBadge(p.status)]">{{ $filters.capitalize(p.status) }}</span>
                     <div class="dropdown" v-if="hasPayrollActions(p)">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item text-danger" :href="getPayslipUrl(p)"><i class="bi bi-file-earmark-pdf me-2"></i>Download Payslip</a>
                           </li>
                           <li v-if="canManage && p.status === 'draft'">
                              <a class="dropdown-item text-success" href="#" @click.prevent="approvePayroll(p)"><i class="bi bi-check-lg me-2"></i>Approve</a>
                           </li>
                           <li v-if="canManage && canAddPayout(p)">
                              <a class="dropdown-item text-primary" href="#" @click.prevent="openPayoutModal(p)"><i class="bi bi-cash me-2"></i>Add Payout</a>
                           </li>
                           <li v-if="canManage && p.status === 'draft'">
                              <a class="dropdown-item" :href="editUrl(p)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li v-if="canManage && p.status === 'draft'">
                              <a class="dropdown-item text-warning" href="#" @click.prevent="confirmCancel(p)"><i class="bi bi-x-circle me-2"></i>Cancel Payroll</a>
                           </li>
                        </ul>
                     </div>
                  </div>
               </div>
               <div class="member-card-tags ps-0 mt-1">
                  <span class="small text-muted">Gross: ₱{{ $filters.formatMoney(p.gross_amount) }}</span>
                  <span class="small text-muted" v-if="p.regular_hours > 0"> · Regular: {{ formatHours(p.regular_hours) }}h</span>
                  <span class="small text-muted" v-if="p.overwork_hours > 0"> · Overwork: {{ formatHours(p.overwork_hours) }}h</span>
                  <span class="small text-muted" v-if="hasManualGrossAdjustment(p.manual_gross_adjustment_amount)">
                     · Adj:
                     {{ p.manual_gross_adjustment_amount > 0 ? "+" : "-" }}₱{{ $filters.formatMoney(Math.abs(Number(p.manual_gross_adjustment_amount || 0))) }}
                  </span>
                  <span class="small text-danger" v-if="p.employee_contributions_total > 0"> · Gov't ded.: ₱{{ $filters.formatMoney(p.employee_contributions_total) }}</span>
                  <span class="small text-muted" v-if="p.employer_contributions_total > 0"> · Employer share: ₱{{ $filters.formatMoney(p.employer_contributions_total) }}</span>
                  <span class="small text-danger" v-if="p.remaining_balance > 0"> · Balance: ₱{{ $filters.formatMoney(p.remaining_balance) }}</span>
                  <span class="small text-success" v-else> · Fully Paid</span>
               </div>
            </div>
         </div>
      </div>

      <!-- Payout Modal -->
      <div class="modal fade" tabindex="-1" ref="payoutModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Add Payout</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="payoutTarget">
                  <div class="alert alert-danger py-2 small" v-if="payoutError">{{ payoutError }}</div>
                  <div class="d-flex justify-content-between mb-3 small">
                     <span class="text-muted">Period: {{ formatDate(payoutTarget.period_start) }} – {{ formatDate(payoutTarget.period_end) }}</span>
                     <span class="fw-bold text-danger">Balance: ₱{{ $filters.formatMoney(payoutTarget.remaining_balance) }}</span>
                  </div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="payoutForm.amount" min="0.01" :max="payoutTarget.remaining_balance" step="0.01" :class="{ 'is-invalid': payoutErrors.amount }" />
                        <div class="invalid-feedback">{{ payoutErrors.amount }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Method <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="payoutForm.method">
                           <option value="cash">Cash</option>
                           <option value="online_payment">Online Payment</option>
                           <option value="bank_transfer">Bank Transfer</option>
                        </select>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Reference No.</label>
                        <input type="text" class="form-control" v-model="payoutForm.reference_number" placeholder="Optional" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Paid At</label>
                        <input type="datetime-local" class="form-control" v-model="payoutForm.paid_at" />
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea class="form-control" rows="2" v-model="payoutForm.notes" placeholder="Optional"></textarea>
                     </div>
                  </div>

                  <!-- Existing payouts for this payroll -->
                  <div v-if="payoutTarget.payouts_count > 0" class="mt-3">
                     <div class="small fw-semibold text-muted mb-2">Previous Payouts</div>
                     <div v-if="loadingPayouts" class="text-center small text-muted">Loading…</div>
                     <div v-else>
                        <div v-for="po in existingPayouts" :key="po.id" class="d-flex justify-content-between align-items-center small border-bottom py-1">
                           <span
                              >{{ formatDate(po.paid_at) }} · <span>{{ $filters.capitalize(po.method) }}</span> <span v-if="po.reference_number" class="text-muted">({{ po.reference_number }})</span></span
                           >
                           <span class="fw-semibold text-success">₱{{ $filters.formatMoney(po.amount) }}</span>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-primary btn-sm" :disabled="payoutSubmitting" @click="submitPayout"><span v-if="payoutSubmitting" class="spinner-border spinner-border-sm me-1"></span>Add Payout</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Cancel Modal -->
      <div class="modal fade" tabindex="-1" ref="cancelModal">
         <div class="modal-dialog modal-sm">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold">Cancel Payroll?</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body pt-1 text-muted small">This draft payroll will be kept in the records and marked as canceled.</div>
               <div class="modal-footer border-0 pt-0">
                  <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-warning btn-sm" :disabled="canceling" @click="doCancel"><span v-if="canceling" class="spinner-border spinner-border-sm me-1"></span>Mark as Canceled</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDate, toDateTimeInputValue } from "../../../dates";

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         approving: null,
         canceling: false,
         payoutSubmitting: false,
         loadingPayouts: false,
         payrolls: [],
         payoutError: "",
         payoutErrors: {},
         pageError: "",
         cancelTarget: null,
         payoutTarget: null,
         existingPayouts: [],
         payoutForm: this.emptyPayoutForm(),
         payoutModalInst: null,
         cancelModalInst: null,
      };
   },

   mounted: function () {
      this.payoutModalInst = new Modal(this.$refs.payoutModal);
      this.cancelModalInst = new Modal(this.$refs.cancelModal);
      this.fetchPayrolls();
   },

   computed: {
      createUrl: function () {
         return `/panel/employees/${this.employee.id}/payrolls/create`;
      },
      canManage: function () {
         return this.can("manage employees");
      },
      canCreatePayroll: function () {
         if (!this.canManage) return false;
         const authUser = window.Laravel?.user;
         if (!authUser) return true;
         const roles = authUser.roles || [];
         const isSelf = Number(authUser.id) === Number(this.employee?.id);
         const isManager = roles.includes("manager");
         const isAdmin = roles.includes("admin") || roles.includes("super admin");
         return !(isSelf && isManager && !isAdmin);
      },
      summaryPayrolls: function () {
         return this.payrolls.filter((payroll) => payroll.status !== "canceled");
      },
      summaryCards: function () {
         const totalGross = this.summaryPayrolls.reduce((sum, payroll) => sum + payroll.gross_amount, 0);
         const totalNet = this.summaryPayrolls.reduce((sum, payroll) => sum + payroll.net_amount, 0);
         const totalPaid = this.summaryPayrolls.reduce((sum, payroll) => sum + payroll.total_paid, 0);
         const outstanding = this.summaryPayrolls.reduce((sum, payroll) => sum + payroll.remaining_balance, 0);
         return [
            { label: "Total Gross", value: totalGross, icon: "bi-receipt", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Total Net", value: totalNet, icon: "bi-calculator", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Total Paid", value: totalPaid, icon: "bi-cash-stack", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Outstanding", value: outstanding, icon: "bi-exclamation-circle", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },

   methods: {
      formatDate,
      formatHours: function (value) {
         return Number(value || 0).toFixed(2);
      },
      hasManualGrossAdjustment: function (value) {
         return Math.abs(Number(value || 0)) >= 0.01;
      },
      contributionPrograms: function (contributions) {
         return Object.entries(contributions || {})
            .map(([programKey, program]) => ({
               key: programKey,
               label: program?.label || this.humanizeContributionKey(programKey),
               total: Number(program?.total || 0),
               lines: Object.entries(program?.lines || {})
                  .map(([lineKey, line]) => ({
                     key: lineKey,
                     label: line?.label || this.humanizeContributionKey(lineKey),
                     amount: Number(line?.amount || 0),
                  }))
                  .filter((line) => line.amount > 0),
            }))
            .filter((program) => program.total > 0 || program.lines.length > 0);
      },
      formatContributionSummary: function (contributions) {
         return this.contributionPrograms(contributions)
            .map((program) => `${program.label} ₱${this.$filters.formatMoney(program.total)}`)
            .join(" · ");
      },
      humanizeContributionKey: function (value) {
         return String(value || "")
            .split("_")
            .filter(Boolean)
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(" ");
      },
      fetchPayrolls: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/employees/${this.employee.id}/payrolls`)
            .then((res) => (this.payrolls = res.data))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load payrolls."))
            .finally(() => (this.loading = false));
      },

      editUrl: function (p) {
         return `/panel/employees/${this.employee.id}/payrolls/create?payroll=${p.id}`;
      },

      approvePayroll: function (p) {
         this.approving = p.id;
         this.pageError = "";
         axios
            .post(`/panel/employees/${this.employee.id}/payrolls/${p.id}/approve`)
            .then((res) => {
               const idx = this.payrolls.findIndex((x) => x.id === p.id);
               if (idx !== -1) this.payrolls.splice(idx, 1, res.data);
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to approve payroll."))
            .finally(() => (this.approving = null));
      },

      openPayoutModal: function (p) {
         if (!this.canAddPayout(p)) {
            this.pageError = "Payroll has no remaining balance for payout.";
            return;
         }

         this.payoutTarget = p;
         this.payoutError = "";
         this.payoutErrors = {};
         this.payoutForm = this.emptyPayoutForm();
         this.existingPayouts = [];
         this.payoutModalInst.show();

         if (p.payouts_count > 0) {
            this.loadingPayouts = true;
            axios
               .get(`/panel/employees/${this.employee.id}/payrolls/${p.id}/payouts`)
               .then((res) => (this.existingPayouts = res.data))
               .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load payouts."))
               .finally(() => (this.loadingPayouts = false));
         }
      },

      submitPayout: function () {
         this.payoutSubmitting = true;
         this.payoutError = "";
         this.payoutErrors = {};
         axios
            .post(`/panel/employees/${this.employee.id}/payrolls/${this.payoutTarget.id}/payouts`, this.payoutForm)
            .then((res) => {
               this.payoutModalInst.hide();
               this.fetchPayrolls();
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  const errors = err.response.data.errors || {};
                  this.payoutErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
               } else {
                  this.payoutError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.payoutSubmitting = false));
      },

      confirmCancel: function (p) {
         this.cancelTarget = p;
         this.cancelModalInst.show();
      },

      doCancel: function () {
         if (!this.cancelTarget) return;
         this.canceling = true;
         this.pageError = "";
         axios
            .post(`/panel/employees/${this.employee.id}/payrolls/${this.cancelTarget.id}/cancel`)
            .then((res) => {
               const idx = this.payrolls.findIndex((p) => p.id === res.data.id);
               if (idx !== -1) this.payrolls.splice(idx, 1, res.data);
               this.cancelModalInst.hide();
               this.cancelTarget = null;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to cancel payroll."))
            .finally(() => (this.canceling = false));
      },

      emptyPayoutForm: function () {
         return { amount: "", method: "cash", reference_number: "", notes: "", paid_at: toDateTimeInputValue() };
      },

      getPayslipUrl: function (payroll) {
         return `/panel/employees/${this.employee.id}/payrolls/${payroll.id}/payslip`;
      },

      canAddPayout: function (payroll) {
         return ["approved", "partially_paid"].includes(payroll.status) && Number(payroll.remaining_balance) > 0;
      },

      hasPayrollActions: function (payroll) {
         return payroll.status === "draft" || this.canAddPayout(payroll) || !!this.getPayslipUrl(payroll);
      },
   },
};
</script>
