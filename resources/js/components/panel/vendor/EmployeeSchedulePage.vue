<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <div v-if="loading">
         <div class="skeleton-box mb-2" v-for="i in 7" :key="'sk-sch-' + i" style="height: 56px; border-radius: 8px"></div>
      </div>

      <div v-else>
         <!-- Header -->
         <div class="schedule-header mb-3">
            <div class="schedule-summary">
               <div class="schedule-summary-block">
                  <div class="schedule-summary-value">{{ weekly.totalHours }}<span class="schedule-summary-unit">h</span></div>
                  <div class="schedule-summary-label">Weekly hours</div>
               </div>
               <div class="schedule-summary-block">
                  <div class="schedule-summary-value">{{ weekly.workingDays }}<span class="schedule-summary-unit">/7</span></div>
                  <div class="schedule-summary-label">Working days</div>
               </div>
               <div class="schedule-summary-block">
                  <div class="schedule-summary-value">{{ shifts.length }}</div>
                  <div class="schedule-summary-label">Total shifts</div>
               </div>
               <div class="schedule-summary-status">
                  <span v-if="isDirty" class="text-warning small"><i class="bi bi-pencil-fill me-1"></i>Unsaved</span>
                  <span v-else-if="savedAt" class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Saved</span>
               </div>
            </div>
            <div class="schedule-actions">
               <div class="dropdown">
                  <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                     <i class="bi bi-magic me-1"></i>Template
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                     <li><button class="dropdown-item small" type="button" @click="applyPreset('weekdays_9_5')">Mon–Fri, 9 AM – 5 PM</button></li>
                     <li><button class="dropdown-item small" type="button" @click="applyPreset('six_day_split')">Mon–Sat split (6–10, 16–20)</button></li>
                     <li><button class="dropdown-item small" type="button" @click="applyPreset('all_day_8_5')">All days, 8 AM – 5 PM</button></li>
                     <li><hr class="dropdown-divider" /></li>
                     <li><button class="dropdown-item small text-danger" type="button" @click="applyPreset('clear_all')">Clear all</button></li>
                  </ul>
               </div>
               <button class="btn btn-outline-secondary btn-sm" :disabled="!isDirty || saving" @click="discard">
                  <i class="bi bi-arrow-counterclockwise"></i>
               </button>
               <button class="btn btn-danger btn-sm" :disabled="saving || !isDirty" @click="save">
                  <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-save me-1"></i>Save
               </button>
            </div>
         </div>

         <!-- Day rows -->
         <div class="schedule-days">
            <div
               v-for="day in days"
               :key="day.key"
               class="schedule-day"
               :class="{ 'is-day-off': !entriesFor(day.key).length, 'is-today': day.key === todayDow }"
            >
               <div class="schedule-day-head">
                  <div class="schedule-day-name">
                     {{ day.label }}
                     <span v-if="day.key === todayDow" class="schedule-today-tag">Today</span>
                  </div>
                  <div class="schedule-day-meta">
                     <span v-if="entriesFor(day.key).length" class="schedule-hours">{{ dayTotals.get(day.key) }}h</span>
                     <span v-else class="schedule-off">Day off</span>
                     <div class="dropdown">
                        <button class="btn btn-sm btn-link p-0 schedule-menu-btn" type="button" data-bs-toggle="dropdown" :aria-label="'Actions for ' + day.label">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li><button class="dropdown-item small" type="button" @click="addShift(day.key)"><i class="bi bi-plus-lg me-2"></i>Add shift</button></li>
                           <li v-if="entriesFor(day.key).length"><button class="dropdown-item small text-danger" type="button" @click="clearDay(day.key)"><i class="bi bi-x-circle me-2"></i>Clear day</button></li>
                           <template v-if="copyableSourcesByDay.get(day.key).length">
                              <li><hr class="dropdown-divider" /></li>
                              <li class="dropdown-header small py-1">Copy from</li>
                              <li v-for="src in copyableSourcesByDay.get(day.key)" :key="'cp-' + day.key + '-' + src.key">
                                 <button class="dropdown-item small" type="button" @click="copyFrom(day.key, src.key)">
                                    {{ src.label }} ({{ dayTotals.get(src.key) }}h)
                                 </button>
                              </li>
                           </template>
                        </ul>
                     </div>
                  </div>
               </div>

               <div class="schedule-shifts" v-if="entriesFor(day.key).length">
                  <div v-for="entry in entriesFor(day.key)" :key="'s-' + entry.index" class="schedule-shift" :class="{ 'has-error': hasErrorsForShift(entry.index) }">
                     <input type="time" class="form-control form-control-sm schedule-time" v-model="shifts[entry.index].start_time" @input="onShiftChange" />
                     <span class="schedule-dash">–</span>
                     <input type="time" class="form-control form-control-sm schedule-time" v-model="shifts[entry.index].end_time" @input="onShiftChange" />
                     <span class="schedule-dur">{{ entry.durationHours }}h</span>
                     <button type="button" class="btn btn-sm btn-link text-danger schedule-remove" @click="removeShift(entry.index)" :aria-label="'Remove shift'">
                        <i class="bi bi-x-lg"></i>
                     </button>
                     <div class="schedule-error" v-if="errorFor(entry.index)">{{ errorFor(entry.index) }}</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-link p-0 schedule-add-link" @click="addShift(day.key)">
                     <i class="bi bi-plus-lg"></i> Add shift
                  </button>
               </div>
               <button v-else type="button" class="btn btn-sm btn-link p-0 schedule-add-link" @click="addShift(day.key)">
                  <i class="bi bi-plus-lg"></i> Add shift
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
const PRESETS = {
   weekdays_9_5: [1, 2, 3, 4, 5].map((d) => ({ day_of_week: d, start_time: "09:00", end_time: "17:00" })),
   six_day_split: [1, 2, 3, 4, 5, 6].flatMap((d) => [
      { day_of_week: d, start_time: "06:00", end_time: "10:00" },
      { day_of_week: d, start_time: "16:00", end_time: "20:00" },
   ]),
   all_day_8_5: [0, 1, 2, 3, 4, 5, 6].map((d) => ({ day_of_week: d, start_time: "08:00", end_time: "17:00" })),
   clear_all: [],
};

