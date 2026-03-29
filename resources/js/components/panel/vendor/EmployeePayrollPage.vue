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
         <button class="btn btn-danger btn-sm" @click="openCreate"><i class="bi bi-plus-lg me-1"></i>Create Payroll</button>
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
                     <th class="text-end">Bonus</th>
                     <th class="text-end">PT Comm.</th>
                     <th class="text-end">Deductions</th>
                     <th class="text-end">CA Deduction</th>
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
                        <div class="fw-semibold">{{ $filters.formatDate(p.period_start) }} – {{ $filters.formatDate(p.period_end) }}</div>
                        <div class="text-muted" style="font-size: 0.75rem">{{ p.notes }}</div>
                     </td>
                     <td class="text-end small">₱{{ $filters.formatMoney(p.gross_amount) }}</td>
                     <td class="text-end small text-success">{{ p.bonus > 0 ? "+₱" + $filters.formatMoney(p.bonus) : "—" }}</td>
                     <td class="text-end small text-success">{{ p.pt_commission_amount > 0 ? "+₱" + $filters.formatMoney(p.pt_commission_amount) : "—" }}</td>
                     <td class="text-end small text-danger">{{ p.employee_deductions_total > 0 ? "-₱" + $filters.formatMoney(p.employee_deductions_total) : "—" }}</td>
                     <td class="text-end small text-warning">{{ p.cash_advance_deduction > 0 ? "-₱" + $filters.formatMoney(p.cash_advance_deduction) : "—" }}</td>
                     <td class="text-end fw-bold small">₱{{ $filters.formatMoney(p.net_amount) }}</td>
                     <td class="text-end small text-success">{{ p.total_paid > 0 ? "₱" + $filters.formatMoney(p.total_paid) : "—" }}</td>
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
                           <button v-if="p.status === 'draft'" class="btn btn-sm btn-outline-success" title="Approve" @click="approvePayroll(p)" :disabled="approving === p.id">
                              <i class="bi bi-check-lg tbl-icon"></i>
                           </button>
                           <button v-if="canAddPayout(p)" class="btn btn-sm btn-outline-primary" title="Add Payout" @click="openPayoutModal(p)">
                              <i class="bi bi-cash tbl-icon"></i>
                           </button>
                           <button v-if="p.status === 'draft'" class="btn btn-sm btn-outline-secondary" title="Edit" @click="openEdit(p)">
                              <i class="bi bi-pencil tbl-icon"></i>
                           </button>
                           <button v-if="p.status === 'draft'" class="btn btn-sm btn-outline-warning" title="Cancel" @click="confirmCancel(p)">
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
                     <div class="fw-semibold small">{{ $filters.formatDate(p.period_start) }} – {{ $filters.formatDate(p.period_end) }}</div>
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
                           <li v-if="p.status === 'draft'">
                              <a class="dropdown-item text-success" href="#" @click.prevent="approvePayroll(p)"><i class="bi bi-check-lg me-2"></i>Approve</a>
                           </li>
                           <li v-if="canAddPayout(p)">
                              <a class="dropdown-item text-primary" href="#" @click.prevent="openPayoutModal(p)"><i class="bi bi-cash me-2"></i>Add Payout</a>
                           </li>
                           <li v-if="p.status === 'draft'">
                              <a class="dropdown-item" href="#" @click.prevent="openEdit(p)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li v-if="p.status === 'draft'">
                              <a class="dropdown-item text-warning" href="#" @click.prevent="confirmCancel(p)"><i class="bi bi-x-circle me-2"></i>Cancel Payroll</a>
                           </li>
                        </ul>
                     </div>
                  </div>
               </div>
               <div class="member-card-tags ps-0 mt-1">
                  <span class="small text-muted">Gross: ₱{{ $filters.formatMoney(p.gross_amount) }}</span>
                  <span class="small text-success" v-if="p.pt_commission_amount > 0"> · PT: +₱{{ $filters.formatMoney(p.pt_commission_amount) }}</span>
                  <span class="small text-danger" v-if="p.remaining_balance > 0"> · Balance: ₱{{ $filters.formatMoney(p.remaining_balance) }}</span>
                  <span class="small text-success" v-else> · Fully Paid</span>
               </div>
            </div>
         </div>
      </div>

      <!-- Create / Edit Payroll Modal -->
      <div class="modal fade" tabindex="-1" ref="payrollModal">
         <div class="modal-dialog modal-lg">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ modalMode === "create" ? "Create Payroll" : "Edit Payroll" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>

                  <!-- Attendance suggestion banner (create mode only) -->
                  <div v-if="modalMode === 'create'" class="mb-3">
                     <div v-if="loadingSuggestion" class="alert alert-secondary py-2 small d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm"></span>
                        Computing from attendance…
                     </div>
                     <div v-else-if="suggestion" class="alert py-2 small mb-0" :class="suggestion.daily_rate > 0 ? 'alert-info' : 'alert-warning'">
                        <i class="bi bi-calendar-check me-1"></i>
                        <strong>{{ suggestion.days_worked }} day{{ suggestion.days_worked !== 1 ? "s" : "" }} worked</strong>
                        <template v-if="suggestion.daily_rate > 0">
                           &times; ₱{{ $filters.formatMoney(suggestion.daily_rate) }} daily rate = <strong>₱{{ $filters.formatMoney(suggestion.gross_amount) }}</strong> gross.
                        </template>
                        <template v-else> &mdash; <span class="text-warning fw-semibold">No daily rate set.</span> Set it on the employee profile to auto-compute gross. </template>
                        <span v-if="suggestion.suggested_ca > 0"> &nbsp;· Pending CA: ₱{{ $filters.formatMoney(suggestion.suggested_ca) }}</span>
                        <span v-if="suggestion.pt_commission_amount > 0"> &nbsp;· PT commissions: ₱{{ $filters.formatMoney(suggestion.pt_commission_amount) }}</span>
                        <span v-if="suggestion.days_worked === 0" class="d-block text-muted mt-1"><i class="bi bi-info-circle me-1"></i>No attendance records found for this period.</span>
                        <span v-if="suggestion.pt_commission_items?.length" class="d-block text-muted mt-1"> <i class="bi bi-stopwatch me-1"></i>{{ suggestion.pt_commission_items.length }} completed PT package{{ suggestion.pt_commission_items.length !== 1 ? "s" : "" }} will be added to this payroll. </span>
                     </div>
                     <div v-else-if="!form.period_start || !form.period_end" class="alert alert-secondary py-2 small text-muted"><i class="bi bi-info-circle me-1"></i>Set the period dates to auto-compute from attendance.</div>
                  </div>

                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Period Start <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" v-model="form.period_start" :class="{ 'is-invalid': formErrors.period_start }" />
                        <div class="invalid-feedback">{{ formErrors.period_start }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Period End <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" v-model="form.period_end" :class="{ 'is-invalid': formErrors.period_end }" />
                        <div class="invalid-feedback">{{ formErrors.period_end }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Gross Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="form.gross_amount" min="0" step="0.01" :class="{ 'is-invalid': formErrors.gross_amount }" />
                        <div class="invalid-feedback">{{ formErrors.gross_amount }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Bonus (₱)</label>
                        <input type="number" class="form-control" v-model="form.bonus" min="0" step="0.01" />
                     </div>
                      <div class="col-md-6">
                         <label class="form-label form-label-sm">PT Commission (₱)</label>
                         <input type="number" class="form-control" :value="form.pt_commission_amount" readonly />
                         <div class="form-text">
                            Auto-added from completed PT packages in this payroll period.
                            <span v-if="form.pt_commission_items?.length">({{ form.pt_commission_items.length }} item{{ form.pt_commission_items.length !== 1 ? "s" : "" }})</span>
                         </div>
                      </div>
                      <div class="col-md-6">
                         <label class="form-label form-label-sm">Other Deductions (₱)</label>
                         <input type="number" class="form-control" v-model="form.manual_deductions" min="0" step="0.01" />
                      </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm d-flex justify-content-between">
                           <span>Cash Advance Deduction (₱)</span>
                           <button type="button" class="btn btn-link btn-sm py-0 px-0" @click="loadSuggestedCa" :disabled="loadingCa">
                              <span v-if="loadingCa" class="spinner-border spinner-border-sm"></span>
                              <span v-else><i class="bi bi-magic me-1"></i>Auto-fill</span>
                           </button>
                        </label>
                         <input type="number" class="form-control" v-model="form.cash_advance_deduction" min="0" step="0.01" :class="{ 'is-invalid': formErrors.cash_advance_deduction }" />
                         <div class="invalid-feedback">{{ formErrors.cash_advance_deduction }}</div>
                         <div class="form-text text-warning" v-if="suggestedCa > 0 && modalMode === 'create'">Pending advances: ₱{{ $filters.formatMoney(suggestedCa) }}</div>
                      </div>
                      <div class="col-12">
                         <label class="form-label form-label-sm">Notes</label>
                         <textarea class="form-control" rows="2" v-model="form.notes" placeholder="Optional"></textarea>
                      </div>
                     <!-- Net preview -->
                     <div class="col-12">
                        <div class="p-3 rounded bg-light border d-flex justify-content-between align-items-center">
                           <span class="text-muted">Net Amount Preview:</span>
                           <span class="fw-bold fs-5">₱{{ $filters.formatMoney(netPreview) }}</span>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submitPayroll">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                     {{ modalMode === "create" ? "Create" : "Save Changes" }}
                  </button>
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
                     <span class="text-muted">Period: {{ $filters.formatDate(payoutTarget.period_start) }} – {{ $filters.formatDate(payoutTarget.period_end) }}</span>
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
                              >{{ $filters.formatDate(po.paid_at) }} · <span>{{ $filters.capitalize(po.method) }}</span> <span v-if="po.reference_number" class="text-muted">({{ po.reference_number }})</span></span
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

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         submitting: false,
         approving: null,
         canceling: false,
         payoutSubmitting: false,
         loadingCa: false,
         loadingPayouts: false,
         loadingSuggestion: false,
         suggestion: null,
         payrolls: [],
         formError: "",
         formErrors: {},
         payoutError: "",
         payoutErrors: {},
         pageError: "",
         modalMode: "create",
         cancelTarget: null,
         payoutTarget: null,
         existingPayouts: [],
         suggestedCa: 0,
         form: this.emptyForm(),
         payoutForm: this.emptyPayoutForm(),
         payrollModalInst: null,
         payoutModalInst: null,
         cancelModalInst: null,
      };
   },

   mounted: function () {
      this.payrollModalInst = new Modal(this.$refs.payrollModal);
      this.payoutModalInst = new Modal(this.$refs.payoutModal);
      this.cancelModalInst = new Modal(this.$refs.cancelModal);
      this.fetchPayrolls();
   },

   watch: {
      "form.period_start"(val) {
         if (this.modalMode === "create" && val && this.form.period_end) this.fetchSuggestion();
      },
      "form.period_end"(val) {
         if (this.modalMode === "create" && val && this.form.period_start) this.fetchSuggestion();
      },
   },

   computed: {
      netPreview: function () {
         const gross = parseFloat(this.form.gross_amount) || 0;
         const bonus = parseFloat(this.form.bonus) || 0;
         const ptCommission = parseFloat(this.form.pt_commission_amount) || 0;
         const ded = parseFloat(this.form.manual_deductions) || 0;
         const ca = parseFloat(this.form.cash_advance_deduction) || 0;
         return Math.max(0, gross + bonus + ptCommission - ded - ca);
      },
      summaryCards: function () {
         const totalGross = this.payrolls.reduce((s, p) => s + p.gross_amount, 0);
         const totalNet = this.payrolls.reduce((s, p) => s + p.net_amount, 0);
         const totalPaid = this.payrolls.reduce((s, p) => s + p.total_paid, 0);
         const outstanding = this.payrolls.reduce((s, p) => s + p.remaining_balance, 0);
         return [
            { label: "Total Gross", value: totalGross, icon: "bi-receipt", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Total Net", value: totalNet, icon: "bi-calculator", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Total Paid", value: totalPaid, icon: "bi-cash-stack", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Outstanding", value: outstanding, icon: "bi-exclamation-circle", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },

   methods: {
      fetchPayrolls: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/employees/${this.employee.id}/payrolls`)
            .then((res) => (this.payrolls = res.data))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load payrolls."))
            .finally(() => (this.loading = false));
      },

      openCreate: function () {
         this.modalMode = "create";
         // Default period to current calendar month
         const now = new Date();
         const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
         const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
         this.form = { ...this.emptyForm(), period_start: firstDay, period_end: lastDay };
         this.formError = "";
         this.formErrors = {};
         this.suggestedCa = 0;
         this.suggestion = null;
         this.payrollModalInst.show();
         this.fetchSuggestion();
      },

      openEdit: function (p) {
         this.modalMode = "edit";
         this.formError = "";
         this.formErrors = {};
         this.form = {
            id: p.id,
            period_start: p.period_start,
            period_end: p.period_end,
            gross_amount: p.gross_amount,
            bonus: p.bonus,
            pt_commission_amount: p.pt_commission_amount,
            pt_commission_items: p.pt_commission_items || [],
            manual_deductions: p.manual_deductions,
            cash_advance_deduction: p.cash_advance_deduction,
            notes: p.notes || "",
         };
         this.payrollModalInst.show();
      },

      loadSuggestedCa: function () {
         this.loadingCa = true;
         axios
            .get(`/panel/employees/${this.employee.id}/payrolls/suggested-ca`)
            .then((res) => {
               this.suggestedCa = res.data.suggested_ca;
               this.form.cash_advance_deduction = res.data.suggested_ca;
            })
            .finally(() => (this.loadingCa = false));
      },

      fetchSuggestion: function () {
         if (!this.form.period_start || !this.form.period_end) return;
         this.loadingSuggestion = true;
         this.suggestion = null;
         axios
            .get(`/panel/employees/${this.employee.id}/payrolls/suggest`, {
               params: {
                  period_start: this.form.period_start,
                  period_end: this.form.period_end,
                  payroll_id: this.form.id || null,
               },
            })
            .then((res) => {
               this.suggestion = res.data;
               if (this.modalMode === "create") {
                  if (res.data.gross_amount > 0) this.form.gross_amount = res.data.gross_amount;
                  if (res.data.suggested_ca > 0) this.form.cash_advance_deduction = res.data.suggested_ca;
                  this.form.pt_commission_amount = res.data.pt_commission_amount || 0;
                  this.form.pt_commission_items = res.data.pt_commission_items || [];
               }
            })
            .finally(() => (this.loadingSuggestion = false));
      },

      submitPayroll: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         const req = this.modalMode === "create" ? axios.post(`/panel/employees/${this.employee.id}/payrolls`, this.form) : axios.put(`/panel/employees/${this.employee.id}/payrolls/${this.form.id}`, this.form);

         req.then((res) => {
            this.payrollModalInst.hide();
            if (this.modalMode === "create") {
               this.payrolls.unshift(res.data);
            } else {
               const idx = this.payrolls.findIndex((p) => p.id === res.data.id);
               if (idx !== -1) this.payrolls.splice(idx, 1, res.data);
            }
         })
            .catch((err) => {
               if (err.response?.status === 422) {
                  const errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
               } else {
                  this.formError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.submitting = false));
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

      emptyForm: function () {
         return { period_start: "", period_end: "", gross_amount: "", bonus: 0, pt_commission_amount: 0, pt_commission_items: [], manual_deductions: 0, cash_advance_deduction: 0, notes: "" };
      },

      emptyPayoutForm: function () {
         const d = new Date();
         d.setSeconds(0, 0);
         return { amount: "", method: "cash", reference_number: "", notes: "", paid_at: d.toISOString().slice(0, 16) };
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
