<template>
   <div class="employees-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Employees</h4>
            <p class="text-muted small mb-0">{{ employees.length }} employee{{ employees.length !== 1 ? "s" : "" }}</p>
         </div>
         <button class="btn btn-danger px-3" @click="openAdd">
            <i class="bi bi-plus-lg me-1"></i>
            Add Employee
         </button>
      </div>

      <div class="panel-card mb-4 p-3">
         <div class="row g-2">
            <div class="col-md-6">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted search-icon"></i></span>
                  <input type="text" class="form-control border-start-0" v-model="search" @input="onSearchInput" placeholder="Search by name, email, or phone…" />
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
                  <span
                     v-if="emp.employee_profile"
                     :class="['m-badge', biometricStatusClass(emp.employee_profile.biometric_status)]"
                  >
                     {{ biometricStatusLabel(emp.employee_profile.biometric_status) }}
                  </span>
               </div>

               <div class="small text-muted mt-2 d-flex align-items-center gap-2 flex-wrap" v-if="emp.employee_profile">
                  <span><i class="bi bi-fingerprint me-1"></i>{{ emp.employee_profile.hikvision_employee_no }}</span>
                  <span v-if="emp.employee_profile.biometric_enrolled_at">Enrolled {{ formatShortDateTime(emp.employee_profile.biometric_enrolled_at) }}</span>
               </div>

               <div class="small text-danger mt-2" v-if="emp.employee_profile?.biometric_last_error">
                  {{ emp.employee_profile.biometric_last_error }}
               </div>
            </div>
         </div>
      </div>

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
                        <label class="form-label fw-semibold">Daily Rate (₱)</label>
                        <input
                           type="number"
                           class="form-control"
                           :class="{ 'is-invalid': formErrors['employee_profile.daily_rate'] }"
                           v-model="form.employee_profile.daily_rate"
                           min="0"
                           step="0.01"
                           placeholder="0.00"
                        />
                        <div class="invalid-feedback" v-if="formErrors['employee_profile.daily_rate']">{{ formErrors["employee_profile.daily_rate"][0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label fw-semibold">Pay Frequency</label>
                        <select
                           class="form-select"
                           :class="{ 'is-invalid': formErrors['employee_profile.pay_frequency'] }"
                           v-model="form.employee_profile.pay_frequency"
                        >
                           <option value="semi_monthly">Semi-Monthly</option>
                           <option value="monthly">Monthly</option>
                        </select>
                        <div class="form-text small">Set the payroll schedule directly on the employee contract.</div>
                        <div class="invalid-feedback" v-if="formErrors['employee_profile.pay_frequency']">{{ formErrors["employee_profile.pay_frequency"][0] }}</div>
                     </div>
                     <div class="col-12" v-if="isPhilippinesPayroll">
                        <div class="border rounded-3 p-3 bg-light">
                           <div class="fw-semibold mb-1">Philippine Government Contributions</div>
                           <div class="text-muted small mb-3">These monthly statutory bases are saved on the employee and snapped into future payrolls.</div>

                           <div class="row g-3">
                              <div class="col-md-4">
                                 <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="employee-create-sss-covered" v-model="form.employee_profile.sss_covered" />
                                    <label class="form-check-label fw-semibold" for="employee-create-sss-covered">SSS Covered</label>
                                 </div>
                                 <label class="form-label form-label-sm fw-semibold">SSS Monthly Compensation (₱)</label>
                                 <input
                                    type="number"
                                    class="form-control"
                                    :class="{ 'is-invalid': formErrors['employee_profile.sss_monthly_compensation'] }"
                                    v-model="form.employee_profile.sss_monthly_compensation"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    :disabled="!form.employee_profile.sss_covered"
                                 />
                                 <div class="form-text small">Required when SSS coverage is enabled.</div>
                                 <div class="invalid-feedback" v-if="formErrors['employee_profile.sss_monthly_compensation']">{{ formErrors["employee_profile.sss_monthly_compensation"][0] }}</div>
                              </div>
                              <div class="col-md-4">
                                 <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="employee-create-philhealth-covered" v-model="form.employee_profile.philhealth_covered" />
                                    <label class="form-check-label fw-semibold" for="employee-create-philhealth-covered">PhilHealth Covered</label>
                                 </div>
                                 <label class="form-label form-label-sm fw-semibold">PhilHealth Monthly Basic Salary (₱)</label>
                                 <input
                                    type="number"
                                    class="form-control"
                                    :class="{ 'is-invalid': formErrors['employee_profile.philhealth_monthly_basic_salary'] }"
                                    v-model="form.employee_profile.philhealth_monthly_basic_salary"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    :disabled="!form.employee_profile.philhealth_covered"
                                 />
                                 <div class="form-text small">Required when PhilHealth coverage is enabled.</div>
                                 <div class="invalid-feedback" v-if="formErrors['employee_profile.philhealth_monthly_basic_salary']">{{ formErrors["employee_profile.philhealth_monthly_basic_salary"][0] }}</div>
                              </div>
                              <div class="col-md-4">
                                 <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="employee-create-pagibig-covered" v-model="form.employee_profile.pagibig_covered" />
                                    <label class="form-check-label fw-semibold" for="employee-create-pagibig-covered">Pag-IBIG Covered</label>
                                 </div>
                                 <label class="form-label form-label-sm fw-semibold">Pag-IBIG Monthly Compensation (₱)</label>
                                 <input
                                    type="number"
                                    class="form-control"
                                    :class="{ 'is-invalid': formErrors['employee_profile.pagibig_monthly_compensation'] }"
                                    v-model="form.employee_profile.pagibig_monthly_compensation"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    :disabled="!form.employee_profile.pagibig_covered"
                                 />
                                 <div class="form-text small">Required when Pag-IBIG coverage is enabled.</div>
                                 <div class="invalid-feedback" v-if="formErrors['employee_profile.pagibig_monthly_compensation']">{{ formErrors["employee_profile.pagibig_monthly_compensation"][0] }}</div>
                              </div>
                           </div>
                        </div>
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
                     <div class="col-12">
                        <label class="form-label fw-semibold d-block">Biometric</label>
                        <div class="form-check border rounded-3 p-3">
                           <input class="form-check-input" type="checkbox" id="employee-enroll-fingerprint" v-model="form.enroll_fingerprint" />
                           <label class="form-check-label fw-semibold" for="employee-enroll-fingerprint">Open fingerprint enrollment after save</label>
                           <div class="form-text small mt-1">After saving the employee, open the biometric modal so you can start collecting when the employee is at the device.</div>
                        </div>
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
                  <p class="text-muted small mb-0">You can restore this employee later from System Activity.</p>
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

      <div class="modal fade" id="employeeBiometricModal" tabindex="-1" ref="employeeBiometricModal" data-bs-backdrop="static" data-bs-keyboard="false">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Fingerprint Enrollment</h5>
                  <button v-if="canCloseBiometricModal" type="button" class="btn-close" @click="closeBiometricModal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="biometricModalError" class="alert alert-danger py-2 small mb-3">{{ biometricModalError }}</div>

                  <div class="text-center mb-4">
                     <div class="display-5 mb-3" :class="isBiometricBusy ? 'text-danger' : 'text-muted'">
                        <i class="bi" :class="isBiometricBusy ? 'bi-fingerprint' : 'bi-shield-check'"></i>
                     </div>
                     <h5 class="fw-bold mb-1">{{ biometricHeadline }}</h5>
                     <p class="text-muted small mb-0">{{ biometricDescription }}</p>
                  </div>

                  <div class="panel-card p-3">
                     <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                           <div class="small text-muted">Employee</div>
                           <div class="fw-semibold">{{ biometricEmployee?.name || "Employee" }}</div>
                        </div>
                        <span
                           v-if="biometricDisplayStatus"
                           :class="['m-badge', biometricStatusClass(biometricDisplayStatus)]"
                        >
                           {{ biometricStatusLabel(biometricDisplayStatus) }}
                        </span>
                     </div>

                     <div class="small text-muted mt-3">
                        <div v-if="biometricEmployee?.employee_profile?.hikvision_employee_no">
                           <i class="bi bi-person-badge me-1"></i>{{ biometricEmployee.employee_profile.hikvision_employee_no }}
                        </div>
                        <div v-if="biometricSession?.error_message" class="text-danger mt-2">
                           {{ biometricSession.error_message }}
                        </div>
                        <div v-else-if="biometricEmployee?.employee_profile?.biometric_last_error" class="text-danger mt-2">
                           {{ biometricEmployee.employee_profile.biometric_last_error }}
                        </div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button
                     v-if="showBiometricStartButton"
                     type="button"
                     class="btn btn-danger"
                     @click="startBiometricEnrollment(biometricEmployee)"
                     :disabled="isBiometricBusy || !biometricEmployee"
                  >
                     <span class="spinner-border spinner-border-sm me-1" v-if="biometricSubmitting"></span>
                     {{ biometricActionLabel }}
                  </button>
                  <button v-if="canCloseBiometricModal" type="button" class="btn btn-outline-secondary" @click="closeBiometricModal">
                     Close
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import MultiSelect from "./vendor/MultiSelect.vue";

export default {
   components: {
      MultiSelect,
   },
   props: {
      rolesData: { type: Array, default: () => [] },
   },
   data: function () {
      return {
         loading: true,
         pageError: "",
         employees: [],
         pagination: null,
         search: new URLSearchParams(window.location.search).get("search") || "",
         selectedRole: "",
         selectedStatus: "",
         searchTimer: null,
         employeeModal: null,
         deleteModal: null,
         biometricModal: null,
         modalMode: "create",
         editTarget: null,
         form: this.emptyForm(),
         formErrors: {},
         saving: false,
         deleteTarget: null,
         deleting: false,
         biometricEmployee: null,
         biometricSession: null,
         biometricModalError: "",
         biometricPollHandle: null,
         biometricSubmitting: false,
      };
   },
   mounted: function () {
      this.employeeModal = new Modal(this.$refs.employeeFormModal);
      this.deleteModal = new Modal(this.$refs.employeeDeleteModal);
      this.biometricModal = new Modal(this.$refs.employeeBiometricModal);
      this.$refs.employeeBiometricModal?.addEventListener("hide.bs.modal", this.onBiometricModalHide);
      this.fetchEmployees();
   },
   beforeUnmount: function () {
      clearTimeout(this.searchTimer);
      this.clearBiometricPolling();
      this.$refs.employeeBiometricModal?.removeEventListener("hide.bs.modal", this.onBiometricModalHide);
      this.employeeModal?.dispose();
      this.deleteModal?.dispose();
      this.biometricModal?.dispose();
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
      biometricDisplayStatus: function () {
         return this.biometricSession?.status || this.biometricEmployee?.employee_profile?.biometric_status || "";
      },
      hasActiveBiometricSession: function () {
         return ["pending", "capturing", "uploading"].includes(this.biometricSession?.status || "");
      },
      isBiometricBusy: function () {
         return this.biometricSubmitting || this.hasActiveBiometricSession;
      },
      canCloseBiometricModal: function () {
         return !this.isBiometricBusy;
      },
      showBiometricStartButton: function () {
         if (this.isBiometricBusy) {
            return false;
         }

         if (!this.biometricSession) {
            return !!this.biometricEmployee;
         }

         return ["failed", "expired", "cancelled"].includes(this.biometricSession.status || "");
      },
      biometricActionLabel: function () {
         if (["failed", "expired", "cancelled"].includes(this.biometricSession?.status || "")) {
            return "Start Collecting Again";
         }

         return "Start Collecting";
      },
      biometricHeadline: function () {
         const status = this.biometricDisplayStatus;

         if (this.biometricSubmitting && !this.biometricSession) {
            return "Starting fingerprint collection";
         }

         if (this.hasActiveBiometricSession) {
            return "Please press your fingerprint";
         }

         if (!this.biometricSession) {
            return "Fingerprint enrollment";
         }

         if (status === "succeeded" || status === "enrolled") {
            return "Fingerprint enrolled";
         }

         if (status === "failed") {
            return "Fingerprint enrollment failed";
         }

         if (status === "expired") {
            return "Fingerprint enrollment timed out";
         }

         if (status === "cancelled") {
            return "Fingerprint enrollment cancelled";
         }

         return "Fingerprint enrollment";
      },
      biometricDescription: function () {
         if (this.biometricSubmitting && !this.biometricSession) {
            return "Please wait while fingerprint collection is being started.";
         }

         if (this.hasActiveBiometricSession) {
            return "Please press your fingerprint on the Hikvision device.";
         }

         if (!this.biometricSession) {
            return "Click Start Collecting when the employee is at the device.";
         }

         if (this.biometricSession?.error_message) {
            return this.biometricSession.error_message;
         }

         if (this.biometricEmployee?.employee_profile?.biometric_last_error) {
            return this.biometricEmployee.employee_profile.biometric_last_error;
         }

         if (this.biometricDisplayStatus === "succeeded" || this.biometricDisplayStatus === "enrolled") {
            return "The employee can now use the biometric device for attendance check-in and check-out.";
         }

         return "Collection failed or timed out. Try again when the employee is ready at the device.";
      },
      isPhilippinesPayroll: function () {
         return (window.JPrime?.profile?.country_code || "PH") === "PH";
      },
   },
   methods: {
      employeeProfileForm: function (profile) {
         return {
            daily_rate: profile?.daily_rate ?? "",
            pay_frequency: profile?.pay_frequency || "semi_monthly",
            sss_covered: profile ? Boolean(profile.sss_covered) : this.isPhilippinesPayroll,
            sss_monthly_compensation: profile?.sss_monthly_compensation ?? "",
            philhealth_covered: profile ? Boolean(profile.philhealth_covered) : this.isPhilippinesPayroll,
            philhealth_monthly_basic_salary: profile?.philhealth_monthly_basic_salary ?? "",
            pagibig_covered: profile ? Boolean(profile.pagibig_covered) : this.isPhilippinesPayroll,
            pagibig_monthly_compensation: profile?.pagibig_monthly_compensation ?? "",
         };
      },
      emptyForm: function () {
         return {
            name: "",
            email: "",
            phone: "",
            status: "active",
            role_ids: [],
            employee_profile: this.employeeProfileForm(null),
            password: "",
            enroll_fingerprint: false,
         };
      },
      fetchEmployees: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/employees/list", {
               params: {
                  search: this.search || undefined,
                  role: this.selectedRole || undefined,
                  status: this.selectedStatus || undefined,
               },
            })
            .then((res) => {
               this.employees = res.data;
            })
            .catch((err) => {
               this.pageError = err.response?.data?.message || "Failed to load employees.";
            })
            .finally(() => {
               this.loading = false;
            });
      },
      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchEmployees(), 500);
      },
      openAdd: function () {
         this.form = this.emptyForm();
         this.formErrors = {};
         this.pageError = "";
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
            employee_profile: this.employeeProfileForm(emp.employee_profile || null),
            password: "",
            enroll_fingerprint: false,
         };
         this.formErrors = {};
         this.pageError = "";
         this.modalMode = "edit";
         this.editTarget = emp;
         this.employeeModal.show();
      },
      submitForm: function () {
         this.saving = true;
         this.formErrors = {};
         this.pageError = "";

         const shouldEnrollFingerprint = !!this.form.enroll_fingerprint;
         const payload = { ...this.form };
         delete payload.enroll_fingerprint;

         const request = this.modalMode === "create"
            ? axios.post("/panel/employees", payload)
            : axios.put(`/panel/employees/${this.editTarget.id}`, payload);

         request
            .then((res) => {
               const savedEmployee = this.mergeEmployee(res.data);

               this.employeeModal.hide();

               if (shouldEnrollFingerprint) {
                  this.openBiometricModal(savedEmployee);
               }
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.formErrors = err.response.data.errors || {};
               } else {
                  this.pageError = err.response?.data?.message || "Failed to save employee.";
               }
            })
            .finally(() => {
               this.saving = false;
            });
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
            .catch((err) => {
               this.pageError = err.response?.data?.message || "Failed to delete employee.";
            })
            .finally(() => {
               this.deleting = false;
            });
      },
      openBiometricModal: function (employee) {
         this.clearBiometricPolling();
         this.biometricEmployee = employee;
         this.biometricSession = null;
         this.biometricModalError = "";
         this.biometricSubmitting = false;
         this.biometricModal.show();
      },
      closeBiometricModal: function () {
         if (!this.canCloseBiometricModal) {
            return;
         }

         this.clearBiometricPolling();
         this.biometricModal.hide();
         this.biometricEmployee = null;
         this.biometricSession = null;
         this.biometricModalError = "";
         this.biometricSubmitting = false;
      },
      onBiometricModalHide: function (event) {
         if (this.isBiometricBusy) {
            event.preventDefault();
         }
      },
      startBiometricEnrollment: function (employee) {
         if (!employee || this.isBiometricBusy) {
            return;
         }

         this.biometricSubmitting = true;
         this.biometricModalError = "";

         axios
            .post(`/panel/employees/${employee.id}/biometric/enroll`)
            .then((res) => {
               this.applyBiometricPayload(employee, res.data);
            })
            .catch((err) => {
               this.biometricModalError = err.response?.data?.message || "Failed to start fingerprint enrollment.";
            })
            .finally(() => {
               this.biometricSubmitting = false;

               if (this.hasActiveBiometricSession) {
                  this.pollBiometricSession();
               }
            });
      },
      pollBiometricSession: function () {
         if (!this.biometricEmployee || !this.biometricSession?.id || this.biometricSubmitting) {
            return;
         }

         this.biometricPollHandle = window.setTimeout(() => {
            axios
               .get(`/panel/employees/${this.biometricEmployee.id}/biometric/sessions/${this.biometricSession.id}`)
               .then((res) => {
                  this.applyBiometricPayload(this.biometricEmployee, res.data);
               })
               .catch((err) => {
                  this.clearBiometricPolling();
                  this.biometricSession = null;
                  this.biometricModalError = err.response?.data?.message || "Failed to refresh fingerprint enrollment status.";
               });
         }, 1500);
      },
      applyBiometricPayload: function (employee, payload) {
         const updatedEmployee = this.mergeEmployee({
            ...employee,
            employee_profile: payload.employee_profile || employee.employee_profile || null,
         });

         this.biometricEmployee = updatedEmployee;
         this.biometricSession = payload.session || this.biometricSession;

         this.clearBiometricPolling();

         if (this.hasActiveBiometricSession) {
            this.pollBiometricSession();
         }
      },
      clearBiometricPolling: function () {
         if (this.biometricPollHandle) {
            window.clearTimeout(this.biometricPollHandle);
            this.biometricPollHandle = null;
         }
      },
      mergeEmployee: function (employee) {
         const index = this.employees.findIndex((item) => item.id === employee.id);

         if (index === -1) {
            this.employees.push(employee);

            return employee;
         }

         const merged = {
            ...this.employees[index],
            ...employee,
            employee_profile: employee.employee_profile ?? this.employees[index].employee_profile ?? null,
         };

         this.employees.splice(index, 1, merged);

         return merged;
      },
      biometricStatusLabel: function (status) {
         return {
            not_enrolled: "Not Enrolled",
            enrolling: "Enrolling",
            pending: "Waiting For Scan",
            capturing: "Capturing",
            uploading: "Uploading",
            enrolled: "Enrolled",
            succeeded: "Enrolled",
            failed: "Failed",
            expired: "Timed Out",
            cancelled: "Cancelled",
         }[status] || this.$filters.capitalize(status || "unknown");
      },
      biometricStatusClass: function (status) {
         return {
            not_enrolled: "m-badge--draft",
            enrolling: "m-badge--pending",
            pending: "m-badge--pending",
            capturing: "m-badge--pending",
            uploading: "m-badge--pending",
            enrolled: "m-badge--active",
            succeeded: "m-badge--active",
            failed: "m-badge--suspended",
            expired: "m-badge--suspended",
            cancelled: "m-badge--inactive",
         }[status] || "m-badge--draft";
      },
      formatShortDateTime: function (value) {
         if (!value) {
            return "";
         }

         return new Date(value).toLocaleString("en-PH", {
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit",
         });
      },
   },
};
</script>
