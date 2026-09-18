<template>
   <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
         <thead class="table-light">
            <tr>
               <th>{{ datetime ? "Date" : "Time" }}</th>
               <th>Type</th>
               <th>Description</th>
               <th>By</th>
               <th class="text-end">Amount</th>
            </tr>
         </thead>
         <tbody>
            <tr v-for="entry in entries" :key="entry.id">
               <td class="small text-nowrap">{{ datetime ? formatDateTime(entry.occurred_at) : formatTime(entry.occurred_at) }}</td>
               <td>
                  <span :class="['m-badge', $filters.ledgerTypeBadge(entry.type)]">{{ $filters.ledgerTypeLabel(entry.type) }}</span>
                  <span v-if="entry.category" class="text-muted small ms-1">{{ $filters.capitalize(entry.category) }}</span>
                  <div class="text-muted small mt-1">{{ entry.payment_method_label || "Cash" }}</div>
               </td>
               <td class="small">
                  {{ entry.description }}
                  <a v-if="entry.receipt_url" :href="entry.receipt_url" target="_blank" rel="noopener" class="ms-1 text-decoration-none"><i class="bi bi-paperclip"></i>Receipt</a>
                  <div v-if="entry.notes" class="text-muted" style="font-size: 0.75rem">{{ entry.notes }}</div>
               </td>
               <td class="small text-muted">{{ entry.recorded_by_name || "-" }}</td>
               <td class="text-end small fw-bold text-nowrap" :class="entry.affects_cash ? (entry.amount < 0 ? 'text-danger' : 'text-success') : 'text-body'">
                  {{ entry.affects_cash ? (entry.amount < 0 ? "-" : "+") : "" }}₱{{ $filters.formatMoney(Math.abs(entry.amount)) }}
                  <div v-if="!entry.affects_cash" class="text-muted fw-normal" style="font-size: 0.72rem">No cash effect</div>
               </td>
            </tr>
         </tbody>
      </table>
   </div>
</template>

<script>
import { formatDateTime, formatTime } from "../../../dates";

export default {
   props: {
      entries: { type: Array, required: true },
      // show the full date (cross-day lists) instead of just the clock time
      datetime: { type: Boolean, default: false },
   },

   methods: { formatDateTime, formatTime },
};
</script>
