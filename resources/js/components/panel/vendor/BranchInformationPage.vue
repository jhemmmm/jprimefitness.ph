<template>
   <div class="p-4">
      <div class="row g-3">
         <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
               <div class="small text-uppercase text-muted fw-semibold mb-3">Branch Profile</div>
               <div class="row g-3">
                  <div class="col-sm-6">
                     <div class="text-muted small">Branch Name</div>
                     <div class="fw-semibold">{{ branch.name || "—" }}</div>
                  </div>
                  <div class="col-sm-3">
                     <div class="text-muted small">Status</div>
                     <div class="fw-semibold">{{ branchStatus }}</div>
                  </div>
                  <div class="col-sm-3">
                     <div class="text-muted small">Country</div>
                     <div class="fw-semibold">{{ (branch.country_code || "PH").toUpperCase() }}</div>
                  </div>
                  <div class="col-12">
                     <div class="text-muted small">Address</div>
                     <div class="fw-semibold">{{ fullAddress }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">City</div>
                     <div class="fw-semibold">{{ branch.city || "—" }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">Province</div>
                     <div class="fw-semibold">{{ branch.province || "—" }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
               <div class="small text-uppercase text-muted fw-semibold mb-3">Contact & Hours</div>
               <div class="row g-3">
                  <div class="col-sm-6">
                     <div class="text-muted small">Phone</div>
                     <div class="fw-semibold">{{ branch.phone || "—" }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">Email</div>
                     <div class="fw-semibold">{{ branch.email || "—" }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">Opening Time</div>
                     <div class="fw-semibold">{{ formatTime(branch.opening_time) || "—" }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">Closing Time</div>
                     <div class="fw-semibold">{{ formatTime(branch.closing_time) || "—" }}</div>
                  </div>
                  <div class="col-12">
                     <div class="text-muted small">Timezone</div>
                     <div class="fw-semibold">{{ branch.timezone || "—" }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
               <div class="small text-uppercase text-muted fw-semibold mb-3">Online Presence</div>
               <div class="row g-3">
                  <div class="col-12">
                     <div class="text-muted small">Google Maps</div>
                     <div class="fw-semibold">
                        <a v-if="branch.map_url" :href="branch.map_url" class="text-decoration-none" target="_blank" rel="noopener noreferrer">Open map link</a>
                        <span v-else>—</span>
                     </div>
                  </div>
                  <div class="col-12">
                     <div class="text-muted small">Social Links</div>
                     <div v-if="socialLinks.length" class="d-flex flex-wrap gap-2 pt-1">
                        <a
                           v-for="link in socialLinks"
                           :key="link.label"
                           :href="link.url"
                           class="btn btn-sm btn-outline-secondary"
                           target="_blank"
                           rel="noopener noreferrer"
                        >
                           <i class="bi me-1" :class="link.icon"></i>{{ link.label }}
                        </a>
                     </div>
                     <div v-else class="fw-semibold">—</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
               <div class="small text-uppercase text-muted fw-semibold mb-3">Amenities</div>
               <div v-if="amenities.length" class="d-flex flex-wrap gap-2">
                  <span class="m-badge m-badge--plan" v-for="amenity in amenities" :key="amenity">{{ amenity }}</span>
               </div>
               <div v-else class="fw-semibold">No amenities listed.</div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
export default {
   props: {
      branch: { type: Object, required: true },
   },

   computed: {
      amenities: function () {
         return Array.isArray(this.branch.amenities) ? this.branch.amenities.filter(Boolean) : [];
      },
      branchStatus: function () {
         return this.$filters.capitalize(this.branch.status || "open");
      },
      fullAddress: function () {
         return [this.branch.address, this.branch.city, this.branch.province].filter(Boolean).join(", ") || "—";
      },
      socialLinks: function () {
         return [
            { label: "Facebook", url: this.branch.facebook_url, icon: "bi-facebook" },
            { label: "Messenger", url: this.branch.messenger_url, icon: "bi-messenger" },
            { label: "WhatsApp", url: this.branch.whatsapp_url, icon: "bi-whatsapp" },
         ].filter((link) => !!link.url);
      },
   },

   methods: {
      formatTime: function (timeStr) {
         if (!timeStr) {
            return "";
         }

         const parts = String(timeStr).split(":");
         let hour = parseInt(parts[0], 10);
         const minute = parts[1];
         const suffix = hour >= 12 ? "PM" : "AM";

         hour = hour % 12 || 12;

         return `${hour}:${minute} ${suffix}`;
      },
   },
};
</script>
