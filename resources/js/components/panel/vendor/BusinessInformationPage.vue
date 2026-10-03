<template>
   <div class="p-4">
      <div class="row g-3">
         <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
               <div class="small text-uppercase text-muted fw-semibold mb-3">Business Profile</div>
               <div class="row g-3">
                  <div class="col-sm-8">
                     <div class="text-muted small">Business Name</div>
                     <div class="fw-semibold">{{ profile.name || "-" }}</div>
                  </div>
                  <div class="col-sm-4">
                     <div class="text-muted small">Country</div>
                     <div class="fw-semibold">{{ (profile.country_code || "PH").toUpperCase() }}</div>
                  </div>
                  <div class="col-12">
                     <div class="text-muted small">Address</div>
                     <div class="fw-semibold">{{ fullAddress }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">City</div>
                     <div class="fw-semibold">{{ profile.city || "-" }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted small">Province</div>
                     <div class="fw-semibold">{{ profile.province || "-" }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
               <div class="small text-uppercase text-muted fw-semibold mb-3">Hours</div>
               <div class="row g-3">
                  <div class="col-sm-6" v-for="group in operatingHourGroups" :key="group.days">
                     <div class="text-muted small">{{ group.days }}</div>
                     <div class="fw-semibold">{{ group.hours }}</div>
                  </div>
                  <div class="col-12">
                     <div class="text-muted small">Timezone</div>
                     <div class="fw-semibold">{{ profile.timezone || "-" }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="col-12">
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
import { formatDayRange, groupOperatingHours } from "../../../dates";

export default {
   props: {
      profile: { type: Object, required: true },
   },

   computed: {
      amenities: function () {
         return Array.isArray(this.profile.amenities) ? this.profile.amenities.filter(Boolean) : [];
      },
      fullAddress: function () {
         return [this.profile.address, this.profile.city, this.profile.province].filter(Boolean).join(", ") || "-";
      },
      operatingHourGroups: function () {
         return groupOperatingHours(this.profile.operating_hours).map((group) => ({
            days: formatDayRange(group.days),
            hours: group.hours,
         }));
      },
   },
};
</script>
