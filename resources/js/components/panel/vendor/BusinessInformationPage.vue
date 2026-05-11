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
         const groups = [];
         const entries = Array.isArray(this.profile.operating_hours) ? this.profile.operating_hours : [];

         entries.forEach((day) => {
            const opening = this.formatTime(day.opening_time);
            const closing = this.formatTime(day.closing_time);

            if (!day.day || !opening || !closing) {
               return;
            }

            const hours = `${opening} - ${closing}`;
            const lastGroup = groups[groups.length - 1];

            if (lastGroup?.hours === hours) {
               lastGroup.days.push(day.day);

               return;
            }

            groups.push({
               days: [day.day],
               hours,
            });
         });

         return groups.map((group) => ({
            days: this.formatDayRange(group.days),
            hours: group.hours,
         }));
      },
   },

   methods: {
      formatDayRange: function (days) {
         if (days.length === 1) {
            return days[0];
         }

         return `${days[0]}-${days[days.length - 1]}`;
      },
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
