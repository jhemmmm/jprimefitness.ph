<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Summary -->
      <div class="d-flex align-items-center justify-content-between mb-3">
         <template v-if="loading">
            <div class="skeleton-box" style="height: 14px; width: 80px; border-radius: 4px"></div>
         </template>
         <template v-else>
            <div class="text-muted small">{{ payouts.length }} payout{{ payouts.length !== 1 ? "s" : "" }}</div>
            <div class="fw-semibold small text-success" v-if="payouts.length">Total: ₱{{ $filters.formatMoney(payouts.reduce((s, p) => s + p.amount, 0)) }}</div>
         </template>
      </div>

      <!-- Loading -->
      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 4" :key="i" style="height: 48px; border-radius: 6px; margin-bottom: 8px"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="payouts.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
         <div>No payouts recorded yet.</div>
      </div>

      <!-- Desktop table -->
      <div class="d-none d-md-block" v-else>
         <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                  <th>Payroll Period</th>
                  <th>Paid At</th>
                  <th>Method</th>
                  <th>Reference</th>
                  <th class="text-end fw-bold">Amount</th>
                  <th>Released By</th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="p in payouts" :key="p.id">
                  <td class="small text-muted">{{ p.payroll_period ?? "—" }}</td>
                  <td class="small">{{ $filters.formatDateTime(p.paid_at) }}</td>
                  <td>
                     <span class="m-badge" :class="$filters.statusBadge(p.method)">{{ $filters.capitalize(p.method) }}</span>
                  </td>
                  <td class="small text-muted">{{ p.reference_number || "—" }}</td>
                  <td class="text-end fw-bold text-success small">₱{{ $filters.formatMoney(p.amount) }}</td>
                  <td class="small text-muted">{{ p.released_by_name || "—" }}</td>
               </tr>
            </tbody>
         </table>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none" v-if="!loading && payouts.length">
         <div class="member-card" v-for="p in payouts" :key="'po' + p.id">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold small">₱{{ $filters.formatMoney(p.amount) }}</div>
                  <div class="text-muted small">{{ p.payroll_period ?? "—" }}</div>
               </div>
               <div class="d-flex gap-2 align-items-center">
                  <span class="m-badge" :class="$filters.statusBadge(p.method)">{{ $filters.capitalize(p.method) }}</span>
               </div>
            </div>
            <div class="member-card-footer">
               <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $filters.formatDateTime(p.paid_at) }}</span>
               <span class="text-muted small" v-if="p.reference_number"><i class="bi bi-hash me-1"></i>{{ p.reference_number }}</span>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         payouts: [],
         pageError: "",
      };
   },

   mounted: function () {
      this.fetchPayouts();
   },

   methods: {
      fetchPayouts: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/employees/${this.employee.id}/payouts`)
            .then((res) => (this.payouts = res.data))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load payouts."))
            .finally(() => (this.loading = false));
      },
   },
};
</script>
