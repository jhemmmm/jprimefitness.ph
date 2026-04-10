<template>
   <div>
      <div v-if="loading && events.length === 0" class="p-3 text-muted small">Loading audit history...</div>
      <div v-else-if="events.length === 0" class="p-3 text-muted small">{{ emptyMessage }}</div>

      <div v-else class="d-flex flex-column gap-2">
         <div v-for="event in events" :key="event.id || `${event.subject_type}-${event.subject_id}-${event.event}-${event.occurred_at}`" class="border rounded px-3 py-2 bg-white">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
               <div class="flex-grow-1 min-w-0">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                     <span class="badge rounded-pill text-bg-light border">{{ event.event_label }}</span>
                     <span v-if="showSubject && (event.subject_label || event.subject_type_label)" class="small text-muted">{{ event.subject_label || event.subject_type_label }}</span>
                  </div>

                  <div class="small fw-semibold mt-2">{{ event.title }}</div>
                  <div class="small text-muted mt-1">{{ event.message }}</div>
                  <div v-if="event.caused_by" class="small text-muted mt-1">
                     Caused by {{ event.caused_by.subject_label || event.caused_by.subject_type }} · {{ event.caused_by.event_label }}
                  </div>
                  <div class="small text-muted mt-2">
                     <span v-if="event.actor_name">{{ event.actor_name }} · </span>{{ formatDateTime(event.occurred_at) }}
                  </div>
               </div>

               <a v-if="showAction && event.action_url" class="btn btn-sm btn-outline-secondary" :href="event.action_url">
                  Open
               </a>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { formatDateTime } from "../../../dates";

export default {
   props: {
      events: {
         type: Array,
         default: function () {
            return [];
         },
      },
      loading: {
         type: Boolean,
         default: false,
      },
      emptyMessage: {
         type: String,
         default: "No audit history found.",
      },
      showSubject: {
         type: Boolean,
         default: false,
      },
      showAction: {
         type: Boolean,
         default: false,
      },
   },
   methods: {
      formatDateTime,
   },
};
</script>
