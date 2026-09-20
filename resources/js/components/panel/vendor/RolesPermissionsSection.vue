<template>
   <div>
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
         <div class="text-muted small">Permissions are defined in code; here you decide which roles hold them. Super admin always has every permission.</div>
         <button type="button" class="btn btn-danger btn-sm" @click="openCreate"><i class="bi bi-plus-lg me-1"></i>New Role</button>
      </div>

      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <div class="text-center py-4" v-if="loading"><div class="spinner-border text-danger spinner-border-sm"></div></div>

      <div class="table-responsive" v-else>
         <table class="table table-sm align-middle mb-0">
            <thead>
               <tr class="small text-muted">
                  <th>Role</th>
                  <th>Permissions</th>
                  <th class="text-center">Users</th>
                  <th></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="role in roles" :key="role.id">
                  <td class="text-nowrap">
                     <span :class="['m-badge', `m-badge--${role.color}`]">{{ $filters.capitalize(role.name) }}</span>
                     <div class="small text-muted" v-if="role.protected">Built-in</div>
                  </td>
                  <td>
                     <span class="text-muted small" v-if="role.permissions.length === 0">None</span>
                     <span v-for="permission in role.permissions" :key="permission" class="m-badge m-badge--slate me-1 mb-1">{{ $filters.capitalize(permission) }}</span>
                  </td>
                  <td class="text-center">{{ role.users_count }}</td>
                  <td class="text-end text-nowrap">
                     <button type="button" class="btn btn-sm btn-outline-secondary" @click="openEdit(role)" title="Edit"><i class="bi bi-pencil"></i></button>
                     <button
                        type="button"
                        class="btn btn-sm btn-outline-danger ms-1"
                        @click="confirmDelete(role)"
                        :disabled="role.protected || role.users_count > 0"
                        :title="role.protected ? 'Built-in roles cannot be deleted' : role.users_count > 0 ? 'Reassign its users first' : 'Delete'"
                     >
                        <i class="bi bi-trash"></i>
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
      </div>

      <div class="modal fade" tabindex="-1" ref="formModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ form.id ? "Edit Role" : "New Role" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>
                  <div class="mb-3">
                     <label class="form-label form-label-sm fw-semibold">Role name <span class="text-danger">*</span></label>
                     <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" :readonly="isProtected" placeholder="e.g. front desk" />
                     <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                     <div class="form-text" v-if="isProtected">Built-in roles keep their name; only their permissions change.</div>
                  </div>
                  <div class="mb-3">
                     <label class="form-label form-label-sm fw-semibold d-block">Badge color</label>
                     <div class="d-flex flex-wrap gap-2">
                        <button type="button" v-for="color in colors" :key="color" :class="['m-badge', `m-badge--${color}`, { 'is-selected': form.color === color }]" @click="form.color = color">
                           {{ $filters.capitalize(form.name || "Role") }}
                        </button>
                     </div>
                     <div class="text-danger small mt-1" v-if="formErrors.color">{{ formErrors.color }}</div>
                  </div>
                  <label class="form-label form-label-sm fw-semibold">Permissions</label>
                  <div class="row g-2">
                     <div class="col-md-6" v-for="permission in permissions" :key="permission">
                        <div class="form-check">
                           <input class="form-check-input" type="checkbox" :id="`role-permission-${permission}`" :value="permission" v-model="form.permissions" />
                           <label class="form-check-label" :for="`role-permission-${permission}`">{{ $filters.capitalize(permission) }}</label>
                        </div>
                     </div>
                  </div>
                  <div class="text-danger small mt-2" v-if="formErrors.permissions">{{ formErrors.permissions }}</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger" @click="submitForm" :disabled="saving">
                     <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                     {{ form.id ? "Save Changes" : "Create Role" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="deleteModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Delete Role</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <div class="alert alert-danger py-2 small" v-if="deleteError">{{ deleteError }}</div>
                  <p class="mb-0">
                     Delete the <strong>{{ $filters.capitalize(deleteTarget.name) }}</strong> role? This cannot be undone.
                  </p>
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
import { firstErrors } from "../../../http";

// Roles & permissions section of Settings: lists roles (super admin and member are never returned) and edits them in place.
export default {
   data: function () {
      return {
         loading: true,
         pageError: "",
         roles: [],
         permissions: [],
         colors: [],
         form: this.emptyForm(),
         formError: "",
         formErrors: {},
         saving: false,
         deleteTarget: null,
         deleteError: "",
         deleting: false,
         formModal: null,
         deleteModal: null,
      };
   },

   mounted: function () {
      this.formModal = new Modal(this.$refs.formModal);
      this.deleteModal = new Modal(this.$refs.deleteModal);
      this.fetchRoles();
   },

   // the section is v-if'd in, so it unmounts whenever the admin switches tabs
   beforeUnmount: function () {
      this.formModal?.dispose();
      this.deleteModal?.dispose();
   },

   computed: {
      isProtected: function () {
         return this.roles.find((role) => role.id === this.form.id)?.protected ?? false;
      },
   },

   methods: {
      emptyForm: function () {
         return { id: null, name: "", color: "slate", permissions: [] };
      },

      fetchRoles: function () {
         this.loading = true;
         axios
            .get("/panel/roles")
            .then((response) => {
               this.roles = response.data.roles;
               this.permissions = response.data.permissions;
               this.colors = response.data.colors;
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load roles.";
            })
            .finally(() => {
               this.loading = false;
            });
      },

      openCreate: function () {
         this.form = this.emptyForm();
         this.resetFormState();
         this.formModal.show();
      },

      openEdit: function (role) {
         this.form = { id: role.id, name: role.name, color: role.color, permissions: [...role.permissions] };
         this.resetFormState();
         this.formModal.show();
      },

      resetFormState: function () {
         this.formError = "";
         this.formErrors = {};
      },

      submitForm: function () {
         this.saving = true;
         this.resetFormState();
         var request = this.form.id ? axios.put(`/panel/roles/${this.form.id}`, this.form) : axios.post("/panel/roles", this.form);

         request
            .then((response) => {
               if (window.JPrime?.roleColors) window.JPrime.roleColors[response.data.name] = response.data.color;
               var index = this.roles.findIndex((role) => role.id === response.data.id);
               if (index === -1) {
                  this.roles.push(response.data);
                  this.roles.sort((a, b) => a.name.localeCompare(b.name));
               } else {
                  this.roles.splice(index, 1, response.data);
               }
               this.formModal.hide();
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  this.formErrors = firstErrors(error.response.data.errors);
               } else {
                  this.formError = error.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => {
               this.saving = false;
            });
      },

      confirmDelete: function (role) {
         this.deleteTarget = role;
         this.deleteError = "";
         this.deleteModal.show();
      },

      doDelete: function () {
         this.deleting = true;
         axios
            .delete(`/panel/roles/${this.deleteTarget.id}`)
            .then(() => {
               this.roles = this.roles.filter((role) => role.id !== this.deleteTarget.id);
               this.deleteModal.hide();
            })
            .catch((error) => {
               this.deleteError = error.response?.data?.errors?.role?.[0] || error.response?.data?.message || "Failed to delete role.";
            })
            .finally(() => {
               this.deleting = false;
            });
      },
   },
};
</script>
