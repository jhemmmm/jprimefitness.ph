<template>
   <div class="business-gallery-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Business Gallery</h4>
            <p class="text-muted small mb-0">Upload storefront, interior, facility, and team photos for your business.</p>
         </div>
         <label class="btn btn-danger btn-sm mb-0" :class="{ disabled: uploading }" v-if="canManageGallery">
            <span v-if="uploading" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="bi bi-upload me-1"></i>
            Upload Photos
            <input type="file" accept="image/*" multiple class="d-none" @change="uploadPhotos" :disabled="uploading" ref="photoInput" />
         </label>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <span class="panel-card-title">
               Gallery Photos
               <span class="badge-count ms-1">{{ photos.length }}</span>
            </span>
            <span class="text-muted small">{{ canManageGallery ? "Manage uploaded business photos." : "Business photo gallery." }}</span>
         </div>

         <div v-if="photos.length" class="p-3 p-lg-4">
            <div class="row g-3">
               <div class="col-sm-6 col-lg-4" v-for="(photo, index) in photos" :key="photo + '-' + index">
                  <div class="border rounded-3 h-100 bg-white p-2">
                     <img :src="'/storage/' + photo" class="img-fluid rounded mb-2 w-100" :alt="profile.name + ' photo ' + (index + 1)" />
                     <div class="d-flex justify-content-between align-items-center small text-muted px-1 pb-1">
                        <span>Photo {{ index + 1 }}</span>
                        <button type="button" class="btn btn-outline-danger btn-sm" @click="deletePhoto(index)" :disabled="deletingIndex === index" v-if="canManageGallery">
                           <span v-if="deletingIndex === index" class="spinner-border spinner-border-sm"></span>
                           <i v-else class="bi bi-trash"></i>
                        </button>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div v-else class="p-5 text-center text-muted">
            <div class="mx-auto" style="max-width: 20rem;">
               <i class="bi bi-images fs-1 d-block mb-2 opacity-25"></i>
               No gallery photos yet for this business.
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

   emits: ["updated"],

   data: function () {
      return {
         uploading: false,
         deletingIndex: null,
         pageError: "",
         photos: Array.isArray(this.profile.photos) ? [...this.profile.photos] : [],
      };
   },

   watch: {
      profile: function (value) {
         this.photos = Array.isArray(value.photos) ? [...value.photos] : [];
      },
   },

   computed: {
      canManageGallery: function () {
         return this.is("super admin") || this.is("admin");
      },
   },

   methods: {
      emitUpdatedPhotos: function (photos) {
         this.$emit("updated", { ...this.profile, photos });
      },

      uploadPhotos: function (event) {
         const files = Array.from(event.target.files || []);

         if (!files.length) {
            return;
         }

         this.uploading = true;
         this.pageError = "";

         const uploadNext = (index) => {
            if (index >= files.length) {
               this.uploading = false;

               if (this.$refs.photoInput) {
                  this.$refs.photoInput.value = "";
               }

               return Promise.resolve();
            }

            const formData = new FormData();
            formData.append("photo", files[index]);

            return axios
               .post("/panel/business/photos", formData, {
                  headers: { "Content-Type": "multipart/form-data" },
               })
               .then((response) => {
                  this.photos.push(response.data.path);
                  this.emitUpdatedPhotos([...this.photos]);

                  return uploadNext(index + 1);
               })
               .catch((error) => {
                  this.pageError = error.response?.data?.message || "Failed to upload photo.";
                  this.uploading = false;

                  if (this.$refs.photoInput) {
                     this.$refs.photoInput.value = "";
                  }
               });
         };

         uploadNext(0);
      },

      deletePhoto: function (index) {
         this.deletingIndex = index;
         this.pageError = "";

         axios
            .delete(`/panel/business/photos/${index}`)
            .then(() => {
               this.photos.splice(index, 1);
               this.emitUpdatedPhotos([...this.photos]);
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to remove photo.";
            })
            .finally(() => {
               this.deletingIndex = null;
            });
      },
   },
};
</script>
