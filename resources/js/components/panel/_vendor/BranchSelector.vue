<template>
    <div class="sidebar-branch dropdown">
        <div class="sidebar-branch-inner" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false">
            <div class="sidebar-branch-dot"></div>
            <div class="sidebar-branch-text">
                <div class="sidebar-branch-label">Current Branch</div>
                <div class="sidebar-branch-name">
                    {{ branchesData.find((branch) => branch.id === parseInt(branchId))?.name || "All Branches" }}
                </div>
            </div>
            <i class="bi bi-chevron-expand sidebar-branch-chevron"></i>
        </div>
        <ul class="dropdown-menu shadow-sm border-0">
            <li>
                <h6 class="dropdown-header">Select Branch</h6>
            </li>
            <li>
                <a :class="['dropdown-item', { active: branchId === 'null' }]" href="javascript:void(0)" @click="setBranchId('null')"> <i class="bi bi-diagram-3 me-2"></i> All Branches </a>
            </li>
            <li v-if="branchesData.length">
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
    data: function () {
        return {
            branchId: localStorage.getItem("selectedBranch"),
        };
    },
    methods: {
        setBranchId: function (newVal) {
            this.branchId = newVal;
            location.reload();
        },
    },
    watch: {
        branchId: function (newVal) {
            localStorage.setItem("selectedBranch", newVal);
        },
    },
};
</script>
