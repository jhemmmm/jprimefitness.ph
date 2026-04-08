<template>
   <div class="global-search">
      <button class="topbar-icon-btn" title="Search" @click="open">
         <i class="bi bi-search"></i>
      </button>

      <teleport to="body">
         <div class="modal fade" tabindex="-1" ref="modal">
            <div class="modal-dialog modal-dialog-centered modal-lg">
               <div class="modal-content global-search-shell border-0 shadow-lg">
                  <div class="global-search-bar">
                     <i class="bi bi-search global-search-bar-icon"></i>
                     <input
                        ref="input"
                        v-model="search"
                        type="text"
                        class="global-search-input"
                        placeholder="Search members, employees, inventory, or walk-ins"
                        @input="onSearchInput"
                     />
                     <button v-if="search" type="button" class="btn btn-link btn-sm text-muted text-decoration-none px-0" @click="clearSearch">
                        <i class="bi bi-x-lg"></i>
                     </button>
                  </div>

                  <div class="global-search-body">
                     <div v-if="error" class="alert alert-danger py-2 px-3 m-3 small mb-0">{{ error }}</div>

                     <div v-else-if="!hasEnoughCharacters" class="global-search-state text-muted">
                        <div class="fw-semibold text-body mb-1">Search across the panel</div>
                        <div>Type at least 2 characters to search members, employees, inventory, and walk-ins.</div>
                     </div>

                     <div v-else-if="loading" class="global-search-state text-muted">
                        <div class="spinner-border spinner-border-sm text-danger me-2" role="status" aria-hidden="true"></div>
                        Searching…
                     </div>

                     <div v-else-if="visibleGroups.length === 0" class="global-search-state text-muted">
                        <div class="fw-semibold text-body mb-1">No matches found</div>
                        <div>Try a different name, email, phone, SKU, or category.</div>
                     </div>

                     <div v-else class="global-search-results">
                        <section v-for="group in visibleGroups" :key="group.key" class="global-search-section">
                           <div class="global-search-section-head">
                              <div class="global-search-section-title">
                                 <span class="global-search-section-icon" :class="group.iconBg">
                                    <i class="bi" :class="[group.icon, group.iconColor]"></i>
                                 </span>
                                 <div>
                                    <div class="fw-semibold">{{ group.label }}</div>
                                    <div class="small text-muted">{{ group.total }} match{{ group.total === 1 ? "" : "es" }}</div>
                                 </div>
                              </div>
                              <a :href="group.viewAllUrl" class="small text-decoration-none">View all</a>
                           </div>

                           <div class="list-group list-group-flush">
                              <a v-for="item in group.items" :key="`${group.key}-${item.id}`" :href="item.url" class="list-group-item list-group-item-action global-search-result">
                                 <div class="global-search-result-main">
                                    <div class="fw-semibold text-truncate">{{ item.title }}</div>
                                    <div v-if="item.subtitle" class="small text-muted text-truncate">{{ item.subtitle }}</div>
                                    <div v-if="item.meta" class="small text-muted text-truncate">{{ item.meta }}</div>
                                 </div>
                                 <span v-if="item.status" :class="['m-badge', $filters.statusBadge(item.status)]">{{ $filters.capitalize(item.status) }}</span>
                              </a>
                           </div>
                        </section>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </teleport>
   </div>
</template>

<script>
import { Modal } from "bootstrap";

const GROUP_CONFIG = {
   members: { icon: "bi-people-fill", iconBg: "bg-primary-soft", iconColor: "text-primary" },
   employees: { icon: "bi-person-workspace", iconBg: "bg-warning-soft", iconColor: "text-warning" },
   inventory: { icon: "bi-box-seam-fill", iconBg: "bg-danger-soft", iconColor: "text-danger" },
   walkins: { icon: "bi-person-plus-fill", iconBg: "bg-primary-soft", iconColor: "text-primary" },
};

