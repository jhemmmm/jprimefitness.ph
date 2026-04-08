<template>
   <div class="p-4">
      <div class="row g-4">
         <div class="col-lg-7">
            <div class="panel-card h-100 p-3">
               <div class="fw-semibold mb-3">Member Information</div>
               <div class="row g-3">
                  <div class="col-md-6">
                     <div class="small text-muted">Email</div>
                     <div class="fw-semibold small">{{ member.email || "-" }}</div>
                  </div>
                  <div class="col-md-6">
                     <div class="small text-muted">Phone</div>
                     <div class="fw-semibold small">{{ member.phone || "-" }}</div>
                  </div>
                  <div class="col-md-6">
                  <div class="small text-muted">Location</div>
                  <div class="fw-semibold small">{{ currentLocationName }}</div>
                  </div>
                  <div class="col-md-6">
                     <div class="small text-muted">Status</div>
                     <div class="fw-semibold small">{{ $filters.capitalize(member.status) }}</div>
                  </div>
                  <div class="col-md-6">
                     <div class="small text-muted">Date of Birth</div>
                     <div class="fw-semibold small">{{ $filters.formatDate(member.profile?.date_of_birth) }}</div>
                  </div>
                  <div class="col-md-6">
                     <div class="small text-muted">Gender</div>
                     <div class="fw-semibold small">{{ $filters.capitalize(member.profile?.gender || "-") }}</div>
                  </div>
                  <div class="col-md-6">
                     <div class="small text-muted">Emergency Contact</div>
                     <div class="fw-semibold small">{{ member.profile?.emergency_contact_name || "-" }}</div>
                  </div>
                  <div class="col-md-6">
                     <div class="small text-muted">Emergency Phone</div>
                     <div class="fw-semibold small">{{ member.profile?.emergency_contact_phone || "-" }}</div>
                  </div>
                  <div class="col-12">
                     <div class="small text-muted">Notes</div>
                     <div class="fw-semibold small">{{ member.profile?.notes || "-" }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="col-lg-5">
            <div class="panel-card p-3">
               <div class="fw-semibold mb-3">Current Membership</div>
               <div v-if="activeMembership">
                  <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                     <div>
                        <div class="fw-semibold">{{ activeMembership.rate_plan.name }}</div>
                        <div class="small text-muted">Start {{ $filters.formatDate(activeMembership.start_date) }}</div>
                     </div>
                     <span class="m-badge" :class="planStatusClass(activeMembership.status)">{{ $filters.capitalize(activeMembership.status) }}</span>
                  </div>
                  <div class="small text-muted">
                     <span v-if="activeMembership.end_date">Ends {{ $filters.formatDate(activeMembership.end_date) }}</span>
                     <span v-else>No end date set.</span>
                  </div>
               </div>
               <div v-else class="text-muted small">No active membership found.</div>

               <hr class="my-3" />

               <div class="small text-muted">Member since</div>
               <div class="fw-semibold small">{{ $filters.formatDate(member.created_at) }}</div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
export default {
   props: {
      member: { type: Object, required: true },
   },

   computed: {
      currentLocationName: function () {
         return window.JPrime?.profile?.name || this.member.branches?.[0]?.name || "-";
      },
      activeMembership: function () {
         if (!this.member.member_subscriptions || !this.member.member_subscriptions.length) {
            return null;
         }

         return this.member.member_subscriptions.find((membership) => membership.status === "active" || membership.status === "paused") || this.member.member_subscriptions[0];
      },
   },

   methods: {
      planStatusClass: function (status) {
         return (
            {
               active: "m-badge--plan-active",
               expired: "m-badge--plan-expired",
               cancelled: "m-badge--plan-cancelled",
               paused: "m-badge--plan-paused",
            }[status] || "m-badge--plan-expired"
         );
      },
   },
};
</script>
