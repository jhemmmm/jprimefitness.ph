<template>
   <div class="business-settings-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Business Settings</h4>
            <p class="text-muted small mb-0">Manage business profile details, payroll toggles, location, hours, and amenities.</p>
         </div>
      </div>

      <div class="panel-card mb-4">
         <div class="panel-card-header">
            <div>
               <div class="panel-card-title">Current Profile Snapshot</div>
               <div class="panel-card-sub">Review the live business information before updating anything below.</div>
            </div>
         </div>
         <business-information-page :profile="localProfile" />
      </div>

      <business-settings-form :profile="localProfile" @updated="onProfileUpdated" />
   </div>
</template>

<script>
import BusinessInformationPage from "./vendor/BusinessInformationPage.vue";
import BusinessSettingsForm from "./vendor/BusinessSettingsForm.vue";

export default {
   components: {
      BusinessInformationPage,
      BusinessSettingsForm,
   },

   props: {
      profile: { type: Object, required: true },
   },

   data: function () {
      return {
         localProfile: { ...this.profile },
      };
   },

   watch: {
      profile: function (value) {
         this.localProfile = { ...value };
      },
   },

   methods: {
      onProfileUpdated: function (updatedProfile) {
         this.localProfile = { ...this.localProfile, ...updatedProfile };
         globalThis.JPrime = globalThis.JPrime || {};
         globalThis.JPrime.profile = {
            ...(globalThis.JPrime.profile || {}),
            ...updatedProfile,
         };
      },
   },
};
</script>
