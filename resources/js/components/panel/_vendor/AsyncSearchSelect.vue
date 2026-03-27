<template>
   <div class="async-search-select" ref="root">
      <button type="button" class="form-select async-search-select-trigger text-start" :class="{ 'is-invalid': invalid, active: open }" :disabled="disabled" @click="toggleOpen">
         <span class="text-truncate d-block pe-4" :class="{ 'text-muted': !displayText }">{{ displayText || placeholder }}</span>
         <i class="bi async-search-select-icon" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
      </button>

      <div v-if="open" class="async-search-select-menu shadow-sm border bg-white rounded-3 mt-1 p-2">
         <div class="input-group input-group-sm mb-2">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input ref="searchInput" type="text" class="form-control border-start-0" v-model="query" :placeholder="searchPlaceholder" />
         </div>

         <div v-if="query.trim().length < minChars" class="small text-muted px-2 py-2">
            Type at least {{ minChars }} characters.
         </div>
         <div v-else-if="loading" class="small text-muted px-2 py-2">
            Searching...
         </div>
         <ul v-else class="list-unstyled mb-0 async-search-select-results">
            <li v-for="option in options" :key="option.id">
               <button type="button" class="async-search-select-option" @click="selectOption(option)">
                  <span class="fw-semibold d-block text-truncate">{{ option.name }}</span>
                  <span v-if="option.meta" class="small text-muted d-block text-truncate">{{ option.meta }}</span>
               </button>
            </li>
            <li v-if="options.length === 0" class="small text-muted px-2 py-2">
               No results found.
            </li>
         </ul>
      </div>
   </div>
</template>

<script>
export default {
   props: {
      modelValue: {
         type: [Number, String, null],
         default: null,
      },
      selectedLabel: {
         type: String,
         default: "",
      },
      placeholder: {
         type: String,
         default: "Select an option...",
      },
      searchPlaceholder: {
         type: String,
         default: "Search...",
      },
      invalid: {
         type: Boolean,
         default: false,
      },
      disabled: {
         type: Boolean,
         default: false,
      },
      minChars: {
         type: Number,
         default: 2,
      },
      fetchOptions: {
         type: Function,
         required: true,
      },
   },

   emits: ["update:modelValue", "select-option"],

   data() {
      return {
         open: false,
         query: "",
         options: [],
         loading: false,
         searchTimer: null,
         requestId: 0,
         selectedOption: null,
      };
   },

   computed: {
      displayText() {
         return this.selectedOption?.name || this.selectedLabel;
      },
   },

   watch: {
      query() {
         clearTimeout(this.searchTimer);

         if (this.query.trim().length < this.minChars) {
            this.options = [];
            this.loading = false;
            return;
         }

         this.searchTimer = setTimeout(() => this.loadOptions(), 250);
      },

      open(value) {
         if (value) {
            document.addEventListener("click", this.handleClickOutside);
            this.$nextTick(() => this.$refs.searchInput?.focus());
            return;
         }

         document.removeEventListener("click", this.handleClickOutside);
         this.query = "";
         this.options = [];
         this.loading = false;
      },

      selectedLabel(value) {
         if (!value) {
            this.selectedOption = null;
         }
      },
   },

   methods: {
      toggleOpen() {
         if (this.disabled) return;
         this.open = !this.open;
      },

      handleClickOutside(event) {
         if (!this.$refs.root?.contains(event.target)) {
            this.open = false;
         }
      },

      async loadOptions() {
         const currentRequestId = ++this.requestId;
         this.loading = true;

         try {
            const options = await this.fetchOptions(this.query.trim());
            if (currentRequestId !== this.requestId) return;
            this.options = Array.isArray(options) ? options : [];
         } catch (_) {
            if (currentRequestId !== this.requestId) return;
            this.options = [];
         } finally {
            if (currentRequestId === this.requestId) {
               this.loading = false;
            }
         }
      },

      selectOption(option) {
         this.selectedOption = option;
         this.$emit("update:modelValue", option.id);
         this.$emit("select-option", option);
         this.open = false;
      },
   },

   beforeUnmount() {
      clearTimeout(this.searchTimer);
      document.removeEventListener("click", this.handleClickOutside);
   },
};
</script>

<style scoped>
.async-search-select {
   position: relative;
}

.async-search-select .form-select {
   appearance: none;
   -webkit-appearance: none;
   -moz-appearance: none;
   background-image: none;
}

.async-search-select-trigger {
   position: relative;
   padding-right: 2.25rem;
}

.async-search-select-icon {
   position: absolute;
   top: 50%;
   right: 0.75rem;
   transform: translateY(-50%);
   pointer-events: none;
}

.async-search-select-menu {
   position: absolute;
   top: calc(100% + 0.25rem);
   left: 0;
   right: 0;
   z-index: 1080;
}

.async-search-select-results {
   max-height: 240px;
   overflow-y: auto;
}

.async-search-select-option {
   width: 100%;
   border: 0;
   background: transparent;
   text-align: left;
   padding: 0.5rem;
   border-radius: 0.5rem;
}

.async-search-select-option:hover {
   background: rgba(220, 53, 69, 0.08);
}
</style>
