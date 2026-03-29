<template>
    <div class="topbar-branch-selector dropdown">
        <button class="topbar-branch-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="topbar-branch-dot" :class="{ 'all-branches': isAllBranches }"></span>
            <span class="topbar-branch-copy">
                <span class="topbar-branch-label">{{ currentBranchLabel }}</span>
                <span class="topbar-branch-value">₱{{ $filters.formatMoney(currentBranchBalance) }}</span>
            </span>
            <i class="bi bi-chevron-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 topbar-branch-menu">
            <li>
                <h6 class="dropdown-header">Branch Cash Balances</h6>
            </li>
            <li>
                <a :class="['dropdown-item topbar-branch-menu-item', { active: isAllBranches }]" href="javascript:void(0)" @click="setBranchId('null')">
                    <span class="d-flex align-items-center gap-2 min-w-0">
                        <i class="bi bi-diagram-3"></i>
                        <span class="topbar-branch-menu-name">All Branches</span>
                    </span>
                    <span class="topbar-branch-menu-value">₱{{ $filters.formatMoney(totalBalance) }}</span>
                </a>
            </li>
            <li>
                <hr class="dropdown-divider" />
            </li>
            <li v-for="branch in branchesData" :key="branch.id">
                <a :class="['dropdown-item topbar-branch-menu-item', { active: branch.id === selectedBranchId }]" href="javascript:void(0)" @click="setBranchId(branch.id)">
                    <span class="d-flex align-items-center gap-2 min-w-0">
                        <span class="branch-status-dot online"></span>
                        <span class="topbar-branch-menu-name">{{ branch.name }}</span>
                    </span>
                    <span class="topbar-branch-menu-value">₱{{ $filters.formatMoney(branch.cash_balance || 0) }}</span>
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
    computed: {
        selectedBranchId: function () {
            const parsed = parseInt(this.branchId, 10);

            return Number.isNaN(parsed) ? null : parsed;
        },
        isAllBranches: function () {
            return this.branchId === null || this.branchId === "null" || this.selectedBranchId === null;
        },
        currentBranch: function () {
            if (this.isAllBranches) {
                return null;
            }

            return this.branchesData.find((branch) => branch.id === this.selectedBranchId) || null;
        },
        currentBranchLabel: function () {
            return this.currentBranch?.name || "All Branches";
        },
        currentBranchBalance: function () {
            return this.currentBranch?.cash_balance ?? this.totalBalance;
        },
        totalBalance: function () {
            return this.branchesData.reduce((sum, branch) => sum + parseFloat(branch.cash_balance || 0), 0);
        },
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
