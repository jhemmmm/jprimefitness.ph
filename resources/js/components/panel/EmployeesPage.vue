<template>
   <div>
      <!-- Header -->
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Employees</h4>
            <div class="text-muted small">{{ employees.length }} employee{{ employees.length !== 1 ? "s" : "" }}</div>
         </div>
         <button class="btn btn-danger px-3" @click="openAdd"><i class="bi bi-plus-lg me-1"></i> Add Employee</button>
      </div>

      <!-- Filters -->
      <div class="panel-card mb-4 p-3">
         <div class="row g-2">
            <div class="col-md-6">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted search-icon"></i></span>
                  <input type="text" class="form-control border-start-0" v-model="search" @input="onSearchInput" placeholder="Search by name, email…" />
               </div>
            </div>
            <div class="col-md-3">
               <select class="form-select" v-model="selectedRole" @change="fetchEmployees">
                  <option value="">All Roles</option>
                  <option v-for="r in allowedRoles" :key="r.id" :value="r.id">
                     {{ $filters.capitalize(r.name) }}
                  </option>
               </select>
            </div>
            <div class="col-md-3">
               <select class="form-select" v-model="selectedStatus" @change="fetchEmployees">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
               </select>
            </div>
         </div>
      </div>

      <!-- Grid -->
      <!-- Skeleton loading -->
      <div v-if="loading" class="row g-3">
         <div class="col-sm-6 col-lg-4 col-xl-3" v-for="i in 8" :key="'sk-' + i">
            <div class="panel-card p-3 h-100">
               <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="skeleton-box rounded-circle flex-shrink-0" style="width: 44px; height: 44px"></div>
                  <div class="flex-grow-1">
                     <div class="skeleton-box mb-2" style="height: 14px; width: 70%"></div>
                     <div class="skeleton-box" style="height: 12px; width: 55%"></div>
                  </div>
               </div>
               <div class="d-flex gap-2">
                  <div class="skeleton-box" style="height: 20px; width: 60px; border-radius: 20px"></div>
                  <div class="skeleton-box" style="height: 20px; width: 50px; border-radius: 20px"></div>
               </div>
            </div>
         </div>
      </div>

      <!-- Grid populated -->
      <div v-else-if="employees.length === 0" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-person-workspace fs-1 d-block mb-2 opacity-25"></i>
         <div>No employees found.</div>
      </div>

      <div class="row g-3" v-else>
         <div class="col-sm-6 col-lg-4 col-xl-3" v-for="emp in employees" :key="emp.id">
            <div class="panel-card p-3 h-100 employee-card">
               <div class="d-flex align-items-start gap-3 mb-3">
                  <a :href="`/panel/employees/${emp.id}`" class="text-decoration-none d-flex align-items-center gap-3 flex-grow-1 overflow-hidden">
                     <div class="member-avatar employee-avatar-lg flex-shrink-0">{{ $filters.getNameInitials(emp.name) }}</div>
                     <div class="overflow-hidden">
                        <div class="fw-semibold text-truncate">{{ emp.name }}</div>
                        <div class="small text-muted text-truncate">{{ emp.email }}</div>
                     </div>
                  </a>
                  <div class="d-flex gap-1 flex-shrink-0">
                     <button class="btn btn-sm btn-outline-secondary" @click="openEdit(emp)" title="Edit"><i class="bi bi-pencil"></i></button>
                     <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(emp)" title="Delete"><i class="bi bi-trash"></i></button>
                  </div>
               </div>
               <div class="d-flex gap-2 flex-wrap">
                  <span v-for="role in emp.roles" :key="role.id" :class="['m-badge', $filters.roleBadge(role.name)]">{{ $filters.capitalize(role.name) }}</span>
                  <span :class="['m-badge', $filters.statusBadge(emp.status)]">{{ $filters.capitalize(emp.status) }}</span>
               </div>
               <div class="small text-muted mt-2" v-if="emp.branches && emp.branches.length">
                  <i class="bi bi-geo-alt me-1"></i>
                  {{ emp.branches.map((b) => b.name).join(", ") }}
               </div>
            </div>
         </div>
      </div>

      <!-- Add / Edit Employee Modal -->
      <div class="modal fade" id="employeeFormModal" tabindex="-1" ref="employeeFormModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ modalMode === "create" ? "Add New Employee" : "Edit Employee" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div class="row g-3">
                     <div class="col-md-12">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': formErrors.name }" v-model="form.name" />
                        <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" :class="{ 'is-invalid': formErrors.email }" v-model="form.email" />
                        <div class="invalid-feedback" v-if="formErrors.email">{{ formErrors.email[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': formErrors.phone }" v-model="form.phone" />
                        <div class="invalid-feedback" v-if="formErrors.phone">{{ formErrors.phone[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <div :class="{ 'is-invalid': formErrors.role_ids }">
                           <MultiSelect v-model="form.role_ids" :options="allowedRoles" placeholder="Select roles..." searchable />
                        </div>
                        <div class="invalid-feedback" v-if="formErrors.role_ids">{{ formErrors.role_ids[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                        <select class="form-select" :class="{ 'is-invalid': formErrors.status }" v-model="form.status">
                           <option value="active">Active</option>
                           <option value="inactive">Inactive</option>
                           <option value="suspended">Suspended</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.status">{{ formErrors.status[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Branches</label>
                        <div :class="{ 'is-invalid': formErrors.branch_ids }">
                           <MultiSelect v-model="form.branch_ids" :options="branchesData" placeholder="Select branches..." searchable />
                        </div>
                        <div class="form-text small">Super admin/admin can assign multiple branches. Staff/coach typically one.</div>
                        <div class="invalid-feedback" v-if="formErrors.branch_ids">{{ formErrors.branch_ids[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Daily Rate (₱)</label>
                        <input type="number" class="form-control" :class="{ 'is-invalid': formErrors.daily_rate }" v-model="form.daily_rate" min="0" step="0.01" placeholder="0.00" />
                        <div class="invalid-feedback" v-if="formErrors.daily_rate">{{ formErrors.daily_rate[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Pay Frequency</label>
                        <select class="form-select" :class="{ 'is-invalid': formErrors.pay_frequency }" v-model="form.pay_frequency">
                           <option value="semi_monthly">Semi-Monthly</option>
                           <option value="monthly">Monthly</option>
                        </select>
                        <div class="form-text small">Set the payroll schedule directly on the employee contract.</div>
                        <div class="invalid-feedback" v-if="formErrors.pay_frequency">{{ formErrors.pay_frequency[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">
                           Password
                           <span class="text-danger" v-if="modalMode === 'create'">*</span>
                           <span class="text-muted small" v-else>(leave blank to keep current)</span>
                        </label>
                        <input type="password" class="form-control" :class="{ 'is-invalid': formErrors.password }" v-model="form.password" autocomplete="new-password" />
                        <div class="invalid-feedback" v-if="formErrors.password">{{ formErrors.password[0] }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger" @click="submitForm" :disabled="saving">
                     <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                     {{ modalMode === "create" ? "Create Employee" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <!-- Delete Confirm Modal -->
      <div class="modal fade" id="employeeDeleteModal" tabindex="-1" ref="employeeDeleteModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Delete Employee</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p>
                     Are you sure you want to delete <strong>{{ deleteTarget.name }}</strong
                     >?
                  </p>
                  <p class="text-muted small mb-0">This action cannot be undone.</p>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger" @click="doDelete" :disabled="deleting">
                     <span class="spinner-border spinner-border-sm me-1" v-if="deleting"></span>
                     Delete
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import MultiSelect from "./_vendor/MultiSelect.vue";

export default {
   components: {
      MultiSelect,
   },
   props: {
      branchesData: { type: Array, default: () => [] },
      rolesData: { type: Array, default: () => [] },
   },
   data: function () {
      return {
         loading: true,
         employees: [],
         pagination: null,
         search: "",
         selectedRole: "",
         selectedStatus: "",
         selectedBranch: parseInt(localStorage.getItem("selectedBranch")) || "",
         searchTimer: null,
         // Modal
         employeeModal: null,
         deleteModal: null,
         modalMode: "create",
         editTarget: null,
         form: this.emptyForm(),
         formErrors: {},
         saving: false,
         // Delete
         deleteTarget: null,
         deleting: false,
      };
   },
   mounted: function () {
      this.employeeModal = new Modal(this.$refs.employeeFormModal);
      this.deleteModal = new Modal(this.$refs.employeeDeleteModal);
      this.fetchEmployees();
   },
   methods: {
      emptyForm: function () {
         return { name: "", email: "", phone: "", status: "active", role_ids: [], branch_ids: [], daily_rate: "", pay_frequency: "semi_monthly", password: "" };
      },
      fetchEmployees: function () {
         this.loading = true;
         axios
            .get("/panel/employees/list", {
               params: {
                  search: this.search || undefined,
                  role: this.selectedRole || undefined,
                  status: this.selectedStatus || undefined,
                  branch: this.selectedBranch || undefined,
               },
            })
            .then((res) => (this.employees = res.data))
            .catch((err) => console.error(err))
            .finally(() => (this.loading = false));
      },
      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchEmployees(), 500);
      },
      openAdd: function () {
         this.form = this.emptyForm();
         this.formErrors = {};
         this.modalMode = "create";
         this.editTarget = null;
         this.employeeModal.show();
      },
      openEdit: function (emp) {
         this.form = {
            name: emp.name,
            email: emp.email,
            phone: emp.phone || "",
            role_ids: emp.roles ? emp.roles.map((r) => r.id) : [],
            status: emp.status,
            branch_ids: emp.branches ? emp.branches.map((b) => b.id) : [],
            daily_rate: emp.daily_rate || "",
            pay_frequency: emp.pay_frequency,
            password: "",
         };
         this.formErrors = {};
         this.modalMode = "edit";
         this.editTarget = emp;
         this.employeeModal.show();
      },
      submitForm: function () {
         this.saving = true;
         this.formErrors = {};
         const payload = { ...this.form, branch_ids: this.form.branch_ids };
         const request = this.modalMode === "create" ? axios.post("/panel/employees", payload) : axios.put(`/panel/employees/${this.editTarget.id}`, payload);
         request
            .then((res) => {
               if (this.modalMode === "create") {
                  this.employees.push(res.data);
               } else {
                  const idx = this.employees.findIndex((e) => e.id === this.editTarget.id);
                  if (idx !== -1) this.employees.splice(idx, 1, res.data);
               }
               this.employeeModal.hide();
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.formErrors = err.response.data.errors;
               }
            })
            .finally(() => (this.saving = false));
      },
      confirmDelete: function (emp) {
         this.deleteTarget = emp;
         this.deleteModal.show();
      },
      doDelete: function () {
         this.deleting = true;
         axios
            .delete(`/panel/employees/${this.deleteTarget.id}`)
            .then(() => {
               this.employees = this.employees.filter((e) => e.id !== this.deleteTarget.id);
               this.deleteModal.hide();
            })
            .catch((err) => console.error(err))
            .finally(() => (this.deleting = false));
      },
   },

   computed: {
      allowedRoles: function () {
         const allowed = ["super admin", "admin", "manager", "staff", "coach", "employee"];
         const roleRestrictions = {
            "super admin": [],
            admin: ["super admin"],
            default: ["super admin", "admin"],
         };
         const currentRole = this.is("super admin") ? "super admin" : this.is("admin") ? "admin" : "default";
         return this.rolesData
            .filter((r) => allowed.includes(r.name) && !roleRestrictions[currentRole].includes(r.name))
            .map((r) => ({
               id: r.id,
               name: this.$filters.capitalize(r.name),
            }));
      },
   },
};
</script>
