<template>
    <div class="p-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Changes saved successfully.</div>
                <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': errors.first_name }" v-model="form.first_name" />
                        <div class="invalid-feedback" v-if="errors.first_name">{{ errors.first_name[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': errors.last_name }" v-model="form.last_name" />
                        <div class="invalid-feedback" v-if="errors.last_name">{{ errors.last_name[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" v-model="form.email" />
                        <div class="invalid-feedback" v-if="errors.email">{{ errors.email[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Phone</label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="form.phone" />
                        <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Role <span class="text-danger">*</span></label>
                        <select class="form-select" :class="{ 'is-invalid': errors.role }" v-model="form.role">
                            <option v-for="r in allowedRoles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="errors.role">{{ errors.role[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Status <span class="text-danger">*</span></label>
                        <select class="form-select" :class="{ 'is-invalid': errors.status }" v-model="form.status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <div class="invalid-feedback" v-if="errors.status">{{ errors.status[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Branches</label>
                        <div :class="{ 'is-invalid': errors.branch_ids }">
                            <MultiSelect v-model="form.branch_ids" :options="branchesData" placeholder="Select branches..." searchable />
                        </div>
                        <div class="form-text small">Super admin/admin can assign multiple branches. Staff/coach should select one.</div>
                        <div class="invalid-feedback" v-if="errors.branch_ids">{{ errors.branch_ids[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Daily Rate (₱)</label>
                        <input type="number" class="form-control" :class="{ 'is-invalid': errors.daily_rate }" v-model="form.daily_rate" min="0" step="0.01" placeholder="0.00" />
                        <div class="invalid-feedback" v-if="errors.daily_rate">{{ errors.daily_rate[0] }}</div>
                    </div>

                    <div class="col-12"><hr class="my-1" /></div>

                    <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">
                            New Password
                            <span class="text-muted small">(leave blank to keep current)</span>
                        </label>
                        <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" v-model="form.password" autocomplete="new-password" />
                        <div class="invalid-feedback" v-if="errors.password">{{ errors.password[0] }}</div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <button class="btn btn-danger px-4" @click="save" :disabled="saving">
                        <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import MultiSelect from "../_vendor/MultiSelect.vue";

export default {
    components: {
        MultiSelect,
    },
    props: {
        employee: { type: Object, required: true },
        branchesData: { type: Array, default: () => [] },
    },

    emits: ["updated"],

    data() {
        return {
            saving: false,
            saved: false,
            generalError: "",
            errors: {},
            roles: [
                { value: "super_admin", label: "Super Admin" },
                { value: "admin", label: "Admin" },
                { value: "manager", label: "Manager" },
                { value: "staff", label: "Staff" },
                { value: "coach", label: "Coach" },
            ],
            form: {
                first_name: this.employee.first_name,
                last_name: this.employee.last_name,
                email: this.employee.email,
                phone: this.employee.phone || "",
                role: this.employee.role,
                status: this.employee.status,
                branch_ids: this.employee.branches ? this.employee.branches.map((b) => b.id) : [],
                daily_rate: this.employee.daily_rate || "",
                password: "",
            },
        };
    },

    watch: {
        employee(val) {
            this.form = {
                first_name: val.first_name,
                last_name: val.last_name,
                email: val.email,
                phone: val.phone || "",
                role: val.role,
                status: val.status,
                branch_ids: val.branches ? val.branches.map((b) => b.id) : [],
                daily_rate: val.daily_rate || "",
                password: "",
            };
        },
    },

    computed: {
        allowedRoles() {
            const p = window.permissions || {};
            if (p["super-admin"]) return this.roles;
            if (p["admin-or-above"]) return this.roles.filter((r) => r.value !== "super_admin");
            return this.roles.filter((r) => !["super_admin", "admin"].includes(r.value));
        },
    },

    methods: {
        async save() {
            this.saving = true;
            this.saved = false;
            this.generalError = "";
            this.errors = {};
            try {
                const payload = { ...this.form, branch_ids: this.form.branch_ids };
                const res = await axios.put(`/panel/employees/${this.employee.id}`, payload);
                this.saved = true;
                this.form.password = "";
                this.$emit("updated", res.data);
                setTimeout(() => (this.saved = false), 3000);
            } catch (err) {
                if (err.response?.status === 422) {
                    this.errors = err.response.data.errors || {};
                } else {
                    this.generalError = err.response?.data?.message || "Something went wrong.";
                }
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
