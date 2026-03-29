<template>
   <div class="p-4">
      <div class="row justify-content-center">
         <div class="col-xl-10">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
               <div>
                  <div class="fw-semibold">Branch Gallery</div>
                  <div class="text-muted small">Upload storefront, interior, facility, and team photos for this branch.</div>
               </div>
               <label class="btn btn-danger btn-sm mb-0" :class="{ disabled: uploading }" v-if="canManageGallery">
                  <span v-if="uploading" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-upload me-1"></i>
                  Upload Photos
                  <input type="file" accept="image/*" multiple class="d-none" @change="uploadPhotos" :disabled="uploading" ref="photoInput" />
               </label>
            </div>

            <div v-if="generalError" class="alert alert-danger py-2 small">{{ generalError }}</div>

            <div v-if="photos.length" class="row g-3">
               <div class="col-sm-6 col-lg-4" v-for="(photo, index) in photos" :key="photo + '-' + index">
                  <div class="panel-card h-100 p-2">
                     <img :src="'/storage/' + photo" class="img-fluid rounded mb-2" :alt="branch.name + ' photo ' + (index + 1)" />
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

            <div v-else class="panel-card p-5 text-center text-muted">
               <i class="bi bi-images fs-1 d-block mb-2 opacity-25"></i>
               No gallery photos yet for this branch.
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

   emits: ["updated"],

   data: function () {
      return {
         uploading: false,
         deletingIndex: null,
         generalError: "",
         photos: Array.isArray(this.branch.photos) ? [...this.branch.photos] : [],
      };
   },

   watch: {
      branch: function (value) {
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
         this.$emit("updated", { ...this.branch, photos });
      },

      uploadPhotos: function (event) {
         const files = Array.from(event.target.files || []);

         if (!files.length) {
            return;
         }

         this.uploading = true;
         this.generalError = "";

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
               .post(`/panel/branches/${this.branch.id}/photos`, formData, {
                  headers: { "Content-Type": "multipart/form-data" },
               })
               .then((response) => {
                  this.photos.push(response.data.path);
                  this.emitUpdatedPhotos([...this.photos]);

                  return uploadNext(index + 1);
               })
               .catch((error) => {
                  this.generalError = error.response?.data?.message || "Failed to upload photo.";
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
         this.generalError = "";

         axios
            .delete(`/panel/branches/${this.branch.id}/photos/${index}`)
            .then(() => {
               this.photos.splice(index, 1);
               this.emitUpdatedPhotos([...this.photos]);
            })
            .catch((error) => {
               this.generalError = error.response?.data?.message || "Failed to remove photo.";
            })
            .finally(() => {
               this.deletingIndex = null;
            });
      },
   },
};
</script>
