import {
   daysAgoDate,
   endOfPreviousMonthDate,
   formatDate,
   startOfCurrentMonthDate,
   startOfPreviousMonthDate,
   todayDate,
} from "../dates";

export default {
   computed: {
      rangePresets: function () {
         return [
            { key: "today", label: "Today" },
            { key: "yesterday", label: "Yesterday" },
            { key: "7d", label: "Last 7 Days" },
            { key: "30d", label: "Last 30 Days" },
            { key: "this_month", label: "This Month" },
            { key: "last_month", label: "Last Month" },
         ];
      },
      rangeBoundsByKey: function () {
         const today = todayDate();

         return {
            today: { from: today, to: today },
            yesterday: { from: daysAgoDate(1), to: daysAgoDate(1) },
            "7d": { from: daysAgoDate(6), to: today },
            "30d": { from: daysAgoDate(29), to: today },
            this_month: { from: startOfCurrentMonthDate(), to: today },
            last_month: { from: startOfPreviousMonthDate(), to: endOfPreviousMonthDate() },
         };
      },
      activeRangeKey: function () {
         const entry = Object.entries(this.rangeBoundsByKey).find(
            ([, range]) => range.from === this.filters.date_from && range.to === this.filters.date_to,
         );

         return entry ? entry[0] : null;
      },
      activeRangeLabel: function () {
         if (!this.filters.date_from && !this.filters.date_to) {
            return "All time";
         }

         const from = this.filters.date_from ? formatDate(this.filters.date_from) : "Earliest";
         const to = this.filters.date_to ? formatDate(this.filters.date_to) : "Today";

         return `${from} → ${to}`;
      },
   },
};
