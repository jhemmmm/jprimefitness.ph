<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Summary -->
      <div class="d-flex align-items-center justify-content-between mb-3">
         <template v-if="loading">
            <div class="skeleton-box" style="height: 14px; width: 80px"></div>
         </template>
         <template v-else>
            <div class="text-muted small">{{ advances.length }} cash advance{{ advances.length !== 1 ? "s" : "" }}</div>
            <div class="d-flex gap-3 align-items-center">
               <div class="fw-semibold small" v-if="advances.length">Total: ₱{{ $filters.formatMoney(totalAdvanced) }}</div>
               <div class="fw-semibold small text-danger" v-if="totalOutstanding > 0">Outstanding: ₱{{ $filters.formatMoney(totalOutstanding) }}</div>
               <button class="btn btn-primary btn-sm" v-if="canManage" @click="openModal()"><i class="bi bi-plus-lg me-1"></i>Record Advance</button>
               <button class="btn btn-danger btn-sm" v-if="isSelf" :disabled="hasPendingRequest" :title="hasPendingRequest ? 'You already have a pending request' : ''" @click="openRequestModal"><i class="bi bi-send me-1"></i>Request Advance</button>
            </div>
         </template>
      </div>

      <!-- Requests -->
      <div class="mb-4" v-if="!loading && (requests.length || isSelf)">
         <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold small">
               Requests
               <span class="badge-count ms-1" v-if="pendingRequests.length">{{ pendingRequests.length }} pending</span>
            </div>
         </div>
         <div v-if="requests.length === 0" class="text-muted small border rounded p-3">No cash advance requests yet. Use <strong>Request Advance</strong> to ask your manager for one.</div>
         <div class="table-responsive" v-else>
            <table class="table table-striped align-middle mb-0">
               <thead class="table-light">
                  <tr>
                     <th>Requested</th>
                     <th class="text-end fw-bold">Amount</th>
                     <th>Reason</th>
                     <th>Status</th>
                     <th>Reviewed</th>
                     <th class="text-end"></th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="r in requests" :key="'req' + r.id">
                     <td class="small text-nowrap">{{ formatDateTime(r.requested_at) }}</td>
                     <td class="text-end fw-bold small text-nowrap">₱{{ $filters.formatMoney(r.amount) }}</td>
                     <td class="small" style="max-width: 280px">{{ r.reason }}</td>
                     <td><span class="m-badge" :class="$filters.statusBadge(r.status)">{{ $filters.capitalize(r.status) }}</span></td>
                     <td class="small text-muted">
                        <template v-if="r.reviewed_at">
                           <div>{{ r.reviewed_by_name || "-" }} • {{ formatDateTime(r.reviewed_at) }}</div>
                           <div class="text-danger" v-if="r.status === 'rejected' && r.review_note">{{ r.review_note }}</div>
                        </template>
                        <span v-else>-</span>
                     </td>
                     <td class="text-end text-nowrap">
                        <template v-if="r.status === 'pending'">
                           <button type="button" class="btn btn-sm btn-outline-secondary" v-if="isSelf" :disabled="reviewing" @click="withdrawRequest(r)">Withdraw</button>
                           <template v-else-if="canManage">
                              <button type="button" class="btn btn-sm btn-success me-1" @click="openModal(r)"><i class="bi bi-check-lg me-1"></i>Approve</button>
                              <button type="button" class="btn btn-sm btn-outline-danger" @click="openRejectModal(r)"><i class="bi bi-x-lg me-1"></i>Reject</button>
                           </template>
                        </template>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
      </div>

      <!-- Loading -->
      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 4" :key="i" style="height: 48px margin-bottom: 8px"></div>
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
                  <th v-if="canManage"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="a in advances" :key="a.id" :class="{ 'opacity-50': a.voided }">
                  <td class="small">{{ formatDateTime(a.paid_at) }}</td>
                  <td>
                     <div>
                        <span class="m-badge" :class="$filters.statusBadge(a.method)">{{ a.method_label }}</span>
                        <span class="m-badge m-badge--plan-cancelled ms-1" v-if="a.voided">Voided</span>
                     </div>
                     <div class="text-danger small" v-if="a.voided">{{ a.void_reason }}</div>
                  </td>
                  <td class="small text-muted">{{ a.reference_number || "-" }}</td>
                  <td class="text-end fw-bold small" :class="{ 'text-decoration-line-through': a.voided }">₱{{ $filters.formatMoney(a.amount) }}</td>
                  <td class="text-end text-success small">₱{{ $filters.formatMoney(a.repaid_amount) }}</td>
                  <td class="text-end small" :class="a.balance > 0 ? 'text-danger fw-bold' : 'text-muted'">₱{{ $filters.formatMoney(a.balance) }}</td>
                  <td class="small text-muted">{{ a.released_by_name || "-" }}</td>
                  <td v-if="canManage">
                     <button type="button" class="btn btn-sm btn-outline-danger" v-if="a.voidable" @click="openVoidModal(a)" title="Void cash advance">
                        <i class="bi bi-x-circle"></i>
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none" v-if="!loading && advances.length">
         <div class="member-card" v-for="a in advances" :key="'ca' + a.id" :class="{ 'opacity-50': a.voided }">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold small" :class="{ 'text-decoration-line-through': a.voided }">₱{{ $filters.formatMoney(a.amount) }}</div>
                  <div class="small" :class="a.balance > 0 ? 'text-danger' : 'text-success'">{{ a.balance > 0 ? "Balance: ₱" + $filters.formatMoney(a.balance) : "Fully Repaid" }}</div>
               </div>
               <div class="d-flex gap-2 align-items-center">
                  <span class="m-badge" :class="$filters.statusBadge(a.method)">{{ a.method_label }}</span>
                  <span class="m-badge m-badge--plan-cancelled" v-if="a.voided">Voided</span>
               </div>
            </div>
            <div class="member-card-footer">
               <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ formatDateTime(a.paid_at) }}</span>
               <span class="text-muted small" v-if="a.reference_number"><i class="bi bi-hash me-1"></i>{{ a.reference_number }}</span>
               <button type="button" class="btn btn-sm btn-outline-danger" v-if="a.voidable" @click="openVoidModal(a)">Void</button>
            </div>
            <div class="text-danger small mt-1" v-if="a.voided">{{ a.void_reason }}</div>
         </div>
      </div>

      <!-- Record Advance Modal -->
      <div class="modal fade" tabindex="-1" ref="advanceModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ approvingRequest ? "Approve & Record Cash Advance" : "Record Cash Advance" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>
                  <div class="alert alert-warning py-2 small" v-if="approvingRequest">Approving the request for <strong>₱{{ $filters.formatMoney(approvingRequest.amount) }}</strong> — "{{ approvingRequest.reason }}". Recording it releases the money and adds it as a payroll deduction.</div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="form.amount" min="0.01" step="0.01" :class="{ 'is-invalid': formErrors.amount }" :disabled="!!approvingRequest" />
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
                     <div class="col-md">
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
                  <button type="button" class="btn btn-primary btn-sm" :disabled="submitting" @click="submitAdvance"><span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>{{ approvingRequest ? "Approve & Record" : "Record Advance" }}</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Request Modal (self) -->
      <div class="modal fade" tabindex="-1" ref="requestModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Request Cash Advance</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="requestError">{{ requestError }}</div>
                  <p class="text-muted small">Your manager will be notified and can approve or reject the request. Approved advances are deducted from your payroll.</p>
                  <div class="row g-3">
                     <div class="col-12">
                        <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="requestForm.amount" min="0.01" step="0.01" :class="{ 'is-invalid': requestErrors.amount }" />
                        <div class="invalid-feedback">{{ requestErrors.amount }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" rows="3" v-model="requestForm.reason" :class="{ 'is-invalid': requestErrors.reason }" placeholder="What is the advance for?"></textarea>
                        <div class="invalid-feedback">{{ requestErrors.reason }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="requesting" @click="submitRequest"><span v-if="requesting" class="spinner-border spinner-border-sm me-1"></span>Send Request</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Reject Modal (manager) -->
      <div class="modal fade" tabindex="-1" ref="rejectModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Reject Cash Advance Request</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="reviewing"></button>
               </div>
               <div class="modal-body" v-if="rejectTarget">
                  <div class="alert alert-danger py-2 small" v-if="rejectError">{{ rejectError }}</div>
                  <p class="small">Reject the ₱{{ $filters.formatMoney(rejectTarget.amount) }} request ("{{ rejectTarget.reason }}")? The employee will be notified with your reason.</p>
                  <label class="form-label form-label-sm">Reason <span class="text-danger">*</span></label>
                  <textarea class="form-control" rows="3" v-model="rejectForm.reason" :class="{ 'is-invalid': rejectErrors.reason }" placeholder="Why is this request being rejected?"></textarea>
                  <div class="invalid-feedback">{{ rejectErrors.reason }}</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" :disabled="reviewing">Back</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="reviewing" @click="submitReject"><span v-if="reviewing" class="spinner-border spinner-border-sm me-1"></span>Reject Request</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Void Modal -->
      <div class="modal fade" tabindex="-1" ref="voidModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Void Cash Advance</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="voiding"></button>
               </div>
               <div class="modal-body" v-if="voidTarget">
                  <div class="alert alert-danger py-2 small" v-if="voidError">{{ voidError }}</div>
                  <p class="small">
                     Void the ₱{{ $filters.formatMoney(voidTarget.amount) }} {{ voidTarget.method_label }} advance recorded on {{ formatDateTime(voidTarget.paid_at) }}? This cannot be undone.
                  </p>
                  <label class="form-label form-label-sm">Reason <span class="text-danger">*</span></label>
                  <textarea class="form-control" rows="3" v-model="voidForm.reason" :class="{ 'is-invalid': voidErrors.reason }" placeholder="Enter the reason this cash advance is being voided"></textarea>
                  <div class="invalid-feedback">{{ voidErrors.reason }}</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" :disabled="voiding">Back</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="voiding" @click="submitVoid"><span v-if="voiding" class="spinner-border spinner-border-sm me-1"></span>Void Advance</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDateTime, toDateTimeInputValue } from "../../../dates";
import { firstErrors } from "../../../http";

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         advances: [],
         requests: [],
         pageError: "",
         form: this.emptyForm(),
         formError: "",
         formErrors: {},
         submitting: false,
         approvingRequest: null,
         requestForm: { amount: "", reason: "" },
         requestError: "",
         requestErrors: {},
         requesting: false,
         rejectTarget: null,
         rejectForm: { reason: "" },
         rejectError: "",
         rejectErrors: {},
         reviewing: false,
         voidTarget: null,
         voidForm: { reason: "" },
         voidError: "",
         voidErrors: {},
         voiding: false,
      };
   },

   computed: {
      canManage: function () {
         return this.can("manage employees");
      },
      isSelf: function () {
         return this.employee.id === window.Laravel?.user?.id;
      },
      pendingRequests: function () {
         return this.requests.filter((r) => r.status === "pending");
      },
      hasPendingRequest: function () {
         return this.pendingRequests.length > 0;
      },
      totalAdvanced: function () {
         return this.advances.filter((a) => !a.voided).reduce((s, a) => s + a.amount, 0);
      },
      totalOutstanding: function () {
         return this.advances.reduce((s, a) => s + a.balance, 0);
      },
   },

   mounted: function () {
      this.fetchAdvances();
      this.fetchRequests();
      this.modalInst = new Modal(this.$refs.advanceModal);
      this.voidModalInst = new Modal(this.$refs.voidModal);
      this.requestModalInst = new Modal(this.$refs.requestModal);
      this.rejectModalInst = new Modal(this.$refs.rejectModal);
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
      openModal: function (request = null) {
         this.approvingRequest = request;
         this.form = this.emptyForm();
         if (request) this.form.amount = request.amount;
         this.formError = "";
         this.formErrors = {};
         this.modalInst.show();
      },
      submitAdvance: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         const url = this.approvingRequest
            ? `/panel/employees/${this.employee.id}/cash-advance-requests/${this.approvingRequest.id}/approve`
            : `/panel/employees/${this.employee.id}/cash-advances`;

         axios
            .post(url, this.form)
            .then((res) => {
               this.modalInst.hide();
               this.fetchAdvances();
               if (this.approvingRequest) this.replaceRequest(res.data);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.formErrors = firstErrors(err.response.data.errors);
               } else {
                  this.formError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.submitting = false));
      },
      fetchRequests: function () {
         axios
            .get(`/panel/employees/${this.employee.id}/cash-advance-requests`)
            .then((res) => (this.requests = res.data))
            .catch(() => {});
      },
      openRequestModal: function () {
         this.requestForm = { amount: "", reason: "" };
         this.requestError = "";
         this.requestErrors = {};
         this.requestModalInst.show();
      },
      submitRequest: function () {
         this.requesting = true;
         this.requestError = "";
         this.requestErrors = {};

         axios
            .post(`/panel/employees/${this.employee.id}/cash-advance-requests`, this.requestForm)
            .then((res) => {
               this.requestModalInst.hide();
               this.requests.unshift(res.data);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.requestErrors = firstErrors(err.response.data.errors);
               } else {
                  this.requestError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.requesting = false));
      },
      replaceRequest: function (updated) {
         const i = this.requests.findIndex((r) => r.id === updated.id);
         if (i !== -1) this.requests.splice(i, 1, updated);
      },
      withdrawRequest: function (request) {
         this.reviewing = true;
         axios
            .post(`/panel/employees/${this.employee.id}/cash-advance-requests/${request.id}/withdraw`)
            .then((res) => this.replaceRequest(res.data))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to withdraw the request."))
            .finally(() => (this.reviewing = false));
      },
      openRejectModal: function (request) {
         this.rejectTarget = request;
         this.rejectForm = { reason: "" };
         this.rejectError = "";
         this.rejectErrors = {};
         this.rejectModalInst.show();
      },
      submitReject: function () {
         this.reviewing = true;
         this.rejectError = "";
         this.rejectErrors = {};

         axios
            .post(`/panel/employees/${this.employee.id}/cash-advance-requests/${this.rejectTarget.id}/reject`, this.rejectForm)
            .then((res) => {
               this.rejectModalInst.hide();
               this.replaceRequest(res.data);
            })
            .catch((err) => {
               if (err.response?.status === 422 && err.response.data.errors) {
                  this.rejectErrors = firstErrors(err.response.data.errors);
               } else {
                  this.rejectError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.reviewing = false));
      },
      openVoidModal: function (advance) {
         this.voidTarget = advance;
         this.voidForm = { reason: "" };
         this.voidError = "";
         this.voidErrors = {};
         this.voidModalInst.show();
      },
      submitVoid: function () {
         this.voiding = true;
         this.voidError = "";
         this.voidErrors = {};

         axios
            .post(`/panel/employees/${this.employee.id}/cash-advances/${this.voidTarget.id}/void`, this.voidForm)
            .then((res) => {
               this.voidModalInst.hide();
               const i = this.advances.findIndex((a) => a.id === res.data.id);
               if (i !== -1) this.advances.splice(i, 1, res.data);
            })
            .catch((err) => {
               if (err.response?.status === 422 && err.response.data.errors) {
                  this.voidErrors = firstErrors(err.response.data.errors);
               } else {
                  this.voidError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.voiding = false));
      },
   },
};
</script>
