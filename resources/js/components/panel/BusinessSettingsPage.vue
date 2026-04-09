<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Business Settings</h4>
            <div class="text-muted small">Manage business profile details, hours, contact links, and public-site copy.</div>
         </div>
      </div>

      <div class="panel-card mb-4">
         <business-information-page :profile="localProfile" />
      </div>

      <div class="panel-card">
         <business-settings-form :profile="localProfile" @updated="onProfileUpdated" />
      </div>
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
      },
   },
};
</script>