function pickShiftFields(s) {
   return { day_of_week: s.day_of_week, start_time: s.start_time, end_time: s.end_time };
}

function toMinutes(hhmm) {
   if (!hhmm) return 0;
   const [h, m] = hhmm.split(":").map((n) => parseInt(n, 10) || 0);
   return h * 60 + m;
}

function fromMinutes(mins) {
   const h = Math.floor(mins / 60);
   const m = mins % 60;
   return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

function durationHours(shift) {
   const mins = Math.max(0, toMinutes(shift.end_time) - toMinutes(shift.start_time));
   return Math.round((mins / 60) * 10) / 10;
}

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         saving: false,
         pageError: "",
         savedAt: null,
         shifts: [],
         baseline: "[]",
         serverErrors: {},
         todayDow: new Date().getDay(),
         days: [
            { key: 1, label: "Monday" },
            { key: 2, label: "Tuesday" },
            { key: 3, label: "Wednesday" },
            { key: 4, label: "Thursday" },
            { key: 5, label: "Friday" },
            { key: 6, label: "Saturday" },
            { key: 0, label: "Sunday" },
         ],
      };
   },

   mounted: function () {
      this.load();
   },

   computed: {
      byDay: function () {
         const map = new Map();
         this.days.forEach((d) => map.set(d.key, []));
         this.shifts.forEach((shift, index) => {
            if (!map.has(shift.day_of_week)) map.set(shift.day_of_week, []);
            map.get(shift.day_of_week).push({ index, shift, durationHours: durationHours(shift) });
         });
         map.forEach((entries) => entries.sort((a, b) => (a.shift.start_time || "").localeCompare(b.shift.start_time || "")));
         return map;
      },

      dayTotals: function () {
         const totals = new Map();
         this.byDay.forEach((entries, key) => {
            const sum = entries.reduce((s, e) => s + e.durationHours, 0);
            totals.set(key, Math.round(sum * 10) / 10);
         });
         return totals;
      },

      copyableSourcesByDay: function () {
         const map = new Map();
         this.days.forEach((target) => {
            map.set(
               target.key,
               this.days.filter((src) => src.key !== target.key && this.byDay.get(src.key).length > 0),
            );
         });
         return map;
      },

      weekly: function () {
         let totalMinutes = 0;
         const workingDayKeys = new Set();
         this.shifts.forEach((s) => {
            const mins = Math.max(0, toMinutes(s.end_time) - toMinutes(s.start_time));
            totalMinutes += mins;
            if (mins > 0) workingDayKeys.add(s.day_of_week);
         });
         return {
            totalHours: Math.round((totalMinutes / 60) * 10) / 10,
            workingDays: workingDayKeys.size,
         };
      },

      clientErrors: function () {
         const errors = {};
         const byDay = {};
         this.shifts.forEach((s, idx) => {
            if (!s.start_time || !s.end_time) {
               errors[`shifts.${idx}.start_time`] = "Start and end times are required.";
               return;
            }
            if (s.end_time <= s.start_time) {
               errors[`shifts.${idx}.end_time`] = "End must be after start.";
               return;
            }
            (byDay[s.day_of_week] = byDay[s.day_of_week] || []).push({ idx, shift: s });
         });

         Object.values(byDay).forEach((entries) => {
            entries.sort((a, b) => a.shift.start_time.localeCompare(b.shift.start_time));
            for (let i = 1; i < entries.length; i++) {
               if (entries[i].shift.start_time < entries[i - 1].shift.end_time) {
                  errors[`shifts.${entries[i].idx}.start_time`] = "Overlaps another shift.";
               }
            }
         });
         return errors;
      },

      errors: function () {
         return { ...this.serverErrors, ...this.clientErrors };
      },

      hasClientErrors: function () {
         return Object.keys(this.clientErrors).length > 0;
      },

      isDirty: function () {
         return JSON.stringify(this.normalize(this.shifts)) !== this.baseline;
      },
   },

   methods: {
      load: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/employees/${this.employee.id}/schedule`)
            .then((res) => {
               this.shifts = (res.data.shifts || []).map(pickShiftFields);
               this.baseline = JSON.stringify(this.normalize(this.shifts));
               this.serverErrors = {};
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load schedule."))
            .finally(() => (this.loading = false));
      },

      normalize: function (shifts) {
         return [...shifts]
            .map(pickShiftFields)
            .sort((a, b) => a.day_of_week - b.day_of_week || (a.start_time || "").localeCompare(b.start_time || ""));
      },

      entriesFor: function (dayOfWeek) {
         return this.byDay.get(dayOfWeek) || [];
      },

      addShift: function (dayOfWeek) {
         const existing = this.entriesFor(dayOfWeek);
         const last = existing[existing.length - 1];
         const defaults = last ? this.suggestNextShift(last.shift) : { start_time: "09:00", end_time: "17:00" };
         this.shifts.push({ day_of_week: dayOfWeek, ...defaults });
         this.savedAt = null;
         this.serverErrors = {};
      },

      suggestNextShift: function (last) {
         const startMin = toMinutes(last.end_time) + 60;
         const start = startMin < 22 * 60 ? startMin : 9 * 60;
         const end = Math.min(start + 4 * 60, 23 * 60 + 30);
         return { start_time: fromMinutes(start), end_time: fromMinutes(end) };
      },

      removeShift: function (index) {
         this.shifts.splice(index, 1);
         this.savedAt = null;
         this.serverErrors = {};
      },

      clearDay: function (dayOfWeek) {
         this.shifts = this.shifts.filter((s) => s.day_of_week !== dayOfWeek);
         this.savedAt = null;
         this.serverErrors = {};
      },

      copyFrom: function (targetDay, sourceDay) {
         const sourceShifts = this.shifts.filter((s) => s.day_of_week === sourceDay);
         this.shifts = this.shifts
            .filter((s) => s.day_of_week !== targetDay)
            .concat(sourceShifts.map((s) => ({ day_of_week: targetDay, start_time: s.start_time, end_time: s.end_time })));
         this.savedAt = null;
         this.serverErrors = {};
      },

      applyPreset: function (name) {
         this.shifts = (PRESETS[name] || []).map((s) => ({ ...s }));
         this.savedAt = null;
         this.serverErrors = {};
      },

      discard: function () {
         try {
            this.shifts = JSON.parse(this.baseline).map(pickShiftFields);
         } catch (_e) {
            this.shifts = [];
         }
         this.serverErrors = {};
         this.savedAt = null;
         this.pageError = "";
      },

      onShiftChange: function () {
         this.savedAt = null;
         this.serverErrors = {};
      },

      errorFor: function (index) {
         return this.errors[`shifts.${index}.start_time`] || this.errors[`shifts.${index}.end_time`] || "";
      },

      hasErrorsForShift: function (index) {
         return !!this.errorFor(index);
      },

      save: function () {
         if (this.hasClientErrors) {
            this.pageError = "Please fix the highlighted shifts before saving.";
            return;
         }
         this.saving = true;
         this.pageError = "";
         this.savedAt = null;
         axios
            .put(`/panel/employees/${this.employee.id}/schedule`, {
               shifts: this.shifts.map(pickShiftFields),
            })
            .then((res) => {
               this.shifts = (res.data.shifts || []).map(pickShiftFields);
               this.baseline = JSON.stringify(this.normalize(this.shifts));
               this.serverErrors = {};
               this.savedAt = new Date();
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  const raw = err.response.data.errors || {};
                  const mapped = {};
                  Object.entries(raw).forEach(([k, v]) => (mapped[k] = Array.isArray(v) ? v[0] : v));
                  this.serverErrors = mapped;
                  this.pageError = "Some shifts are invalid.";
               } else {
                  this.pageError = err.response?.data?.message || "Failed to save schedule.";
               }
            })
            .finally(() => (this.saving = false));
      },
   },
};
</script>

<style scoped>
.schedule-header {
   display: flex;
   justify-content: space-between;
   align-items: center;
   gap: 12px;
   flex-wrap: wrap;
   padding: 10px 14px;
   border: 1px solid var(--bs-border-color);
   border-radius: 10px;
   background: var(--bs-tertiary-bg);
}

.schedule-summary {
   display: flex;
   gap: 20px;
   align-items: center;
}

.schedule-summary-block {
   display: flex;
   flex-direction: column;
   align-items: flex-start;
}

.schedule-summary-value {
   font-size: 1.2rem;
   font-weight: 700;
   line-height: 1;
   color: var(--bs-emphasis-color);
}

.schedule-summary-unit {
   font-size: 0.75rem;
   font-weight: 500;
   color: var(--bs-secondary-color);
   margin-left: 2px;
}

.schedule-summary-label {
   font-size: 0.65rem;
   text-transform: uppercase;
   letter-spacing: 0.04em;
   color: var(--bs-secondary-color);
   margin-top: 2px;
}

.schedule-summary-status {
   margin-left: 4px;
}

.schedule-actions {
   display: flex;
   gap: 6px;
   align-items: center;
}

.schedule-days {
   display: flex;
   flex-direction: column;
   gap: 6px;
}

.schedule-day {
   border: 1px solid var(--bs-border-color);
   border-radius: 8px;
   padding: 8px 12px;
   background: var(--bs-body-bg);
}

.schedule-day.is-day-off {
   background: var(--bs-tertiary-bg);
}

.schedule-day.is-today {
   border-color: var(--bs-danger);
}

.schedule-day-head {
   display: flex;
   justify-content: space-between;
   align-items: center;
   gap: 8px;
}

.schedule-day-name {
   font-size: 0.92rem;
   font-weight: 600;
   display: flex;
   align-items: center;
   gap: 6px;
}

.schedule-today-tag {
   font-size: 0.65rem;
   font-weight: 500;
   color: var(--bs-danger);
   background: rgba(var(--bs-danger-rgb), 0.1);
   padding: 1px 6px;
   border-radius: 4px;
   text-transform: uppercase;
   letter-spacing: 0.04em;
}

.schedule-day-meta {
   display: flex;
   align-items: center;
   gap: 8px;
}

.schedule-hours {
   font-size: 0.78rem;
   font-weight: 600;
   color: var(--bs-success);
}

.schedule-off {
   font-size: 0.78rem;
   color: var(--bs-secondary-color);
   font-style: italic;
}

.schedule-menu-btn {
   color: var(--bs-secondary-color);
   line-height: 1;
   padding: 2px 4px !important;
}

.schedule-menu-btn:hover {
   color: var(--bs-emphasis-color);
}

.schedule-shifts {
   display: flex;
   flex-direction: column;
   gap: 4px;
   margin-top: 6px;
}

.schedule-shift {
   display: grid;
   grid-template-columns: auto auto auto auto auto 1fr;
   align-items: center;
   gap: 6px;
}

.schedule-shift.has-error .schedule-time {
   border-color: var(--bs-danger);
}

.schedule-time {
   width: 105px;
}

.schedule-dash {
   color: var(--bs-secondary-color);
}

.schedule-dur {
   font-size: 0.75rem;
   color: var(--bs-secondary-color);
   white-space: nowrap;
}

.schedule-remove {
   padding: 0 4px !important;
   line-height: 1;
}

.schedule-error {
   grid-column: 1 / -1;
   color: var(--bs-danger);
   font-size: 0.75rem;
   margin-top: -2px;
}

.schedule-add-link {
   align-self: flex-start;
   font-size: 0.8rem;
   margin-top: 2px;
   text-decoration: none;
   color: var(--bs-secondary-color);
}

.schedule-add-link:hover {
   color: var(--bs-danger);
}

@media (max-width: 600px) {
   .schedule-summary {
      gap: 14px;
   }
   .schedule-summary-value {
      font-size: 1rem;
   }
   .schedule-shift {
      grid-template-columns: 1fr auto 1fr auto auto;
   }
   .schedule-time {
      width: 100%;
   }
   .schedule-dur {
      grid-column: 1 / -1;
      text-align: right;
   }
   .schedule-error {
      grid-column: 1 / -1;
   }
}
</style>
