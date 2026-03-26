<template>
    <div class="p-3">
        <!-- Stats -->
        <div class="row g-3 mb-4" v-if="loading">
            <div class="col-6 col-md-3" v-for="i in 4" :key="'sk-s-' + i">
                <div class="stat-card">
                    <div class="skeleton-box rounded-circle flex-shrink-0" style="width: 40px; height: 40px"></div>
                    <div class="stat-card-body">
                        <div class="skeleton-box mb-2" style="height: 11px; width: 65%"></div>
                        <div class="skeleton-box" style="height: 18px; width: 40%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-4" v-else>
            <div class="col-6 col-md-3" v-for="s in statCards" :key="s.label">
                <div class="stat-card">
                    <div class="stat-card-icon" :class="s.iconBg"><i class="bi" :class="[s.icon, s.iconColor]"></i></div>
                    <div class="stat-card-body">
                        <div class="stat-card-label">{{ s.label }}</div>
                        <div class="stat-card-value small">{{ s.isMoney ? "₱" + formatMoney(s.value) : s.value }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions bar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted small" v-if="!loading">{{ advances.length }} record{{ advances.length !== 1 ? "s" : "" }}</div>
            <div class="skeleton-box" v-else style="height: 14px; width: 80px; border-radius: 4px"></div>
            <button class="btn btn-danger btn-sm" @click="openCreate"><i class="bi bi-plus-lg me-1"></i>New Cash Advance</button>
        </div>

        <!-- Loading -->
        <div v-if="loading">
            <div class="skeleton-box" v-for="i in 3" :key="i" style="height: 56px; border-radius: 6px; margin-bottom: 8px"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="advances.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-25"></i>
            <div>No cash advances recorded.</div>
        </div>

        <!-- Desktop table -->
        <div class="d-none d-md-block" v-else>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Deducted</th>
                        <th class="text-end">Remaining</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in advances" :key="a.id">
                        <td class="small">{{ formatDate(a.requested_at) }}</td>
                        <td class="text-end small fw-semibold">₱{{ formatMoney(a.amount) }}</td>
                        <td class="text-end small text-success">{{ a.deducted_amount > 0 ? "₱" + formatMoney(a.deducted_amount) : "—" }}</td>
                        <td class="text-end small" :class="a.remaining_amount > 0 ? 'text-danger' : 'text-success'">
                            {{ a.remaining_amount > 0 ? "₱" + formatMoney(a.remaining_amount) : "✓" }}
                        </td>
                        <td>
                            <span class="m-badge" :class="statusBadge(a.status)">{{ statusLabel(a.status) }}</span>
                        </td>
                        <td class="small text-muted">{{ a.notes || "—" }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <button v-if="a.status !== 'fully_deducted'" class="btn btn-sm btn-outline-secondary" title="Edit" @click="openEdit(a)">
                                    <i class="bi bi-pencil tbl-icon"></i>
                                </button>
                                <button v-if="a.status !== 'fully_deducted'" class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(a)">
                                    <i class="bi bi-trash tbl-icon"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="d-md-none" v-if="!loading && advances.length">
            <div class="member-card" v-for="a in advances" :key="'ca' + a.id">
                <div class="member-card-top">
                    <div>
                        <div class="fw-semibold small">₱{{ formatMoney(a.amount) }}</div>
                        <div class="text-muted small">{{ formatDate(a.requested_at) }}</div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="m-badge" :class="statusBadge(a.status)">{{ statusLabel(a.status) }}</span>
                        <div class="dropdown" v-if="a.status !== 'fully_deducted'">
                            <button class="btn-icon-sm" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" @click.prevent="openEdit(a)"><i class="bi bi-pencil me-2"></i>Edit</a>
                                </li>
                                <li><hr class="dropdown-divider" /></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete(a)"><i class="bi bi-trash me-2"></i>Delete</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="member-card-tags ps-0 mt-1">
                    <span class="small text-danger" v-if="a.remaining_amount > 0">Remaining: ₱{{ formatMoney(a.remaining_amount) }}</span>
                    <span class="small text-success" v-else>Fully Deducted</span>
                    <span class="small text-muted" v-if="a.notes"> · {{ a.notes }}</span>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <div class="modal fade" tabindex="-1" ref="caModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">{{ modalMode === "create" ? "New Cash Advance" : "Edit Cash Advance" }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" v-model="form.amount" min="1" step="0.01" :class="{ 'is-invalid': formErrors.amount }" />
                                <div class="invalid-feedback">{{ formErrors.amount }}</div>
                            </div>
                            <div class="col-md-6" v-if="modalMode === 'edit'">
                                <label class="form-label form-label-sm">Remaining Amount (₱)</label>
                                <input type="number" class="form-control" v-model="form.remaining_amount" min="0" step="0.01" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm">Date</label>
                                <input type="datetime-local" class="form-control" v-model="form.requested_at" />
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Notes</label>
                                <textarea class="form-control" rows="2" v-model="form.notes" placeholder="Optional"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submit">
                            <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                            {{ modalMode === "create" ? "Create" : "Save" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" tabindex="-1" ref="deleteModal">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Delete Advance?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-1 text-muted small">This cash advance record will be permanently deleted.</div>
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
            submitting: false,
            deleting: false,
            advances: [],
            stats: { total_amount: 0, total_remaining: 0, pending_count: 0, partial_count: 0, deducted_count: 0 },
            modalMode: "create",
            form: this.emptyForm(),
            formError: "",
            formErrors: {},
            deleteTarget: null,
            caModalInst: null,
            deleteModalInst: null,
        };
    },

    mounted() {
        this.caModalInst = new Modal(this.$refs.caModal);
        this.deleteModalInst = new Modal(this.$refs.deleteModal);
        this.fetchAdvances();
    },

    computed: {
        statCards() {
            return [
                { label: "Total Advanced", value: this.stats.total_amount, isMoney: true, icon: "bi-wallet2", iconBg: "bg-primary-soft", iconColor: "text-primary" },
                { label: "Total Remaining", value: this.stats.total_remaining, isMoney: true, icon: "bi-hourglass-split", iconBg: "bg-danger-soft", iconColor: "text-danger" },
                { label: "Pending", value: this.stats.pending_count, isMoney: false, icon: "bi-clock", iconBg: "bg-warning-soft", iconColor: "text-warning" },
                { label: "Fully Deducted", value: this.stats.deducted_count, isMoney: false, icon: "bi-check-circle", iconBg: "bg-success-soft", iconColor: "text-success" },
            ];
        },
    },

    methods: {
        fetchAdvances() {
            this.loading = true;
            axios
                .get(`/panel/employees/${this.employee.id}/cash-advances`)
                .then((res) => {
                    this.advances = res.data.advances;
                    this.stats = res.data.stats;
                })
                .finally(() => (this.loading = false));
        },

        openCreate() {
            this.modalMode = "create";
            this.form = this.emptyForm();
            this.formError = "";
            this.formErrors = {};
            this.caModalInst.show();
        },

        openEdit(a) {
            this.modalMode = "edit";
            this.formError = "";
            this.formErrors = {};
            const d = a.requested_at ? new Date(a.requested_at).toISOString().slice(0, 16) : "";
            this.form = { id: a.id, amount: a.amount, remaining_amount: a.remaining_amount, notes: a.notes || "", requested_at: d };
            this.caModalInst.show();
        },

        submit() {
            this.submitting = true;
            this.formError = "";
            this.formErrors = {};

            const req = this.modalMode === "create" ? axios.post(`/panel/employees/${this.employee.id}/cash-advances`, this.form) : axios.put(`/panel/cash-advances/${this.form.id}`, this.form);

            req.then((res) => {
                this.caModalInst.hide();
                if (this.modalMode === "create") {
                    this.advances.unshift(res.data);
                } else {
                    const idx = this.advances.findIndex((a) => a.id === res.data.id);
                    if (idx !== -1) this.advances.splice(idx, 1, res.data);
                }
                // Refresh stats
                this.fetchAdvances();
            })
                .catch((err) => {
                    if (err.response?.status === 422) {
                        const errors = err.response.data.errors || {};
                        this.formErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
                    } else {
                        this.formError = err.response?.data?.message || "Something went wrong.";
                    }
                })
                .finally(() => (this.submitting = false));
        },

        confirmDelete(a) {
            this.deleteTarget = a;
            this.deleteModalInst.show();
        },

        doDelete() {
            if (!this.deleteTarget) return;
            this.deleting = true;
            axios
                .delete(`/panel/cash-advances/${this.deleteTarget.id}`)
                .then(() => {
                    this.advances = this.advances.filter((a) => a.id !== this.deleteTarget.id);
                    this.deleteModalInst.hide();
                    this.deleteTarget = null;
                    this.fetchAdvances();
                })
                .catch((err) => alert(err.response?.data?.message || "Failed to delete."))
                .finally(() => (this.deleting = false));
        },

        emptyForm() {
            const d = new Date();
            d.setSeconds(0, 0);
            return { amount: "", remaining_amount: "", notes: "", requested_at: d.toISOString().slice(0, 16) };
        },

        statusLabel(s) {
            return { pending: "Pending", partial: "Partial", fully_deducted: "Fully Deducted" }[s] ?? s;
        },

        statusBadge(s) {
            return {
                pending: "m-badge--pending",
                partial: "m-badge--partial",
                fully_deducted: "m-badge--fully_deducted",
            }[s] ?? "";
        },

        formatMoney(v) {
            return parseFloat(v || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatDate(d) {
            if (!d) return "—";
            return new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
        },
    },
};
</script>
