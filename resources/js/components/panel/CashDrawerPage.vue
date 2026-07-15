<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
         <div>
            <h4 class="fw-bold mb-0">Cash Drawer</h4>
            <div class="text-muted small">Track the cash in the gym: open the day, record expenses, count at close, deposit to bank</div>
         </div>
         <div class="d-flex align-items-center gap-2">
            <span v-if="session" :class="['m-badge', 'm-badge--active']">Open since {{ formatTime(session.opened_at) }}</span>
            <span v-else class="m-badge m-badge--inactive">Closed</span>
            <button class="btn btn-outline-secondary btn-sm" @click="openExpenseModal"><i class="bi bi-receipt me-1"></i>Record Expense</button>
            <button v-if="!session" class="btn btn-danger btn-sm" @click="openOpenModal"><i class="bi bi-unlock me-1"></i>Open Day</button>
            <button v-else class="btn btn-danger btn-sm" @click="openCloseModal"><i class="bi bi-lock me-1"></i>Close Day</button>
         </div>
      </div>

      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4" v-if="loading">
         <div class="col-6 col-md-3" v-for="i in 4" :key="'sk-' + i">
            <div class="stat-card">
               <div class="skeleton-box rounded-circle flex-shrink-0" style="width: 40px; height: 40px"></div>
               <div class="stat-card-body">
                  <div class="skeleton-box mb-2" style="height: 11px; width: 65%"></div>
                  <div class="skeleton-box" style="height: 18px; width: 40%"></div>
               </div>
            </div>
         </div>
      </div>
      <div class="row g-3 mb-2" v-else-if="session">
         <div class="col-6 col-md-3" v-for="card in statCards" :key="card.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="card.iconBg"><i class="bi" :class="[card.icon, card.iconColor]"></i></div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ card.label }}</div>
                  <div class="stat-card-value">₱{{ $filters.formatMoney(card.value) }}</div>
               </div>
            </div>
         </div>
      </div>
      <div v-else-if="!loading" class="panel-card p-4 text-center text-muted mb-4">
         <i class="bi bi-safe fs-1 d-block mb-2 opacity-25"></i>
         <div>The drawer is closed. Open the day to start tracking cash.</div>
         <div class="small mt-1" v-if="suggestedFloat !== null">Suggested opening cash from last close: <strong>₱{{ $filters.formatMoney(suggestedFloat) }}</strong></div>
      </div>

      <div v-if="!loading && unassignedTodayTotal !== 0" class="text-muted small mb-3">
         <i class="bi bi-info-circle me-1"></i>₱{{ $filters.formatMoney(Math.abs(unassignedTodayTotal)) }} in cash movements today happened while the drawer was closed. They are kept in the records but not counted in expected cash.
      </div>

      <!-- Session entries -->
      <div class="panel-card mb-4" v-if="session">
         <div class="panel-card-header">
            <div>
               <div class="panel-card-title">Today's Cash Movements</div>
               <div class="panel-card-sub">Everything in and out of the drawer this session</div>
            </div>
         </div>
         <div class="panel-card-body">
            <div v-if="session.entries.length === 0" class="text-center py-4 text-muted">No cash movements yet.</div>
            <div v-else class="table-responsive">
               <table class="table table-striped table-hover align-middle mb-0">
                  <thead class="table-light">
                     <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>By</th>
                        <th class="text-end">Amount</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="entry in session.entries" :key="entry.id">
                        <td class="small">{{ formatTime(entry.occurred_at) }}</td>
                        <td>
                           <span :class="['m-badge', typeBadge(entry.type)]">{{ typeLabel(entry.type) }}</span>
                           <span v-if="entry.category" class="text-muted small ms-1">{{ categoryLabel(entry.category) }}</span>
                        </td>
                        <td class="small">
                           {{ entry.description }}
                           <a v-if="entry.receipt_url" :href="entry.receipt_url" target="_blank" class="ms-1 text-decoration-none"><i class="bi bi-paperclip"></i>Receipt</a>
                           <div v-if="entry.notes" class="text-muted" style="font-size: 0.75rem">{{ entry.notes }}</div>
                        </td>
                        <td class="small text-muted">{{ entry.recorded_by_name || "-" }}</td>
                        <td class="text-end small fw-bold" :class="entry.amount < 0 ? 'text-danger' : 'text-success'">{{ entry.amount < 0 ? "-" : "+" }}₱{{ $filters.formatMoney(Math.abs(entry.amount)) }}</td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>
      </div>

      <div class="row g-4">
         <!-- Past sessions -->
         <div class="col-lg-8">
            <div class="panel-card h-100">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Day History</div>
                     <div class="panel-card-sub">Closed drawer sessions with over/short results</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <div v-if="sessions.length === 0" class="text-center py-4 text-muted">No closed sessions yet.</div>
                  <div v-else class="table-responsive">
                     <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                           <tr>
                              <th>Date</th>
                              <th class="text-end">Opening</th>
                              <th class="text-end">Expected</th>
                              <th class="text-end">Counted</th>
                              <th class="text-end">Over/Short</th>
                              <th class="text-end">Deposited</th>
                              <th>Closed By</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-for="row in sessions" :key="row.id">
                              <td class="small fw-semibold">{{ formatDate(row.closed_at) }}</td>
                              <td class="text-end small">₱{{ $filters.formatMoney(row.opening_float) }}</td>
                              <td class="text-end small">₱{{ $filters.formatMoney(row.expected_cash) }}</td>
                              <td class="text-end small">₱{{ $filters.formatMoney(row.counted_cash) }}</td>
                              <td class="text-end small">
                                 <span :class="['m-badge', overShortBadge(row.over_short)]">{{ overShortLabel(row.over_short) }}</span>
                              </td>
                              <td class="text-end small">
                                 ₱{{ $filters.formatMoney(row.deposited_amount) }}
                                 <div v-if="row.deposit_reference" class="text-muted" style="font-size: 0.72rem">{{ row.deposit_reference }}</div>
                              </td>
                              <td class="small text-muted">{{ row.closed_by_name || "-" }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <div v-if="sessionsPagination.lastPage > 1" class="d-flex justify-content-center pt-3 border-top">
                     <nav>
                        <ul class="pagination pagination-sm mb-0">
                           <li v-for="link in sessionsPagination.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                              <a class="page-link" href="#" @click.prevent="goToSessionsPage(link)" v-html="link.label"></a>
                           </li>
                        </ul>
                     </nav>
                  </div>
               </div>
            </div>
         </div>

         <!-- Expense summary -->
         <div class="col-lg-4">
            <div class="panel-card h-100">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Expenses by Category</div>
                     <div class="panel-card-sub">Monthly totals</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <input type="month" class="form-control form-control-sm mb-3" v-model="expenseMonth" @change="fetchExpenseSummary" />
                  <div v-if="expenseSummary.categories.length === 0" class="text-center py-4 text-muted small">No expenses recorded this month.</div>
                  <template v-else>
                     <div v-for="row in expenseSummary.categories" :key="row.category" class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <span>{{ categoryLabel(row.category) }}</span>
                        <span class="fw-bold text-danger">₱{{ $filters.formatMoney(row.total) }}</span>
                     </div>
                     <div class="d-flex justify-content-between align-items-center pt-2 fw-bold">
                        <span>Total</span>
                        <span class="text-danger">₱{{ $filters.formatMoney(expenseSummary.total) }}</span>
                     </div>
                  </template>
               </div>
            </div>
         </div>
      </div>

      <!-- Open Day modal -->
      <div class="modal fade" tabindex="-1" ref="openModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Open the Day</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="openError">{{ openError }}</div>
                  <label class="form-label form-label-sm">Opening Cash (₱) <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" v-model="openForm.opening_float" min="0" step="0.01" :class="{ 'is-invalid': openErrors.opening_float }" />
                  <div class="invalid-feedback" v-if="openErrors.opening_float">{{ openErrors.opening_float }}</div>
                  <div class="form-text">Count the physical cash in the drawer right now and enter it here.</div>
                  <label class="form-label form-label-sm mt-3">Notes</label>
                  <textarea class="form-control" rows="2" v-model="openForm.notes" placeholder="Optional"></textarea>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submitOpen"><span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>Open Day</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Record Expense modal -->
      <div class="modal fade" tabindex="-1" ref="expenseModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Record Expense</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="expenseError">{{ expenseError }}</div>
                  <div v-if="!session" class="alert alert-warning py-2 small">The drawer is closed. The expense will be saved, but it will not count toward any day's expected cash.</div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Category <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="expenseForm.category" :class="{ 'is-invalid': expenseErrors.category }">
                           <option v-for="cat in categories" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="expenseErrors.category">{{ expenseErrors.category }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="expenseForm.amount" min="0.01" step="0.01" :class="{ 'is-invalid': expenseErrors.amount }" />
                        <div class="invalid-feedback" v-if="expenseErrors.amount">{{ expenseErrors.amount }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="expenseForm.description" placeholder="e.g. Water refill, electric bill" :class="{ 'is-invalid': expenseErrors.description }" />
                        <div class="invalid-feedback" v-if="expenseErrors.description">{{ expenseErrors.description }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Receipt / Bill Photo</label>
                        <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" ref="receiptInput" @change="onReceiptChange" :class="{ 'is-invalid': expenseErrors.receipt }" />
                        <div class="invalid-feedback" v-if="expenseErrors.receipt">{{ expenseErrors.receipt }}</div>
                        <div class="form-text">Optional. Take a photo of the receipt or bill (JPG/PNG/WebP, up to 5MB).</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <input type="text" class="form-control" v-model="expenseForm.notes" placeholder="Optional notes" />
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submitExpense"><span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>Record</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Close Day modal -->
      <div class="modal fade" tabindex="-1" ref="closeModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Close the Day</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="session">
                  <div class="alert alert-danger py-2 small" v-if="closeError">{{ closeError }}</div>
                  <div class="p-3 rounded bg-body-secondary border d-flex justify-content-between align-items-center mb-3">
                     <span class="text-muted">Expected cash in drawer</span>
                     <span class="fw-bold fs-5">₱{{ $filters.formatMoney(session.expected_cash) }}</span>
                  </div>
                  <label class="form-label form-label-sm">Counted Cash (₱) <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" v-model="closeForm.counted_cash" min="0" step="0.01" :class="{ 'is-invalid': closeErrors.counted_cash }" />
                  <div class="invalid-feedback" v-if="closeErrors.counted_cash">{{ closeErrors.counted_cash }}</div>
                  <div class="form-text">Count every bill and coin in the drawer and enter the total.</div>

                  <div v-if="closeForm.counted_cash !== ''" class="d-flex justify-content-between align-items-center p-2 mt-2 rounded border">
                     <span class="small text-muted">Difference</span>
                     <span :class="['m-badge', overShortBadge(liveOverShort)]">{{ overShortLabel(liveOverShort) }}</span>
                  </div>

                  <div class="row g-3 mt-1">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Deposit to Bank (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="closeForm.deposited_amount" min="0" step="0.01" :class="{ 'is-invalid': closeErrors.deposited_amount }" />
                        <div class="invalid-feedback" v-if="closeErrors.deposited_amount">{{ closeErrors.deposited_amount }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Deposit Reference</label>
                        <input type="text" class="form-control" v-model="closeForm.deposit_reference" placeholder="Optional slip / ref no." />
                     </div>
                  </div>
                  <div class="form-text mt-2" v-if="closeForm.counted_cash !== ''">Cash left in the drawer for tomorrow: <strong>₱{{ $filters.formatMoney(nextFloat) }}</strong></div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submitClose"><span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>Close Day</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDate, formatTime } from "../../dates";

export default {
   data: function () {
      return {
         loading: true,
         submitting: false,
         pageError: "",
         session: null,
         suggestedFloat: null,
         unassignedTodayTotal: 0,
         categories: [],
         sessions: [],
         sessionsPagination: { lastPage: 1, links: [] },
         expenseMonth: new Date().toISOString().slice(0, 7),
         expenseSummary: { categories: [], total: 0 },
         openForm: { opening_float: "", notes: "" },
         openError: "",
         openErrors: {},
         expenseForm: this.emptyExpenseForm(),
         receiptFile: null,
         expenseError: "",
         expenseErrors: {},
         closeForm: { counted_cash: "", deposited_amount: "", deposit_reference: "" },
         closeError: "",
         closeErrors: {},
         openModalInst: null,
         expenseModalInst: null,
         closeModalInst: null,
      };
   },

   mounted: function () {
      this.openModalInst = new Modal(this.$refs.openModal);
      this.expenseModalInst = new Modal(this.$refs.expenseModal);
      this.closeModalInst = new Modal(this.$refs.closeModal);
      this.fetchData();
      this.fetchSessions();
      this.fetchExpenseSummary();
   },

   computed: {
      statCards: function () {
         return [
            { label: "Opening Cash", value: this.session.opening_float, icon: "bi-unlock", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Cash In", value: this.session.cash_in, icon: "bi-arrow-down-circle", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Cash Out", value: this.session.cash_out, icon: "bi-arrow-up-circle", iconBg: "bg-danger-soft", iconColor: "text-danger" },
            { label: "Expected Cash Now", value: this.session.expected_cash, icon: "bi-safe", iconBg: "bg-warning-soft", iconColor: "text-warning" },
         ];
      },
      liveOverShort: function () {
         if (!this.session || this.closeForm.counted_cash === "") return 0;
         return Math.round((Number(this.closeForm.counted_cash) - this.session.expected_cash) * 100) / 100;
      },
      nextFloat: function () {
         return Math.max(0, Number(this.closeForm.counted_cash || 0) - Number(this.closeForm.deposited_amount || 0));
      },
   },

   methods: {
      formatDate,
      formatTime,
      emptyExpenseForm: function () {
         return { category: "supplies", amount: "", description: "", notes: "" };
      },
      typeLabel: function (type) {
         return (
            {
               sale: "Sale",
               sale_void: "Void Refund",
               expense: "Expense",
               payout: "Payroll Payout",
               adjustment: "Adjustment",
            }[type] || this.$filters.capitalize(type)
         );
      },
      typeBadge: function (type) {
         return (
            {
               sale: "m-badge--active",
               sale_void: "m-badge--suspended",
               expense: "m-badge--pending",
               payout: "m-badge--partial",
               adjustment: "m-badge--draft",
            }[type] || "m-badge--draft"
         );
      },
      categoryLabel: function (category) {
         return String(category || "")
            .split("_")
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(" ");
      },
      overShortLabel: function (value) {
         const amount = Number(value || 0);
         if (amount === 0) return "Balanced";
         return (amount > 0 ? "Over +₱" : "Short -₱") + this.$filters.formatMoney(Math.abs(amount));
      },
      overShortBadge: function (value) {
         const amount = Number(value || 0);
         if (amount === 0) return "m-badge--active";
         return amount > 0 ? "m-badge--pending" : "m-badge--suspended";
      },
      fetchData: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get("/panel/cash-drawer/data")
            .then((res) => {
               this.session = res.data.session;
               this.suggestedFloat = res.data.suggested_float;
               this.unassignedTodayTotal = Number(res.data.unassigned_today_total || 0);
               this.categories = res.data.categories || [];
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load the cash drawer."))
            .finally(() => (this.loading = false));
      },
      fetchSessions: function (page = 1) {
         axios.get("/panel/cash-drawer/sessions", { params: { page } }).then((res) => {
            this.sessions = res.data.data;
            this.sessionsPagination = { lastPage: res.data.last_page, links: res.data.links };
         });
      },
      goToSessionsPage: function (link) {
         if (!link.url) return;
         const page = parseInt(new URL(link.url).searchParams.get("page") || "1");
         this.fetchSessions(page);
      },
      fetchExpenseSummary: function () {
         axios.get("/panel/cash-drawer/expense-summary", { params: { month: this.expenseMonth } }).then((res) => {
            this.expenseSummary = res.data;
         });
      },
      openOpenModal: function () {
         this.openForm = { opening_float: this.suggestedFloat !== null ? this.suggestedFloat : "", notes: "" };
         this.openError = "";
         this.openErrors = {};
         this.openModalInst.show();
      },
      submitOpen: function () {
         this.submitting = true;
         this.openError = "";
         this.openErrors = {};
         axios
            .post("/panel/cash-drawer/open", this.openForm)
            .then(() => {
               this.openModalInst.hide();
               this.fetchData();
            })
            .catch((err) => this.captureErrors(err, "openError", "openErrors"))
            .finally(() => (this.submitting = false));
      },
      openExpenseModal: function () {
         this.expenseForm = this.emptyExpenseForm();
         this.receiptFile = null;
         if (this.$refs.receiptInput) this.$refs.receiptInput.value = "";
         this.expenseError = "";
         this.expenseErrors = {};
         this.expenseModalInst.show();
      },
      onReceiptChange: function (event) {
         this.receiptFile = event.target.files[0] || null;
      },
      submitExpense: function () {
         this.submitting = true;
         this.expenseError = "";
         this.expenseErrors = {};
         const payload = new FormData();
         payload.append("category", this.expenseForm.category);
         payload.append("description", this.expenseForm.description);
         payload.append("amount", this.expenseForm.amount);
         if (this.expenseForm.notes) payload.append("notes", this.expenseForm.notes);
         if (this.receiptFile) payload.append("receipt", this.receiptFile);
         axios
            .post("/panel/cash-drawer/expenses", payload)
            .then(() => {
               this.expenseModalInst.hide();
               this.fetchData();
               this.fetchExpenseSummary();
            })
            .catch((err) => this.captureErrors(err, "expenseError", "expenseErrors"))
            .finally(() => (this.submitting = false));
      },
      openCloseModal: function () {
         this.closeForm = { counted_cash: "", deposited_amount: "", deposit_reference: "" };
         this.closeError = "";
         this.closeErrors = {};
         this.closeModalInst.show();
      },
      submitClose: function () {
         this.submitting = true;
         this.closeError = "";
         this.closeErrors = {};
         axios
            .post("/panel/cash-drawer/close", this.closeForm)
            .then(() => {
               this.closeModalInst.hide();
               this.fetchData();
               this.fetchSessions();
            })
            .catch((err) => this.captureErrors(err, "closeError", "closeErrors"))
            .finally(() => (this.submitting = false));
      },
      captureErrors: function (err, messageKey, errorsKey) {
         if (err.response?.status === 422) {
            const errors = err.response.data.errors || {};
            this[errorsKey] = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
            this[messageKey] = "Please review the highlighted fields.";
         } else {
            this[messageKey] = err.response?.data?.message || "Something went wrong.";
         }
      },
   },
};
</script>
