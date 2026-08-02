<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Summary -->
      <div class="d-flex align-items-center justify-content-between mb-3">
         <template v-if="loading">
            <div class="skeleton-box" style="height: 14px; width: 80px; border-radius: 4px"></div>
         </template>
         <template v-else>
            <div class="text-muted small">{{ advances.length }} cash advance{{ advances.length !== 1 ? "s" : "" }}</div>
            <div class="d-flex gap-3 align-items-center">
               <div class="fw-semibold small" v-if="advances.length">Total: ₱{{ $filters.formatMoney(totalAdvanced) }}</div>
               <div class="fw-semibold small text-danger" v-if="totalOutstanding > 0">Outstanding: ₱{{ $filters.formatMoney(totalOutstanding) }}</div>
               <button class="btn btn-primary btn-sm" v-if="canManage" @click="openModal"><i class="bi bi-plus-lg me-1"></i>Record Advance</button>
            </div>
         </template>
      </div>

      <!-- Loading -->
      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 4" :key="i" style="height: 48px; border-radius: 6px; margin-bottom: 8px"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="advances.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-cash-coin fs-1 d-block mb-2 opacity-25"></i>
         <div>No cash advances recorded yet.</div>
      </div>

      <!-- Desktop table -->
      <div class="d-none d-md-block" v-else>
         <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                  <th>Given At</th>
                  <th>Method</th>
                  <th>Reference</th>
                  <th class="text-end fw-bold">Amount</th>
                  <th class="text-end">Repaid</th>
                  <th class="text-end">Balance</th>
                  <th>Released By</th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="a in advances" :key="a.id">
                  <td class="small">{{ formatDateTime(a.paid_at) }}</td>
                  <td>
                     <span class="m-badge" :class="$filters.statusBadge(a.method)">{{ a.method_label }}</span>
                  </td>
                  <td class="small text-muted">{{ a.reference_number || "-" }}</td>
                  <td class="text-end fw-bold small">₱{{ $filters.formatMoney(a.amount) }}</td>
                  <td class="text-end text-success small">₱{{ $filters.formatMoney(a.repaid_amount) }}</td>
                  <td class="text-end small" :class="a.balance > 0 ? 'text-danger fw-bold' : 'text-muted'">₱{{ $filters.formatMoney(a.balance) }}</td>
                  <td class="small text-muted">{{ a.released_by_name || "-" }}</td>
               </tr>
            </tbody>
         </table>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none" v-if="!loading && advances.length">
         <div class="member-card" v-for="a in advances" :key="'ca' + a.id">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold small">₱{{ $filters.formatMoney(a.amount) }}</div>
                  <div class="small" :class="a.balance > 0 ? 'text-danger' : 'text-success'">{{ a.balance > 0 ? "Balance: ₱" + $filters.formatMoney(a.balance) : "Fully Repaid" }}</div>
               </div>
               <div class="d-flex gap-2 align-items-center">
                  <span class="m-badge" :class="$filters.statusBadge(a.method)">{{ a.method_label }}</span>
               </div>
            </div>
            <div class="member-card-footer">
               <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ formatDateTime(a.paid_at) }}</span>
               <span class="text-muted small" v-if="a.reference_number"><i class="bi bi-hash me-1"></i>{{ a.reference_number }}</span>
            </div>
         </div>
      </div>

      <!-- Record Advance Modal -->
      <div class="modal fade" tabindex="-1" ref="advanceModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Record Cash Advance</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="form.amount" min="0.01" step="0.01" :class="{ 'is-invalid': formErrors.amount }" />
                        <div class="invalid-feedback">{{ formErrors.amount }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Method <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.method">
                           <option value="cash">Cash</option>
                           <option value="gcash">GCash</option>
                           <option value="online_payment">Online Payment</option>
                        </select>
                     </div>
                     <div class="col-md-6" v-if="form.method !== 'cash'">
                        <label class="form-label form-label-sm">Reference No.</label>
                        <input type="text" class="form-control" v-model="form.reference_number" placeholder="Optional" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Given At</label>
                        <input type="datetime-local" class="form-control" v-model="form.paid_at" />
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea class="form-control" rows="2" v-model="form.notes" placeholder="Optional"></textarea>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-primary btn-sm" :disabled="submitting" @click="submitAdvance"><span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>Record Advance</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDateTime, toDateTimeInputValue } from "../../../dates";

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         advances: [],
         pageError: "",
         form: this.emptyForm(),
         formError: "",
         formErrors: {},
         submitting: false,
      };
   },

   computed: {
      canManage: function () {
         return this.can("manage employees");
      },
      totalAdvanced: function () {
         return this.advances.reduce((s, a) => s + a.amount, 0);
      },
      totalOutstanding: function () {
         return this.advances.reduce((s, a) => s + a.balance, 0);
      },
   },

   mounted: function () {
      this.fetchAdvances();
      this.modalInst = new Modal(this.$refs.advanceModal);
   },

   methods: {
      formatDateTime,
      fetchAdvances: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/employees/${this.employee.id}/cash-advances`)
            .then((res) => (this.advances = res.data))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load cash advances."))
            .finally(() => (this.loading = false));
      },
      emptyForm: function () {
         return {
            amount: "",
            method: "cash",
            reference_number: "",
            notes: "",
            paid_at: toDateTimeInputValue(),
         };
      },
      openModal: function () {
         this.form = this.emptyForm();
         this.formError = "";
         this.formErrors = {};
         this.modalInst.show();
      },
      submitAdvance: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         axios
            .post(`/panel/employees/${this.employee.id}/cash-advances`, this.form)
            .then(() => {
               this.modalInst.hide();
               this.fetchAdvances();
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
   },
};
</script>
