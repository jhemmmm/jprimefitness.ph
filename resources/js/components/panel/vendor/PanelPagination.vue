<template>
   <nav :aria-label="ariaLabel">
      <ul class="pagination pagination-sm mb-0 d-none d-md-flex">
         <li v-for="link in links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
            <a class="page-link" href="#" @click.prevent="goToLink(link)" v-html="link.label"></a>
         </li>
      </ul>

      <ul class="pagination pagination-sm panel-pagination-mobile mb-0 d-flex d-md-none">
         <li class="page-item" :class="{ disabled: currentPage <= 1 }">
            <button type="button" class="page-link" :disabled="currentPage <= 1" @click="emitPage(currentPage - 1)">
               <i class="bi bi-chevron-left me-1"></i>
               Previous
            </button>
         </li>
         <li class="page-item disabled">
            <span class="page-link panel-pagination-status">Page {{ currentPage }} of {{ lastPage }}</span>
         </li>
         <li class="page-item" :class="{ disabled: currentPage >= lastPage }">
            <button type="button" class="page-link" :disabled="currentPage >= lastPage" @click="emitPage(currentPage + 1)">
               Next
               <i class="bi bi-chevron-right ms-1"></i>
            </button>
         </li>
      </ul>
   </nav>
</template>

<script>
export default {
   props: {
      links: { type: Array, default: () => [] },
      currentPage: { type: Number, required: true },
      lastPage: { type: Number, required: true },
      ariaLabel: { type: String, default: "Pagination" },
   },

   emits: ["page-change"],

   methods: {
      goToLink: function (link) {
         if (!link.url) return;

         const page = Number(new URL(link.url, window.location.origin).searchParams.get("page") || 1);
         this.emitPage(page);
      },
      emitPage: function (page) {
         const targetPage = Number(page);

         if (!Number.isInteger(targetPage) || targetPage < 1 || targetPage > this.lastPage || targetPage === this.currentPage) return;

         this.$emit("page-change", targetPage);
      },
   },
};
</script>
