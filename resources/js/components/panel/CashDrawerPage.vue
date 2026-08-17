<template>
   <div class="cash-drawer-page">
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
         <div>
            <h4 class="fw-bold mb-0">Cash Drawer</h4>
            <div class="text-muted small">Track drawer cash, record cash or online expenses, reconcile at close, and deposit to bank</div>
         </div>
         <div class="d-flex align-items-center gap-2">
            <span v-if="session" :class="['m-badge', 'm-badge--active']">Open since {{ formatTime(session.opened_at) }}</span>
            <span v-else class="m-badge m-badge--inactive">Closed</span>
            <button class="btn btn-outline-secondary btn-sm" @click="openExpenseModal" :disabled="!session" :title="session ? 'Record an expense' : 'Open the cash drawer before recording an expense'"><i class="bi bi-receipt me-1"></i>Record Expense</button>
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
               <div class="panel-card-title">Drawer Session Activity</div>
               <div class="panel-card-sub">Cash movements and online expenses recorded during this session</div>
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
                           <div class="text-muted small mt-1">{{ entry.payment_method_label || "Cash" }}</div>
                        </td>
                        <td class="small">
                           {{ entry.description }}
                           <a v-if="entry.receipt_url" :href="entry.receipt_url" target="_blank" class="ms-1 text-decoration-none"><i class="bi bi-paperclip"></i>Receipt</a>
                           <div v-if="entry.notes" class="text-muted" style="font-size: 0.75rem">{{ entry.notes }}</div>
                        </td>
                        <td class="small text-muted">{{ entry.recorded_by_name || "-" }}</td>
                        <td class="text-end small fw-bold" :class="entry.affects_cash ? (entry.amount < 0 ? 'text-danger' : 'text-success') : 'text-body'">
                           {{ entry.affects_cash ? (entry.amount < 0 ? "-" : "+") : "" }}₱{{ $filters.formatMoney(Math.abs(entry.amount)) }}
                           <div v-if="!entry.affects_cash" class="text-muted fw-normal" style="font-size: 0.72rem">No cash effect</div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>
      </div>

      <!-- Past sessions -->
      <div class="panel-card mb-4">
         <div class="panel-card-header flex-column flex-md-row align-items-md-center">
            <div class="d-flex align-items-center gap-3">
               <div class="history-heading-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></div>
               <div>
                  <div class="panel-card-title">
                     Drawer History
                     <span v-if="!loadingSessions" class="badge-count ms-1">{{ sessionsPagination.total }}</span>
                  </div>
                  <div class="panel-card-sub">Review previous cash counts, deposits, and reconciliation results</div>
               </div>
            </div>
            <div v-if="!loadingSessions && sessionsPagination.total > 0" class="small text-muted">
               Showing {{ sessionsPagination.from }}–{{ sessionsPagination.to }} of {{ sessionsPagination.total }} sessions
            </div>
         </div>

         <div v-if="loadingSessions">
            <div class="table-responsive d-none d-lg-block">
               <table class="table align-middle mb-0 panel-table history-table">
                  <thead>
                     <tr>
                        <th v-for="heading in historyHeadings" :key="heading">{{ heading }}</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="index in 5" :key="'history-sk-' + index">
                        <td><div class="skeleton-box history-skeleton history-skeleton--date"></div></td>
                        <td><div class="skeleton-box history-skeleton history-skeleton--amount"></div></td>
                        <td><div class="skeleton-box history-skeleton history-skeleton--reconciliation"></div></td>
                        <td><div class="skeleton-box history-skeleton history-skeleton--badge"></div></td>
                        <td><div class="skeleton-box history-skeleton history-skeleton--amount"></div></td>
                        <td><div class="skeleton-box history-skeleton history-skeleton--person"></div></td>
                        <td><div class="skeleton-box history-skeleton history-skeleton--button ms-auto"></div></td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <div class="d-lg-none">
               <div v-for="index in 3" :key="'history-mobile-sk-' + index" class="drawer-history-card">
                  <div class="d-flex justify-content-between mb-3">
                     <div class="skeleton-box history-skeleton history-skeleton--date"></div>
                     <div class="skeleton-box history-skeleton history-skeleton--badge"></div>
                  </div>
                  <div class="history-metric-grid">
                     <div v-for="metric in 3" :key="metric" class="history-metric"><div class="skeleton-box history-skeleton history-skeleton--amount"></div></div>
                  </div>
               </div>
            </div>
         </div>

         <div v-else-if="sessionsError" class="text-center py-5 px-3">
            <i class="bi bi-exclamation-circle fs-2 d-block mb-2 text-danger opacity-75"></i>
            <div class="fw-semibold">History could not be loaded</div>
            <div class="small text-muted mb-3">{{ sessionsError }}</div>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="fetchSessions()"><i class="bi bi-arrow-clockwise me-1"></i>Try Again</button>
         </div>

         <div v-else-if="sessions.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-archive fs-1 d-block mb-2 opacity-25"></i>
            <div class="fw-semibold text-body">No closed sessions yet</div>
            <div class="small">Completed drawer sessions will appear here for review.</div>
         </div>

         <template v-else>
            <div class="table-responsive d-none d-lg-block">
               <table class="table table-hover align-middle mb-0 panel-table history-table">
                  <thead>
                     <tr>
                        <th scope="col">Session Period</th>
                        <th scope="col">Opening Float</th>
                        <th scope="col">Reconciliation</th>
                        <th scope="col">Result</th>
                        <th scope="col">Bank Deposit</th>
                        <th scope="col">Handled By</th>
                        <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="row in sessions" :key="row.id">
                        <td>
                           <div class="session-period">
                              <div class="session-period-event">
                                 <span class="session-period-dot session-period-dot--open" aria-hidden="true"></span>
                                 <div>
                                    <span class="session-period-label">Opened</span>
                                    <div class="fw-semibold">{{ formatDate(row.opened_at) }}</div>
                                    <div class="small text-muted">{{ formatTime(row.opened_at) }}</div>
                                 </div>
                              </div>
                              <div class="session-period-event">
                                 <span class="session-period-dot session-period-dot--closed" aria-hidden="true"></span>
                                 <div>
                                    <span class="session-period-label">Closed</span>
                                    <div class="fw-semibold">{{ formatDate(row.closed_at) }}</div>
                                    <div class="small text-muted">{{ formatTime(row.closed_at) }}</div>
                                 </div>
                              </div>
                           </div>
                           <span v-if="spansMultipleDays(row)" class="m-badge m-badge--pending mt-2"><i class="bi bi-moon-stars" aria-hidden="true"></i>Carried overnight</span>
                        </td>
                        <td class="fw-semibold">₱{{ $filters.formatMoney(row.opening_float) }}</td>
                        <td>
                           <div class="reconciliation-values">
                              <div>
                                 <span class="reconciliation-label">Expected</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.expected_cash) }}</span>
                              </div>
                              <i class="bi bi-arrow-right text-muted" aria-hidden="true"></i>
                              <div>
                                 <span class="reconciliation-label">Counted</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.counted_cash) }}</span>
                              </div>
                           </div>
                        </td>
                        <td><span :class="['m-badge', overShortBadge(row.over_short)]">{{ overShortLabel(row.over_short) }}</span></td>
                        <td>
                           <div class="fw-semibold">₱{{ $filters.formatMoney(row.deposited_amount) }}</div>
                           <div class="small text-muted">{{ row.deposit_reference || "No reference" }}</div>
                        </td>
                        <td>
                           <div class="session-actor">
                              <span>Opened by</span>
                              <strong>{{ row.opened_by_name || "-" }}</strong>
                           </div>
                           <div class="session-actor mt-2">
                              <span>Closed by</span>
                              <strong>{{ row.closed_by_name || "-" }}</strong>
                           </div>
                        </td>
                        <td class="text-end">
                           <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" :aria-label="'Review cash drawer session opened on ' + formatDate(row.opened_at)" @click="viewSession(row)">
                              Review <i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>
                           </button>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-lg-none">
               <article v-for="row in sessions" :key="'mobile-history-' + row.id" class="drawer-history-card">
                  <div class="d-flex align-items-start justify-content-between gap-3">
                     <div>
                        <div class="fw-bold">{{ sessionPeriodLabel(row) }}</div>
                        <span v-if="spansMultipleDays(row)" class="m-badge m-badge--pending mt-2"><i class="bi bi-moon-stars" aria-hidden="true"></i>Carried overnight</span>
                     </div>
                     <span :class="['m-badge', overShortBadge(row.over_short)]">{{ overShortLabel(row.over_short) }}</span>
                  </div>
                  <div class="mobile-session-events">
                     <div>
                        <span>Opened</span>
                        <strong>{{ formatTime(row.opened_at) }}</strong>
                        <small>by {{ row.opened_by_name || "-" }}</small>
                     </div>
                     <i class="bi bi-arrow-right text-muted" aria-hidden="true"></i>
                     <div>
                        <span>Closed</span>
                        <strong>{{ formatTime(row.closed_at) }}</strong>
                        <small>by {{ row.closed_by_name || "-" }}</small>
                     </div>
                  </div>
                  <div class="history-metric-grid">
                     <div class="history-metric">
                        <span>Opening</span>
                        <strong>₱{{ $filters.formatMoney(row.opening_float) }}</strong>
                     </div>
                     <div class="history-metric">
                        <span>Counted</span>
                        <strong>₱{{ $filters.formatMoney(row.counted_cash) }}</strong>
                     </div>
                     <div class="history-metric">
                        <span>Deposited</span>
                        <strong>₱{{ $filters.formatMoney(row.deposited_amount) }}</strong>
                     </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-3 pt-3 border-top">
                     <div class="small text-muted">
                        <div>Expected ₱{{ $filters.formatMoney(row.expected_cash) }}</div>
                        <div>{{ row.deposit_reference || "No deposit reference" }}</div>
                     </div>
                     <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" @click="viewSession(row)">Review <i class="bi bi-chevron-right ms-1"></i></button>
                  </div>
               </article>
            </div>
         </template>

         <div v-if="!loadingSessions && !sessionsError && sessionsPagination.lastPage > 1" class="d-flex justify-content-center py-3 border-top">
            <panel-pagination :links="sessionsPagination.links" :current-page="sessionsPagination.currentPage" :last-page="sessionsPagination.lastPage" aria-label="Cash drawer history pagination" @page-change="fetchSessions" />
         </div>
      </div>

      <!-- Expense summary -->
      <div class="panel-card">
         <div class="panel-card-header flex-column flex-sm-row align-items-sm-center">
            <div>
               <div class="panel-card-title">Expenses by Category</div>
               <div class="panel-card-sub">Monthly expenses across cash and online payments</div>
            </div>
            <input type="month" class="form-control form-control-sm expense-month" v-model="expenseMonth" aria-label="Expense summary month" @change="fetchExpenseSummary" />
         </div>
         <div class="panel-card-body">
            <div v-if="expenseSummary.categories.length === 0" class="text-center py-4 text-muted small">No expenses recorded this month.</div>
            <template v-else>
               <div class="expense-summary-grid">
                  <div v-for="row in expenseSummary.categories" :key="row.category" class="expense-summary-item">
                     <span class="text-muted small">{{ categoryLabel(row.category) }}</span>
                     <span class="fw-bold text-danger">₱{{ $filters.formatMoney(row.total) }}</span>
                  </div>
               </div>
               <div class="d-flex justify-content-between align-items-center pt-3 mt-3 border-top fw-bold">
                  <span>Monthly Total</span>
                  <span class="text-danger fs-5">₱{{ $filters.formatMoney(expenseSummary.total) }}</span>
               </div>
            </template>
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
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Category <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="expenseForm.category" :class="{ 'is-invalid': expenseErrors.category }">
                           <option v-for="cat in categories" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="expenseErrors.category">{{ expenseErrors.category }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="expenseForm.payment_method" :class="{ 'is-invalid': expenseErrors.payment_method }">
                           <option value="cash">Cash</option>
                           <option value="online_payment">Online Payment</option>
                        </select>
                        <div class="invalid-feedback" v-if="expenseErrors.payment_method">{{ expenseErrors.payment_method }}</div>
                        <div class="form-text" v-if="expenseForm.payment_method === 'online_payment'">This expense will not reduce expected physical cash.</div>
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

      <!-- Session Detail modal -->
      <div class="modal fade" tabindex="-1" ref="sessionModal" aria-labelledby="sessionDetailTitle" aria-hidden="true">
         <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <div>
                     <h5 id="sessionDetailTitle" class="modal-title fw-bold">Cash Drawer Reconciliation</h5>
                     <div v-if="selectedSession" class="modal-session-period mt-1">
                        <span><strong>Opened</strong> {{ formatDate(selectedSession.opened_at) }} at {{ formatTime(selectedSession.opened_at) }}</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        <span><strong>Closed</strong> {{ formatDate(selectedSession.closed_at) }} at {{ formatTime(selectedSession.closed_at) }}</span>
                        <span v-if="spansMultipleDays(selectedSession)" class="m-badge m-badge--pending"><i class="bi bi-moon-stars" aria-hidden="true"></i>Carried overnight</span>
                     </div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
               </div>
               <div v-if="selectedSession" class="modal-body p-0">
                  <div class="session-detail-summary">
                     <div class="session-detail-meta">
                        <div>
                           <span>Opened by</span>
                           <strong>{{ selectedSession.opened_by_name || "-" }}</strong>
                        </div>
                        <div>
                           <span>Closed by</span>
                           <strong>{{ selectedSession.closed_by_name || "-" }}</strong>
                        </div>
                        <div v-if="selectedSession.deposit_reference">
                           <span>Deposit reference</span>
                           <strong>{{ selectedSession.deposit_reference }}</strong>
                        </div>
                     </div>
                     <div class="session-summary-grid">
                        <div class="session-summary-item">
                           <span>Opening Float</span>
                           <strong>₱{{ $filters.formatMoney(selectedSession.opening_float) }}</strong>
                        </div>
                        <div class="session-summary-item">
                           <span>Expected Cash</span>
                           <strong>₱{{ $filters.formatMoney(selectedSession.expected_cash) }}</strong>
                        </div>
                        <div class="session-summary-item">
                           <span>Counted Cash</span>
                           <strong>₱{{ $filters.formatMoney(selectedSession.counted_cash) }}</strong>
                        </div>
                        <div class="session-summary-item">
                           <span>Bank Deposit</span>
                           <strong>₱{{ $filters.formatMoney(selectedSession.deposited_amount) }}</strong>
                        </div>
                        <div class="session-summary-item session-summary-item--result">
                           <span>Reconciliation</span>
                           <strong><span :class="['m-badge', overShortBadge(selectedSession.over_short)]">{{ overShortLabel(selectedSession.over_short) }}</span></strong>
                        </div>
                     </div>
                     <div v-if="selectedSession.notes" class="session-notes"><i class="bi bi-journal-text me-2"></i>{{ selectedSession.notes }}</div>
                  </div>

                  <div class="d-flex align-items-center justify-content-between gap-2 px-3 px-md-4 py-3 border-bottom">
                     <div>
                        <div class="fw-bold">Cash Movements</div>
                        <div class="small text-muted">All transactions recorded during this drawer session</div>
                     </div>
                     <span v-if="!selectedSessionLoading && !selectedSessionError" class="badge-count">{{ selectedEntriesPagination.total }}</span>
                  </div>

                  <div v-if="selectedSessionLoading" class="py-5 text-center text-muted">
                     <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading cash movements…
                  </div>
                  <div v-else-if="selectedSessionError" class="text-center py-5 px-3">
                     <i class="bi bi-exclamation-circle fs-2 d-block mb-2 text-danger opacity-75"></i>
                     <div class="fw-semibold">Cash movements could not be loaded</div>
                     <div class="small text-muted mb-3">{{ selectedSessionError }}</div>
                     <button type="button" class="btn btn-outline-secondary btn-sm" @click="fetchSessionEntries()"><i class="bi bi-arrow-clockwise me-1"></i>Try Again</button>
                  </div>
                  <div v-else-if="selectedSessionEntries.length === 0" class="text-center py-5 text-muted">
                     <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>
                     <div>No cash movements were recorded for this session.</div>
                  </div>
                  <template v-else>
                     <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0 panel-table session-entry-table">
                           <thead>
                              <tr>
                                 <th scope="col">Time</th>
                                 <th scope="col">Type</th>
                                 <th scope="col">Description</th>
                                 <th scope="col">Recorded By</th>
                                 <th scope="col" class="text-end">Amount</th>
                              </tr>
                           </thead>
                           <tbody>
                              <tr v-for="entry in selectedSessionEntries" :key="entry.id">
                                 <td class="small text-muted">{{ formatTime(entry.occurred_at) }}</td>
                                 <td>
                                    <span :class="['m-badge', typeBadge(entry.type)]">{{ typeLabel(entry.type) }}</span>
                                    <div v-if="entry.category" class="small text-muted mt-1">{{ categoryLabel(entry.category) }}</div>
                                    <div class="small text-muted mt-1">{{ entry.payment_method_label || "Cash" }}</div>
                                 </td>
                                 <td class="small">
                                    <div class="fw-semibold">{{ entry.description }}</div>
                                    <div v-if="entry.notes" class="text-muted">{{ entry.notes }}</div>
                                    <a v-if="entry.receipt_url" :href="entry.receipt_url" target="_blank" rel="noopener" class="d-inline-block mt-1 text-decoration-none"><i class="bi bi-paperclip me-1"></i>View receipt</a>
                                 </td>
                                 <td class="small text-muted">{{ entry.recorded_by_name || "-" }}</td>
                                 <td class="text-end fw-bold" :class="entry.affects_cash ? (entry.amount < 0 ? 'text-danger' : 'text-success') : 'text-body'">
                                    {{ entry.affects_cash ? (entry.amount < 0 ? "−" : "+") : "" }}₱{{ $filters.formatMoney(Math.abs(entry.amount)) }}
                                    <div v-if="!entry.affects_cash" class="small text-muted fw-normal">No cash effect</div>
                                 </td>
                              </tr>
                           </tbody>
                        </table>
                     </div>
                     <div class="d-md-none">
                        <article v-for="entry in selectedSessionEntries" :key="'mobile-entry-' + entry.id" class="session-entry-card">
                           <div class="d-flex align-items-start justify-content-between gap-3">
                              <div>
                                 <span :class="['m-badge', typeBadge(entry.type)]">{{ typeLabel(entry.type) }}</span>
                                 <span v-if="entry.category" class="small text-muted ms-1">{{ categoryLabel(entry.category) }}</span>
                                 <div class="small text-muted mt-1">{{ entry.payment_method_label || "Cash" }}</div>
                              </div>
                              <div class="fw-bold text-nowrap" :class="entry.affects_cash ? (entry.amount < 0 ? 'text-danger' : 'text-success') : 'text-body'">
                                 {{ entry.affects_cash ? (entry.amount < 0 ? "−" : "+") : "" }}₱{{ $filters.formatMoney(Math.abs(entry.amount)) }}
                                 <div v-if="!entry.affects_cash" class="small text-muted fw-normal">No cash effect</div>
                              </div>
                           </div>
                           <div class="fw-semibold mt-3">{{ entry.description }}</div>
                           <div v-if="entry.notes" class="small text-muted mt-1">{{ entry.notes }}</div>
                           <div class="d-flex justify-content-between gap-2 small text-muted mt-3 pt-3 border-top">
                              <span>{{ formatTime(entry.occurred_at) }} · {{ entry.recorded_by_name || "-" }}</span>
                              <a v-if="entry.receipt_url" :href="entry.receipt_url" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-paperclip me-1"></i>Receipt</a>
                           </div>
                        </article>
                     </div>
                  </template>

                  <div v-if="!selectedSessionLoading && !selectedSessionError && selectedEntriesPagination.lastPage > 1" class="d-flex justify-content-center py-3 border-top">
                     <panel-pagination :links="selectedEntriesPagination.links" :current-page="selectedEntriesPagination.currentPage" :last-page="selectedEntriesPagination.lastPage" aria-label="Cash movement pagination" @page-change="fetchSessionEntries" />
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { daysBetween, formatDate, formatTime } from "../../dates";

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
         loadingSessions: true,
         sessionsError: "",
         historyHeadings: ["Session Period", "Opening Float", "Reconciliation", "Result", "Bank Deposit", "Handled By", ""],
         sessionsPagination: { currentPage: 1, lastPage: 1, links: [], total: 0, from: 0, to: 0 },
         selectedSession: null,
         selectedSessionEntries: [],
         selectedSessionLoading: false,
         selectedSessionError: "",
         selectedEntriesPagination: { currentPage: 1, lastPage: 1, links: [], total: 0 },
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
         sessionModalInst: null,
      };
   },

   mounted: function () {
      this.openModalInst = new Modal(this.$refs.openModal);
      this.expenseModalInst = new Modal(this.$refs.expenseModal);
      this.closeModalInst = new Modal(this.$refs.closeModal);
      this.sessionModalInst = new Modal(this.$refs.sessionModal);
      this.fetchData();
      this.fetchSessions();
      this.fetchExpenseSummary();
   },

   beforeUnmount: function () {
      this.openModalInst?.dispose();
      this.expenseModalInst?.dispose();
      this.closeModalInst?.dispose();
      this.sessionModalInst?.dispose();
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
         return { category: "supplies", payment_method: "cash", amount: "", description: "", notes: "" };
      },
      typeLabel: function (type) {
         return (
            {
               sale: "Sale",
               sale_void: "Void Refund",
               expense: "Expense",
               payout: "Payroll Payout",
               cash_advance: "Cash Advance",
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
               cash_advance: "m-badge--partial",
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
      spansMultipleDays: function (session) {
         return (daysBetween(session.opened_at, session.closed_at) || 0) > 0;
      },
      sessionPeriodLabel: function (session) {
         const openedDate = formatDate(session.opened_at);
         const closedDate = formatDate(session.closed_at);
         return openedDate === closedDate ? openedDate : `${openedDate} – ${closedDate}`;
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
         this.loadingSessions = true;
         this.sessionsError = "";
         axios
            .get("/panel/cash-drawer/sessions", { params: { page } })
            .then((res) => {
               this.sessions = res.data.data;
               this.sessionsPagination = {
                  currentPage: res.data.current_page,
                  lastPage: res.data.last_page,
                  links: res.data.links,
                  total: res.data.total,
                  from: res.data.from || 0,
                  to: res.data.to || 0,
               };
            })
            .catch((err) => {
               this.sessionsError = err.response?.data?.message || "Please check your connection and try again.";
            })
            .finally(() => (this.loadingSessions = false));
      },
      viewSession: function (row) {
         this.selectedSession = row;
         this.selectedSessionEntries = [];
         this.selectedEntriesPagination = { currentPage: 1, lastPage: 1, links: [], total: 0 };
         this.sessionModalInst.show();
         this.fetchSessionEntries();
      },
      fetchSessionEntries: function (page = 1) {
         if (!this.selectedSession) return;
         this.selectedSessionLoading = true;
         this.selectedSessionError = "";
         axios
            .get("/panel/cash-drawer/entries", { params: { session_id: this.selectedSession.id, page } })
            .then((res) => {
               this.selectedSessionEntries = res.data.data;
               this.selectedEntriesPagination = {
                  currentPage: res.data.current_page,
                  lastPage: res.data.last_page,
                  links: res.data.links,
                  total: res.data.total,
               };
            })
            .catch((err) => {
               this.selectedSessionError = err.response?.data?.message || "Please check your connection and try again.";
            })
            .finally(() => (this.selectedSessionLoading = false));
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
         if (!this.session) return;
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
         payload.append("payment_method", this.expenseForm.payment_method);
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

<style scoped>
.history-heading-icon {
   width: 2.5rem;
   height: 2.5rem;
   border-radius: 0.75rem;
   display: inline-flex;
   align-items: center;
   justify-content: center;
   flex-shrink: 0;
   background: rgba(220, 53, 69, 0.12);
   color: #dc3545;
   font-size: 1.1rem;
}

.history-table th {
   white-space: nowrap;
}

.history-table tbody tr:last-child td {
   border-bottom: 0;
}

.reconciliation-values {
   display: flex;
   align-items: center;
   gap: 0.75rem;
   white-space: nowrap;
}

.reconciliation-values > div {
   display: flex;
   flex-direction: column;
}

.reconciliation-label {
   color: var(--bs-secondary-color);
   font-size: 0.68rem;
   line-height: 1.2;
   text-transform: uppercase;
   letter-spacing: 0.04em;
}

.session-period {
   position: relative;
   display: grid;
   gap: 0.65rem;
   min-width: 10.5rem;
}

.session-period::before {
   content: "";
   position: absolute;
   top: 0.55rem;
   bottom: 0.55rem;
   left: 0.25rem;
   width: 1px;
   background: var(--bs-border-color);
}

.session-period-event {
   position: relative;
   display: flex;
   align-items: flex-start;
   gap: 0.65rem;
}

.session-period-dot {
   width: 0.55rem;
   height: 0.55rem;
   margin-top: 0.25rem;
   border: 2px solid var(--bs-body-bg);
   border-radius: 999px;
   flex-shrink: 0;
   box-shadow: 0 0 0 1px var(--bs-border-color);
   z-index: 1;
}

.session-period-dot--open {
   background: #198754;
}

.session-period-dot--closed {
   background: #6c757d;
}

.session-period-label,
.session-actor > span,
.mobile-session-events span {
   display: block;
   color: var(--bs-secondary-color);
   font-size: 0.65rem;
   line-height: 1.2;
   text-transform: uppercase;
   letter-spacing: 0.04em;
}

.session-actor > strong {
   display: block;
   margin-top: 0.1rem;
   font-size: 0.82rem;
}

.mobile-session-events {
   display: grid;
   grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
   align-items: center;
   gap: 0.75rem;
   margin: 1rem 0;
   padding: 0.75rem;
   border-radius: 0.5rem;
   background: var(--bs-tertiary-bg);
}

.mobile-session-events > div {
   min-width: 0;
}

.mobile-session-events strong,
.mobile-session-events small {
   display: block;
   overflow-wrap: anywhere;
}

.mobile-session-events strong {
   margin-top: 0.2rem;
}

.mobile-session-events small {
   color: var(--bs-secondary-color);
}

.modal-session-period {
   display: flex;
   flex-wrap: wrap;
   align-items: center;
   gap: 0.35rem 0.6rem;
   color: var(--bs-secondary-color);
   font-size: 0.75rem;
}

.drawer-history-card,
.session-entry-card {
   padding: 1rem;
   border-bottom: 1px solid var(--bs-border-color);
}

.drawer-history-card:last-child,
.session-entry-card:last-child {
   border-bottom: 0;
}

.history-metric-grid {
   display: grid;
   grid-template-columns: repeat(3, minmax(0, 1fr));
   gap: 0.5rem;
   margin: 1rem 0;
}

.history-metric {
   min-width: 0;
   padding: 0.65rem;
   border-radius: 0.5rem;
   background: var(--bs-tertiary-bg);
}

.history-metric > span,
.history-metric > strong {
   display: block;
   overflow-wrap: anywhere;
}

.history-metric > span {
   margin-bottom: 0.15rem;
   color: var(--bs-secondary-color);
   font-size: 0.65rem;
   text-transform: uppercase;
   letter-spacing: 0.04em;
}

.history-metric > strong {
   font-size: 0.82rem;
}

.history-skeleton {
   height: 0.875rem;
   border-radius: 0.25rem;
}

.history-skeleton--date {
   width: 7.5rem;
}

.history-skeleton--amount {
   width: 5rem;
}

.history-skeleton--reconciliation {
   width: 11rem;
}

.history-skeleton--badge {
   width: 5.5rem;
   height: 1.5rem;
   border-radius: 999px;
}

.history-skeleton--person {
   width: 6.5rem;
}

.history-skeleton--button {
   width: 4.75rem;
   height: 2rem;
   border-radius: 0.375rem;
}

.expense-month {
   width: auto;
   min-width: 10rem;
}

.expense-summary-grid {
   display: grid;
   grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
   gap: 0.75rem;
}

.expense-summary-item {
   display: flex;
   align-items: center;
   justify-content: space-between;
   gap: 1rem;
   padding: 0.75rem 0.875rem;
   border: 1px solid var(--bs-border-color);
   border-radius: 0.5rem;
   background: var(--bs-tertiary-bg);
}

.session-detail-summary {
   padding: 1.25rem 1.5rem;
   border-bottom: 1px solid var(--bs-border-color);
   background: var(--bs-tertiary-bg);
}

.session-detail-meta {
   display: flex;
   flex-wrap: wrap;
   gap: 0.75rem 2rem;
   margin-bottom: 1rem;
}

.session-detail-meta > div {
   display: flex;
   flex-direction: column;
}

.session-detail-meta span,
.session-summary-item > span {
   color: var(--bs-secondary-color);
   font-size: 0.68rem;
   text-transform: uppercase;
   letter-spacing: 0.05em;
}

.session-detail-meta strong {
   font-size: 0.85rem;
}

.session-summary-grid {
   display: grid;
   grid-template-columns: repeat(5, minmax(0, 1fr));
   gap: 0.75rem;
}

.session-summary-item {
   min-width: 0;
   padding: 0.875rem;
   border: 1px solid var(--bs-border-color);
   border-radius: 0.5rem;
   background: var(--bs-body-bg);
}

.session-summary-item > span,
.session-summary-item > strong {
   display: block;
}

.session-summary-item > strong {
   margin-top: 0.25rem;
   font-size: 1rem;
   overflow-wrap: anywhere;
}

.session-summary-item--result > strong {
   margin-top: 0.4rem;
}

.session-notes {
   margin-top: 1rem;
   padding: 0.75rem 0.875rem;
   border-left: 3px solid var(--bs-secondary-color);
   border-radius: 0.25rem;
   background: var(--bs-body-bg);
   color: var(--bs-secondary-color);
   font-size: 0.82rem;
}

.session-entry-table th:last-child,
.session-entry-table td:last-child {
   white-space: nowrap;
}

@media (max-width: 767.98px) {
   .expense-month {
      width: 100%;
   }

   .session-detail-summary {
      padding: 1rem;
   }

   .session-summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
   }

   .session-summary-item--result {
      grid-column: 1 / -1;
   }
}

@media (max-width: 400px) {
   .history-metric-grid {
      grid-template-columns: 1fr;
   }
}
</style>
