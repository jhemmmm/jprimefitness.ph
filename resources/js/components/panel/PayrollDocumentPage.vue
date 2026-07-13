<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
         <div>
            <h4 class="fw-bold mb-0">{{ isEdit ? "Edit Payroll Draft" : "Create Payroll" }}</h4>
            <div class="text-muted small">Review attendance, adjust amounts, and save the payroll draft</div>
         </div>
         <a :href="employeeUrl" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
      </div>

      <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>

      <div class="doc-sheet mx-auto">
         <!-- Header band -->
         <div class="doc-band">
            <div class="doc-band-left">
               <div class="doc-kicker">Official Payroll Document</div>
               <div class="doc-brand">{{ businessName }}</div>
               <p class="doc-copy">Employee compensation statement computed from attendance, schedule, and statutory deductions.</p>
            </div>
            <div class="doc-band-right">
               <h1 class="doc-title">Payroll</h1>
               <table class="doc-meta">
                  <tbody>
                     <tr v-if="isEdit">
                        <td class="doc-meta-label">Payroll ID</td>
                        <td>
                           <strong>#{{ payroll.id }}</strong>
                        </td>
                     </tr>
                     <tr>
                        <td class="doc-meta-label">Employee</td>
                        <td>
                           <strong>{{ employee.name }}</strong>
                        </td>
                     </tr>
                     <tr>
                        <td class="doc-meta-label">Pay Period</td>
                        <td>
                           <strong>{{ periodLabel }}</strong>
                        </td>
                     </tr>
                     <tr>
                        <td class="doc-meta-label">Status</td>
                        <td><span class="doc-pill">Draft</span></td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>

         <div class="doc-content">
            <!-- Employee details -->
            <div class="doc-section-title">Employee Details</div>
            <div class="doc-info-grid">
               <div class="doc-info-cell">
                  <span class="doc-field-label">Employee Name</span>
                  <span class="doc-field-value">{{ employee.name }}</span>
               </div>
               <div class="doc-info-cell">
                  <span class="doc-field-label">Role</span>
                  <span class="doc-field-value">{{ roleNames || "Employee" }}</span>
               </div>
               <div class="doc-info-cell">
                  <span class="doc-field-label">Daily Rate</span>
                  <span class="doc-field-value">₱{{ $filters.formatMoney(dailyRate) }}</span>
               </div>
               <div class="doc-info-cell">
                  <span class="doc-field-label">Pay Frequency</span>
                  <span class="doc-field-value">{{ $filters.capitalize(employee.employee_profile?.pay_frequency || "-") }}</span>
               </div>
            </div>

            <!-- Pay period -->
            <div class="doc-section-title">Pay Period</div>
            <div class="row g-3 mb-2">
               <div class="col-sm-4">
                  <label class="form-label form-label-sm">Period Start <span class="text-danger">*</span></label>
                  <input type="date" class="form-control form-control-sm" v-model="form.period_start" :class="{ 'is-invalid': formErrors.period_start }" />
                  <div class="invalid-feedback" v-if="formErrors.period_start">{{ formErrors.period_start }}</div>
               </div>
               <div class="col-sm-4">
                  <label class="form-label form-label-sm">Period End <span class="text-danger">*</span></label>
                  <input type="date" class="form-control form-control-sm" v-model="form.period_end" :class="{ 'is-invalid': formErrors.period_end }" />
                  <div class="invalid-feedback" v-if="formErrors.period_end">{{ formErrors.period_end }}</div>
               </div>
               <div class="col-sm-4 d-flex align-items-end">
                  <div v-if="loadingSuggestion" class="text-muted small pb-2"><span class="spinner-border spinner-border-sm me-1"></span>Computing from attendance…</div>
               </div>
            </div>
            <div v-if="dailyRate <= 0" class="alert alert-warning py-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>No daily rate set. Set it on the employee profile to auto-compute pay.</div>
            <div v-if="suggestion && suggestion.open_attendance_count > 0" class="alert alert-warning py-2 small mb-2">
               <i class="bi bi-exclamation-circle me-1"></i>{{ suggestion.open_attendance_count }} open attendance record{{ suggestion.open_attendance_count !== 1 ? "s are" : " is" }} excluded until checkout.
            </div>

            <!-- Attendance detail -->
            <div class="doc-section-title">Attendance Detail</div>
            <div class="table-responsive mb-3">
               <table class="doc-table">
                  <thead>
                     <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th class="text-end">Scheduled</th>
                        <th class="text-end">Worked</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Day Pay</th>
                        <th class="text-end">Deduction</th>
                        <th>Status</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="day in suggestionDays" :key="day.date">
                        <td class="fw-semibold">{{ formatDate(day.date) }}</td>
                        <td>{{ day.time_in ? formatTime(day.time_in) : "-" }}</td>
                        <td>{{ day.time_out ? formatTime(day.time_out) : "-" }}</td>
                        <td class="text-end">{{ day.scheduled_hours > 0 ? formatHours(day.scheduled_hours) + "h" : "-" }}</td>
                        <td class="text-end">{{ formatHours(day.worked_hours) }}h</td>
                        <td class="text-end">{{ formatHours(day.paid_hours) }}h</td>
                        <td class="text-end">₱{{ $filters.formatMoney(day.day_pay_amount) }}</td>
                        <td class="text-end">
                           <span v-if="day.deduction_amount > 0" class="doc-negative">-₱{{ $filters.formatMoney(day.deduction_amount) }}</span>
                           <span v-else>-</span>
                        </td>
                        <td>
                           <span :class="['m-badge', dayStatusBadge(day.status)]">{{ $filters.capitalize(day.status) }}</span>
                           <span v-if="day.late_minutes > 0" class="text-muted ms-1 small">{{ day.late_minutes }}m late</span>
                        </td>
                     </tr>
                     <tr v-if="!suggestionDays.length">
                        <td colspan="9" class="text-center text-muted py-3">No attendance records found for this period.</td>
                     </tr>
                  </tbody>
                  <tfoot v-if="suggestionDays.length">
                     <tr class="doc-table-total">
                        <td colspan="4">Totals</td>
                        <td class="text-end">{{ formatHours(dayTotals.worked) }}h</td>
                        <td class="text-end">{{ formatHours(dayTotals.paid) }}h</td>
                        <td class="text-end">₱{{ $filters.formatMoney(dayTotals.pay) }}</td>
                        <td class="text-end">
                           <span v-if="dayTotals.deduction > 0" class="doc-negative">-₱{{ $filters.formatMoney(dayTotals.deduction) }}</span>
                           <span v-else>-</span>
                        </td>
                        <td></td>
                     </tr>
                  </tfoot>
               </table>
            </div>

            <!-- Compensation -->
            <div class="doc-section-title">Compensation Breakdown</div>
            <div class="row g-3 mb-3">
               <div class="col-lg-7">
                  <table class="doc-breakdown">
                     <tbody>
                        <tr>
                           <td>Regular pay ({{ formatHours(form.regular_hours) }} hrs)</td>
                           <td class="text-end fw-bold">₱{{ $filters.formatMoney(form.regular_pay_amount) }}</td>
                        </tr>
                        <tr v-if="payOverworkHours || form.overwork_pay_amount > 0">
                           <td>Overwork pay ({{ formatHours(form.overwork_hours) }} hrs)</td>
                           <td class="text-end fw-bold doc-positive">+ ₱{{ $filters.formatMoney(form.overwork_pay_amount) }}</td>
                        </tr>
                        <tr v-if="Math.abs(manualGrossAdjustment) >= 0.01">
                           <td>Manual gross adjustment</td>
                           <td class="text-end fw-bold" :class="manualGrossAdjustment > 0 ? 'doc-positive' : 'doc-negative'">{{ manualGrossAdjustment > 0 ? "+" : "-" }} ₱{{ $filters.formatMoney(Math.abs(manualGrossAdjustment)) }}</td>
                        </tr>
                        <tr>
                           <td class="align-middle">Gross amount</td>
                           <td class="text-end">
                              <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end doc-amount-input" v-model="form.gross_amount" :class="{ 'is-invalid': formErrors.gross_amount }" />
                              <div class="invalid-feedback" v-if="formErrors.gross_amount">{{ formErrors.gross_amount }}</div>
                           </td>
                        </tr>
                        <tr v-if="payrollWithholdingTaxEnabled || form.withholding_tax > 0">
                           <td>Withholding tax</td>
                           <td class="text-end fw-bold" :class="{ 'doc-negative': form.withholding_tax > 0 }">{{ form.withholding_tax > 0 ? "- ₱" + $filters.formatMoney(form.withholding_tax) : "-" }}</td>
                        </tr>
                        <template v-for="program in contributionPrograms(form.employee_contributions)" :key="program.key">
                           <tr v-for="line in program.lines" :key="program.key + '-' + line.key">
                              <td>{{ program.label }} - {{ line.label }}</td>
                              <td class="text-end fw-bold doc-negative">- ₱{{ $filters.formatMoney(line.amount) }}</td>
                           </tr>
                        </template>
                        <tr>
                           <td class="align-middle">Other deductions</td>
                           <td class="text-end">
                              <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end doc-amount-input" v-model="form.manual_deductions" :class="{ 'is-invalid': formErrors.manual_deductions }" />
                              <div class="invalid-feedback" v-if="formErrors.manual_deductions">{{ formErrors.manual_deductions }}</div>
                           </td>
                        </tr>
                        <tr class="doc-net-row">
                           <td>Net pay</td>
                           <td class="text-end">₱{{ $filters.formatMoney(netPreview) }}</td>
                        </tr>
                     </tbody>
                  </table>
               </div>
               <div class="col-lg-5">
                  <div class="doc-box mb-3">
                     <div class="doc-box-heading">Notes</div>
                     <textarea class="form-control form-control-sm" rows="3" v-model="form.notes" placeholder="Optional payroll notes"></textarea>
                  </div>
                  <div class="doc-box" v-if="contributionPrograms(form.employer_contributions).length">
                     <div class="doc-box-heading">Employer Contributions</div>
                     <div class="text-muted small mb-2">Reference only. These employer-share statutory amounts do not reduce employee net pay.</div>
                     <table class="doc-breakdown">
                        <tbody>
                           <tr v-for="program in contributionPrograms(form.employer_contributions)" :key="'er-' + program.key">
                              <td>{{ program.label }}</td>
                              <td class="text-end fw-bold">₱{{ $filters.formatMoney(program.total) }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-end gap-2 border-top pt-3">
               <a :href="employeeUrl" class="btn btn-outline-secondary btn-sm">Cancel</a>
               <button class="btn btn-danger btn-sm" :disabled="submitting || !form.period_start || !form.period_end" @click="submitPayroll">
                  <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-save me-1"></i>
                  {{ isEdit ? "Save Changes" : "Save Draft" }}
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { appDayjs, formatDate, formatTime, nowTimestamp, startOfCurrentMonthDate } from "../../dates";

export default {
   props: {
      employee: { type: Object, required: true },
      payroll: { type: Object, default: null },
   },

   data: function () {
      return {
         form: this.initialForm(),
         suggestion: null,
         loadingSuggestion: false,
         submitting: false,
         formError: "",
         formErrors: {},
         shouldAutofillSuggestedAmounts: !this.payroll,
         suggestionFetchHandle: null,
      };
   },

   mounted: function () {
      this.queueSuggestionFetch();
   },

   watch: {
      "form.period_start"() {
         this.queueSuggestionFetch();
      },
      "form.period_end"() {
         this.queueSuggestionFetch();
      },
      "form.gross_amount"() {
         this.queueSuggestionFetch();
      },
      "form.manual_deductions"() {
         this.queueSuggestionFetch();
      },
   },

   computed: {
      isEdit: function () {
         return Boolean(this.payroll);
      },
      employeeUrl: function () {
         return `/panel/employees/${this.employee.id}?tab=payroll`;
      },
      businessName: function () {
         return globalThis.JPrime?.profile?.name || "";
      },
      roleNames: function () {
         return (this.employee.roles || []).map((role) => this.$filters.capitalize(role.name)).join(", ");
      },
      dailyRate: function () {
         return Number(this.employee.employee_profile?.daily_rate || 0);
      },
      periodLabel: function () {
         if (!this.form.period_start || !this.form.period_end) return "-";
         return `${formatDate(this.form.period_start)} – ${formatDate(this.form.period_end)}`;
      },
      payOverworkHours: function () {
         return Boolean(globalThis.JPrime?.profile?.pay_overwork_hours);
      },
      payrollWithholdingTaxEnabled: function () {
         return Boolean(globalThis.JPrime?.profile?.payroll_withholding_tax_enabled);
      },
      suggestionDays: function () {
         return this.suggestion?.days || [];
      },
      dayTotals: function () {
         return this.suggestionDays.reduce(
            (totals, day) => ({
               worked: totals.worked + Number(day.worked_hours || 0),
               paid: totals.paid + Number(day.paid_hours || 0),
               pay: totals.pay + Number(day.day_pay_amount || 0),
               deduction: totals.deduction + Number(day.deduction_amount || 0),
            }),
            { worked: 0, paid: 0, pay: 0, deduction: 0 },
         );
      },
      manualGrossAdjustment: function () {
         return Number(this.suggestion?.manual_gross_adjustment_amount || 0);
      },
      netPreview: function () {
         return Number(this.suggestion?.net_amount_preview || 0);
      },
   },

   methods: {
      formatDate,
      formatTime,
      formatHours: function (value) {
         return Number(value || 0).toFixed(2);
      },
      dayStatusBadge: function (status) {
         return (
            {
               full: "m-badge--active",
               late: "m-badge--pending",
               undertime: "m-badge--partial",
               absent: "m-badge--suspended",
               unscheduled: "m-badge--draft",
            }[status] || "m-badge--draft"
         );
      },
      initialForm: function () {
         if (this.payroll) {
            return {
               id: this.payroll.id,
               period_start: this.payroll.period_start,
               period_end: this.payroll.period_end,
               regular_hours: this.payroll.regular_hours || 0,
               regular_pay_amount: this.payroll.regular_pay_amount || 0,
               overwork_hours: this.payroll.overwork_hours || 0,
               overwork_pay_amount: this.payroll.overwork_pay_amount || 0,
               gross_amount: this.payroll.gross_amount,
               withholding_tax: this.payroll.withholding_tax || 0,
               manual_deductions: this.payroll.manual_deductions || 0,
               employee_contributions: this.payroll.employee_contributions || {},
               employee_contributions_total: Number(this.payroll.employee_contributions_total || 0),
               employer_contributions: this.payroll.employer_contributions || {},
               employer_contributions_total: Number(this.payroll.employer_contributions_total || 0),
               notes: this.payroll.notes || "",
            };
         }

         return {
            period_start: startOfCurrentMonthDate(),
            period_end: appDayjs(nowTimestamp()).endOf("month").format("YYYY-MM-DD"),
            regular_hours: 0,
            regular_pay_amount: 0,
            overwork_hours: 0,
            overwork_pay_amount: 0,
            gross_amount: "",
            withholding_tax: 0,
            manual_deductions: 0,
            employee_contributions: {},
            employee_contributions_total: 0,
            employer_contributions: {},
            employer_contributions_total: 0,
            notes: "",
         };
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
      humanizeContributionKey: function (value) {
         return String(value || "")
            .split("_")
            .filter(Boolean)
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(" ");
      },
      applyContributionState: function (payload) {
         this.form.employee_contributions = payload.employee_contributions || {};
         this.form.employee_contributions_total = Number(payload.employee_contributions_total || 0);
         this.form.employer_contributions = payload.employer_contributions || {};
         this.form.employer_contributions_total = Number(payload.employer_contributions_total || 0);
      },
      queueSuggestionFetch: function () {
         if (!this.form.period_start || !this.form.period_end) {
            this.suggestion = null;
            this.form.withholding_tax = 0;
            this.form.regular_hours = 0;
            this.form.regular_pay_amount = 0;
            this.form.overwork_hours = 0;
            this.form.overwork_pay_amount = 0;
            this.applyContributionState({});
            return;
         }

         window.clearTimeout(this.suggestionFetchHandle);
         this.suggestionFetchHandle = window.setTimeout(() => {
            this.fetchSuggestion();
         }, 250);
      },
      fetchSuggestion: function () {
         if (!this.form.period_start || !this.form.period_end) return;

         this.loadingSuggestion = true;
         axios
            .get(`/panel/employees/${this.employee.id}/payrolls/suggest`, {
               params: {
                  period_start: this.form.period_start,
                  period_end: this.form.period_end,
                  payroll_id: this.form.id || null,
                  gross_amount: this.form.gross_amount === "" ? undefined : this.form.gross_amount,
                  manual_deductions: this.form.manual_deductions,
               },
            })
            .then((res) => {
               this.suggestion = res.data;
               this.form.regular_hours = res.data.regular_hours || 0;
               this.form.regular_pay_amount = res.data.regular_pay_amount || 0;
               this.form.overwork_hours = res.data.overwork_hours || 0;
               this.form.overwork_pay_amount = res.data.overwork_pay_amount || 0;
               this.form.withholding_tax = res.data.withholding_tax || 0;
               this.applyContributionState(res.data);

               if (this.shouldAutofillSuggestedAmounts) {
                  if (this.form.gross_amount === "" && res.data.gross_amount > 0) this.form.gross_amount = res.data.gross_amount;

                  this.shouldAutofillSuggestedAmounts = false;
               }
            })
            .catch((err) => (this.formError = err.response?.data?.message || "Failed to compute from attendance."))
            .finally(() => (this.loadingSuggestion = false));
      },
      submitPayroll: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         const req = this.isEdit ? axios.put(`/panel/employees/${this.employee.id}/payrolls/${this.form.id}`, this.form) : axios.post(`/panel/employees/${this.employee.id}/payrolls`, this.form);

         req.then(() => {
            window.location.href = this.employeeUrl;
         })
            .catch((err) => {
               if (err.response?.status === 422) {
                  const errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
                  this.formError = "Please review the highlighted fields.";
               } else {
                  this.formError = err.response?.data?.message || "Something went wrong.";
               }
               this.submitting = false;
            });
      },
   },

   beforeUnmount: function () {
      window.clearTimeout(this.suggestionFetchHandle);
   },
};
</script>

<style scoped>
/* The sheet reads as a paper document: fixed light palette in both themes. */
.doc-sheet {
   max-width: 960px;
   background: #ffffff;
   color: #111827;
   border: 1px solid #d1d5db;
   border-radius: 4px;
   box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.doc-band {
   display: flex;
   justify-content: space-between;
   gap: 16px;
   flex-wrap: wrap;
   padding: 20px 24px 16px;
   border-bottom: 6px solid #d72638;
}

.doc-kicker {
   color: #d72638;
   font-size: 0.65rem;
   letter-spacing: 1.8px;
   text-transform: uppercase;
   font-weight: 700;
}

.doc-brand {
   font-size: 1.05rem;
   font-weight: 800;
}

.doc-copy {
   margin: 2px 0 0;
   color: #6b7280;
   max-width: 340px;
   font-size: 0.75rem;
   line-height: 1.35;
}

.doc-title {
   margin: 0 0 6px;
   font-size: 1.5rem;
   font-weight: 800;
   text-align: right;
}

.doc-meta {
   margin-left: auto;
   border-collapse: collapse;
}

.doc-meta td {
   padding: 1px 0 1px 12px;
   font-size: 0.72rem;
   text-align: right;
}

.doc-meta-label {
   color: #6b7280;
   text-transform: uppercase;
   letter-spacing: 0.8px;
   font-weight: 700;
}

.doc-pill {
   display: inline-block;
   padding: 2px 10px;
   border-radius: 999px;
   font-size: 0.65rem;
   text-transform: uppercase;
   letter-spacing: 1px;
   font-weight: 700;
   background: #f3f4f6;
   color: #374151;
}

.doc-content {
   padding: 18px 24px 20px;
}

.doc-section-title {
   margin: 16px 0 10px;
   font-size: 0.68rem;
   letter-spacing: 1.2px;
   text-transform: uppercase;
   font-weight: 700;
   border-left: 4px solid #d72638;
   padding-left: 8px;
}

.doc-section-title:first-child {
   margin-top: 0;
}

.doc-info-grid {
   display: grid;
   grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
   border: 1px solid #e5e7eb;
   margin-bottom: 6px;
}

.doc-info-cell {
   padding: 8px 10px;
   border: 1px solid #e5e7eb;
   margin: -1px 0 0 -1px;
}

.doc-field-label {
   display: block;
   margin-bottom: 2px;
   color: #6b7280;
   font-size: 0.65rem;
   text-transform: uppercase;
   letter-spacing: 0.8px;
   font-weight: 700;
}

.doc-field-value {
   font-size: 0.85rem;
   font-weight: 700;
}

.doc-table {
   width: 100%;
   border-collapse: collapse;
   font-size: 0.8rem;
}

.doc-table th,
.doc-table td {
   padding: 6px 8px;
   border: 1px solid #e5e7eb;
}

.doc-table th {
   background: #f9fafb;
   color: #6b7280;
   font-size: 0.62rem;
   text-transform: uppercase;
   letter-spacing: 0.8px;
   text-align: left;
}

.doc-table th.text-end {
   text-align: right;
}

.doc-table-total td {
   background: #fbfbfc;
   font-weight: 700;
}

.doc-breakdown {
   width: 100%;
   border-collapse: collapse;
   font-size: 0.82rem;
}

.doc-breakdown td {
   padding: 6px 0;
   border-bottom: 1px solid #e5e7eb;
   color: #4b5563;
}

.doc-breakdown tr:last-child td {
   border-bottom: 0;
}

.doc-net-row td {
   font-size: 1rem;
   font-weight: 800;
   color: #15803d;
   border-top: 2px solid #111827;
}

.doc-positive {
   color: #15803d;
}

.doc-negative {
   color: #d72638;
}

.doc-amount-input {
   max-width: 160px;
   margin-left: auto;
   background: #ffffff;
   color: #111827;
   border-color: #d1d5db;
}

.doc-box {
   border: 1px solid #e5e7eb;
   padding: 10px 12px;
}

.doc-box-heading {
   margin-bottom: 8px;
   font-size: 0.8rem;
   font-weight: 800;
}

/* form controls inside the paper stay light in dark mode too */
.doc-sheet .form-control,
.doc-sheet .form-label {
   background: #ffffff;
   color: #111827;
   border-color: #d1d5db;
}

.doc-sheet .text-muted {
   color: #6b7280 !important;
}
</style>
