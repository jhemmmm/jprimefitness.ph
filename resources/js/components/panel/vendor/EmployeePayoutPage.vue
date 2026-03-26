<template>
    <div class="p-3">
        <!-- Summary -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <template v-if="loading">
                <div class="skeleton-box" style="height: 14px; width: 80px; border-radius: 4px"></div>
            </template>
            <template v-else>
                <div class="text-muted small">{{ payouts.length }} payout{{ payouts.length !== 1 ? "s" : "" }}</div>
                <div class="fw-semibold small text-success" v-if="payouts.length">Total: ₱{{ formatMoney(payouts.reduce((s, p) => s + p.amount, 0)) }}</div>
            </template>
        </div>

        <!-- Loading -->
        <div v-if="loading">
            <div class="skeleton-box" v-for="i in 4" :key="i" style="height: 48px; border-radius: 6px; margin-bottom: 8px"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="payouts.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
            <div>No payouts recorded yet.</div>
        </div>

        <!-- Desktop table -->
        <div class="d-none d-md-block" v-else>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Payroll Period</th>
                        <th>Paid At</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="text-end fw-bold">Amount</th>
                        <th>Released By</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payouts" :key="p.id">
                        <td class="small text-muted">{{ p.payroll_period ?? "—" }}</td>
                        <td class="small">{{ formatDT(p.paid_at) }}</td>
                        <td>
                            <span class="m-badge" :class="methodBadge(p.method)">{{ methodLabel(p.method) }}</span>
                        </td>
                        <td class="small text-muted">{{ p.reference_number || "—" }}</td>
                        <td class="text-end fw-bold text-success small">₱{{ formatMoney(p.amount) }}</td>
                        <td class="small text-muted">{{ p.released_by_name || "—" }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(p)">
                                <i class="bi bi-trash tbl-icon"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="d-md-none" v-if="!loading && payouts.length">
            <div class="member-card" v-for="p in payouts" :key="'po' + p.id">
                <div class="member-card-top">
                    <div>
                        <div class="fw-semibold small">₱{{ formatMoney(p.amount) }}</div>
                        <div class="text-muted small">{{ p.payroll_period ?? "—" }}</div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="m-badge" :class="methodBadge(p.method)">{{ methodLabel(p.method) }}</span>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" @click="confirmDelete(p)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="member-card-footer">
                    <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ formatDT(p.paid_at) }}</span>
                    <span class="text-muted small" v-if="p.reference_number"><i class="bi bi-hash me-1"></i>{{ p.reference_number }}</span>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" tabindex="-1" ref="deleteModal">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Delete Payout?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-1 text-muted small">This payout record will be deleted and the payroll status will be updated.</div>
                    <div class="modal-footer border-0 pt-0">
                        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger btn-sm" :disabled="deleting" @click="doDelete"><span v-if="deleting" class="spinner-border spinner-border-sm me-1"></span>Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Modal } from "bootstrap";

export default {
    props: {
        employee: { type: Object, required: true },
    },

    data() {
        return {
            loading: true,
            deleting: false,
            payouts: [],
            deleteTarget: null,
            deleteModalInst: null,
        };
    },

    mounted() {
        this.deleteModalInst = new Modal(this.$refs.deleteModal);
        this.fetchPayouts();
    },

    methods: {
        fetchPayouts() {
            this.loading = true;
            axios
                .get(`/panel/employees/${this.employee.id}/payouts`)
                .then((res) => (this.payouts = res.data))
                .finally(() => (this.loading = false));
        },

        confirmDelete(p) {
            this.deleteTarget = p;
            this.deleteModalInst.show();
        },

        doDelete() {
            if (!this.deleteTarget) return;
            this.deleting = true;
            axios
                .delete(`/panel/payouts/${this.deleteTarget.id}`)
                .then(() => {
                    this.payouts = this.payouts.filter((p) => p.id !== this.deleteTarget.id);
                    this.deleteModalInst.hide();
                    this.deleteTarget = null;
                })
                .catch(() => {})
                .finally(() => (this.deleting = false));
        },

        methodLabel(m) {
            return { cash: "Cash", gcash: "GCash", bank: "Bank" }[m] ?? m;
        },

        methodBadge(m) {
            return { cash: "m-badge--active", gcash: "m-badge--plan", bank: "m-badge--plan" }[m] ?? "";
        },

        formatMoney(v) {
            return parseFloat(v || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatDT(dt) {
            if (!dt) return "—";
            return new Date(dt).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
        },
    },
};
</script>