export default {
   data: function () {
      return {
         modalInstance: null,
         search: "",
         loading: false,
         error: "",
         groups: {},
         searchTimer: null,
         requestSequence: 0,
      };
   },
   mounted: function () {
      this.modalInstance = new Modal(this.$refs.modal);
      this.$refs.modal.addEventListener("shown.bs.modal", this.handleShown);
      this.$refs.modal.addEventListener("hidden.bs.modal", this.handleHidden);
      window.addEventListener("keydown", this.handleGlobalKeydown);
   },
   beforeUnmount: function () {
      clearTimeout(this.searchTimer);
      this.$refs.modal?.removeEventListener("shown.bs.modal", this.handleShown);
      this.$refs.modal?.removeEventListener("hidden.bs.modal", this.handleHidden);
      window.removeEventListener("keydown", this.handleGlobalKeydown);
      this.modalInstance?.dispose();
   },
   methods: {
      open: function () {
         this.modalInstance?.show();
      },
      handleShown: function () {
         this.$nextTick(() => this.$refs.input?.focus());
      },
      handleHidden: function () {
         this.resetState();
      },
      handleGlobalKeydown: function (event) {
         if (!(event.metaKey || event.ctrlKey) || String(event.key || "").toLowerCase() !== "k") {
            return;
         }

         if (this.isTypingContext(event.target)) {
            return;
         }

         event.preventDefault();
         this.open();
      },
      isTypingContext: function (target) {
         if (!target) {
            return false;
         }

         const tagName = String(target.tagName || "").toLowerCase();

         return tagName === "input" || tagName === "textarea" || tagName === "select" || target.isContentEditable;
      },
      onSearchInput: function () {
         clearTimeout(this.searchTimer);

         if (!this.hasEnoughCharacters) {
            this.requestSequence += 1;
            this.loading = false;
            this.error = "";
            this.groups = {};
            return;
         }

         this.searchTimer = setTimeout(() => this.fetchResults(), 250);
      },
      fetchResults: function () {
         const requestId = ++this.requestSequence;

         this.loading = true;
         this.error = "";

         axios
            .get("/panel/search", {
               params: {
                  search: this.normalizedSearch,
               },
            })
            .then((response) => {
               if (requestId !== this.requestSequence) {
                  return;
               }

               this.groups = response.data.groups || {};
            })
            .catch((error) => {
               if (requestId !== this.requestSequence) {
                  return;
               }

               this.groups = {};
               this.error = error.response?.data?.message || "Search is unavailable right now.";
            })
            .finally(() => {
               if (requestId === this.requestSequence) {
                  this.loading = false;
               }
            });
      },
      clearSearch: function () {
         this.requestSequence += 1;
         this.search = "";
         this.loading = false;
         this.error = "";
         this.groups = {};
         this.$nextTick(() => this.$refs.input?.focus());
      },
      resetState: function () {
         clearTimeout(this.searchTimer);
         this.requestSequence += 1;
         this.search = "";
         this.loading = false;
         this.error = "";
         this.groups = {};
      },
   },
   computed: {
      normalizedSearch: function () {
         return String(this.search || "").trim();
      },
      hasEnoughCharacters: function () {
         return this.normalizedSearch.length >= 2;
      },
      visibleGroups: function () {
         return Object.entries(this.groups)
            .map(([key, group]) => ({
               key,
               label: group.label,
               total: group.total || 0,
               items: Array.isArray(group.items) ? group.items : [],
               viewAllUrl: group.view_all_url,
               ...(GROUP_CONFIG[key] || { icon: "bi-search", iconBg: "bg-primary-soft", iconColor: "text-primary" }),
            }))
            .filter((group) => group.items.length > 0);
      },
   },
};
</script>

<style scoped>
.global-search-shell {
   border-radius: 1rem;
   overflow: hidden;
}

.global-search-bar {
   display: flex;
   align-items: center;
   gap: 0.85rem;
   padding: 1rem 1.25rem;
   border-bottom: 1px solid rgba(15, 23, 42, 0.08);
   background: var(--bs-body-bg);
}

.global-search-bar-icon {
   color: var(--bs-secondary-color);
   font-size: 1.05rem;
}

.global-search-input {
   width: 100%;
   border: 0;
   outline: 0;
   background: transparent;
   color: inherit;
   font-size: 1rem;
   font-weight: 600;
}

.global-search-body {
   max-height: min(68vh, 680px);
   overflow-y: auto;
   background: var(--bs-body-bg);
}

.global-search-state {
   padding: 2.75rem 1.5rem;
   text-align: center;
}

.global-search-results {
   padding: 1rem;
   display: grid;
   gap: 1rem;
}

.global-search-section {
   border: 1px solid rgba(15, 23, 42, 0.08);
   border-radius: 1rem;
   overflow: hidden;
   background: var(--bs-tertiary-bg);
}

.global-search-section-head {
   display: flex;
   align-items: center;
   justify-content: space-between;
   gap: 1rem;
   padding: 0.9rem 1rem;
   border-bottom: 1px solid rgba(15, 23, 42, 0.08);
   background: var(--bs-body-bg);
}

.global-search-section-title {
   display: flex;
   align-items: center;
   gap: 0.75rem;
   min-width: 0;
}

.global-search-section-icon {
   width: 2rem;
   height: 2rem;
   display: inline-flex;
   align-items: center;
   justify-content: center;
   border-radius: 0.8rem;
   flex-shrink: 0;
}

.global-search-result {
   display: flex;
   align-items: center;
   justify-content: space-between;
   gap: 1rem;
   padding: 0.9rem 1rem;
   border: 0;
   border-top: 1px solid rgba(15, 23, 42, 0.08);
   background: transparent;
}

.global-search-result:first-child {
   border-top: 0;
}

.global-search-result:hover,
.global-search-result:focus-visible {
   background: rgba(220, 53, 69, 0.05);
}

.global-search-result-main {
   min-width: 0;
}

@media (max-width: 575.98px) {
   .global-search-bar {
      padding: 0.9rem 1rem;
   }

   .global-search-results {
      padding: 0.85rem;
   }

   .global-search-section-head,
   .global-search-result {
      padding: 0.85rem;
   }

   .global-search-result {
      align-items: flex-start;
      flex-direction: column;
   }
}
</style>
