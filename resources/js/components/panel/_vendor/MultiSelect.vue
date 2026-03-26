<template>
    <div class="multi-select">
        <button type="button" class="form-select d-flex justify-content-between align-items-center" @click="toggleOpen" :class="{ active: open }">
            <span>{{ displayText }}</span>
            <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>

        <div v-if="open" class="multi-select-menu shadow-sm border bg-white mt-1 p-2">
            <div v-if="searchable" class="mb-2">
                <input type="text" class="form-control form-control-sm" v-model="search" placeholder="Search..." />
            </div>

            <ul class="list-unstyled mb-0" style="max-height: 240px; overflow-y: auto">
                <li v-for="option in filteredOptions" :key="option.value" class="mb-1">
                    <label class="d-flex align-items-center gap-2 mb-0" :class="{ 'text-muted': option.disabled }">
                        <input type="checkbox" :value="option.value" :checked="isSelected(option.value)" :disabled="option.disabled" @change="toggleOption(option.value, option.disabled)" />
                        <span>{{ option.label }}</span>
                    </label>
                </li>
                <li v-if="filteredOptions.length === 0" class="text-center text-muted small">No options found</li>
            </ul>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        modelValue: {
            type: Array,
            default: () => [],
        },
        options: {
            type: Array,
            required: true,
            // each option: { value: any, label: string }
        },
        placeholder: {
            type: String,
            default: "Select...",
        },
        searchable: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            open: false,
            search: "",
        };
    },

    mounted() {
        this.$nextTick(() => {
            this.$el.classList.add("position-relative");
        });
    },

    methods: {
        toggleOpen: function () {
            this.open = !this.open;
        },

        handleClickOutside: function (event) {
            if (!this.$el.contains(event.target)) {
                this.open = false;
            }
        },

        isSelected: function (value) {
            return this.modelValue.includes(value);
        },

        toggleOption: function (value, disabled) {
            if (disabled) return;

            const selected = [...this.modelValue];
            const index = selected.indexOf(value);
            if (index === -1) {
                selected.push(value);
            } else {
                selected.splice(index, 1);
            }
            this.$emit("update:modelValue", selected);
        },
    },

    watch: {
        open: function (value) {
            if (value) {
                document.addEventListener("click", this.handleClickOutside);
            } else {
                document.removeEventListener("click", this.handleClickOutside);
            }
        },
    },

    computed: {
        normalizedOptions: function () {
            return this.options.map((option) => {
                if (option && typeof option === "object") {
                    return {
                        value: option.value ?? option.id,
                        label: option.label ?? option.name ?? "",
                        disabled: option.disabled ?? (option.status === "offline" || option.status === "closed"),
                    };
                }

                return { value: option, label: String(option), disabled: false };
            });
        },

        selectedOptions: function () {
            return this.normalizedOptions.filter((option) => this.modelValue.includes(option.value));
        },

        displayText: function () {
            if (this.selectedOptions.length === 0) {
                return this.placeholder;
            }

            if (this.selectedOptions.length === 1) {
                return this.selectedOptions[0].label;
            }

            return `${this.selectedOptions.length} selected`;
        },

        filteredOptions: function () {
            if (!this.search) return this.normalizedOptions;
            const text = this.search.toLowerCase();
            return this.normalizedOptions.filter((option) => option.label.toLowerCase().includes(text));
        },
    },

    beforeUnmount: function () {
        document.removeEventListener("click", this.handleClickOutside);
    },
};
</script>

<style scoped>
.multi-select {
    width: 100%;
    position: relative;
}

.multi-select .form-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: none;
    padding-right: 2.5rem;
    position: relative;
}

.multi-select .form-select::after {
    display: none;
}

.multi-select i.bi {
    position: absolute;
    top: 50%;
    right: 0.75rem;
    transform: translateY(-50%);
    font-size: 0.9rem;
    color: #495057;
    pointer-events: none;
}

.multi-select-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 9999;
    background: #fff;
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    border-radius: 0.25rem;
}
</style>
