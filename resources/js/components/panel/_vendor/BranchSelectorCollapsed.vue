<template>
    <div class="topbar-branch-selector dropdown">
        <button class="topbar-branch-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="topbar-branch-dot"></span>
            <span class="topbar-branch-name">{{ branchesData.find((branch) => branch.id === parseInt(branchId))?.name || "All Branches" }}</span>
            <i class="bi bi-chevron-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width: 180px; font-size: 0.85rem">
            <li>
                <h6 class="dropdown-header">Select Branch</h6>
            </li>
            <li>
                <a :class="['dropdown-item', { active: branchId === 'null' }]" href="javascript:void(0)" @click="setBranchId('null')"> <i class="bi bi-diagram-3 me-2"></i>All Branches </a>
            </li>
            <li>
                <hr class="dropdown-divider" />
            </li>
            <li v-for="branch in branchesData" :key="branch.id">
                <a :class="['dropdown-item', { active: branch.id === parseInt(branchId) }]" href="javascript:void(0)" @click="setBranchId(branch.id)">
                    <span class="branch-status-dot online me-2"></span>
                    {{ branch.name }}
                </a>
            </li>
        </ul>
    </div>
</template>

<script>
export default {
    props: {
        branchesData: { type: Array, default: () => [] },
    },
    data() {
        return {
            branchId: localStorage.getItem("selectedBranch"),
        };
    },
    methods: {
        setBranchId(newVal) {
            this.branchId = newVal;
            location.reload();
        },
    },
    watch: {
        branchId(newVal) {
            localStorage.setItem("selectedBranch", newVal);
        },
    },
};
</script>
