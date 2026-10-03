import dayjs from "dayjs";
import customParseFormat from "dayjs/plugin/customParseFormat.js";
import timezone from "dayjs/plugin/timezone.js";
import utc from "dayjs/plugin/utc.js";

dayjs.extend(customParseFormat);
dayjs.extend(utc);
dayjs.extend(timezone);

const DATE_FORMAT = "YYYY-MM-DD";
const DATE_TIME_INPUT_FORMAT = "YYYY-MM-DDTHH:mm";
const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const DATE_TIME_LOCAL_PATTERN = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/;
const OFFSET_PATTERN = /(Z|[+-]\d{2}:\d{2})$/i;

let configuredTimezone = null;

function appTimezone() {
   return globalThis.JPrime?.timezone || "UTC";
}

function configureTimezone() {
   const timezoneName = appTimezone();

   if (configuredTimezone !== timezoneName) {
      dayjs.tz.setDefault(timezoneName);
      configuredTimezone = timezoneName;
   }

   return timezoneName;
}

function nowInAppTimezone() {
   return dayjs().tz(configureTimezone());
}

function isEmpty(value) {
   return value === null || value === undefined || value === "";
}

function invalidDayjs() {
   return dayjs("");
}

function normalizeLocalDateTime(value) {
   return String(value).slice(0, 16);
}

export function appDayjs(value = null) {
   const timezoneName = configureTimezone();

   if (isEmpty(value)) {
      return invalidDayjs();
   }

   if (dayjs.isDayjs(value)) {
      return value.tz(timezoneName);
   }

   if (typeof value === "string") {
      const trimmedValue = value.trim();

      if (!trimmedValue) {
         return invalidDayjs();
      }

      if (DATE_ONLY_PATTERN.test(trimmedValue)) {
         return dayjs.tz(trimmedValue, DATE_FORMAT, timezoneName);
      }

      if (DATE_TIME_LOCAL_PATTERN.test(trimmedValue)) {
         return dayjs.tz(normalizeLocalDateTime(trimmedValue), DATE_TIME_INPUT_FORMAT, timezoneName);
      }

      if (OFFSET_PATTERN.test(trimmedValue)) {
         return dayjs(trimmedValue).tz(timezoneName);
      }

      return dayjs.tz(trimmedValue, timezoneName);
   }

   return dayjs(value).tz(timezoneName);
}

export function formatDate(value) {
   const parsedValue = appDayjs(value);

   return parsedValue.isValid() ? parsedValue.format("MMM D, YYYY") : "-";
}

export function formatDateTime(value) {
   const parsedValue = appDayjs(value);

   return parsedValue.isValid() ? parsedValue.format("MMM D, YYYY h:mm A") : "-";
}

export function formatTime(value) {
   const parsedValue = appDayjs(value);

   return parsedValue.isValid() ? parsedValue.format("h:mm A") : "-";
}

// Formats a bare "HH:mm" clock string (e.g. operating hours) — not a datetime.
export function formatClockTime(value, short = false) {
   if (!value) return "";
   const [hStr, mStr] = String(value).split(":");
   const h = parseInt(hStr, 10);
   const m = parseInt(mStr || "0", 10);
   if (isNaN(h)) return "";
   const am = h < 12;
   const display = h % 12 === 0 ? 12 : h % 12;
   if (short) return `${display}${am ? "am" : "pm"}`;
   const mm = m.toString().padStart(2, "0");
   return `${display}:${mm} ${am ? "AM" : "PM"}`;
}

export function formatDayRange(days) {
   if (days.length === 1) {
      return days[0];
   }

   return `${days[0]}-${days[days.length - 1]}`;
}

// Groups consecutive operating-hour entries sharing the same open/close times.
// Returns [{ days: [...], hours: "8:00 AM - 10:00 PM" }].
export function groupOperatingHours(entries) {
   const groups = [];

   (Array.isArray(entries) ? entries : []).forEach((day) => {
      const opening = formatClockTime(day.opening_time);
      const closing = formatClockTime(day.closing_time);

      if (!day.day || !opening || !closing) {
         return;
      }

      const hours = `${opening} - ${closing}`;
      const lastGroup = groups[groups.length - 1];

      if (lastGroup?.hours === hours) {
         lastGroup.days.push(day.day);

         return;
      }

      groups.push({
         days: [day.day],
         hours,
      });
   });

   return groups;
}

export function formatShortMonthDay(value) {
   const parsedValue = appDayjs(value);

   return parsedValue.isValid() ? parsedValue.format("MMM D") : "-";
}

export function toDateInputValue(value = null) {
   if (isEmpty(value)) {
      return nowInAppTimezone().format(DATE_FORMAT);
   }

   const parsedValue = appDayjs(value);

   return parsedValue.isValid() ? parsedValue.format(DATE_FORMAT) : "";
}

export function toDateTimeInputValue(value = null, fallbackToNow = true) {
   if (isEmpty(value)) {
      return fallbackToNow ? nowInAppTimezone().format(DATE_TIME_INPUT_FORMAT) : "";
   }

   const parsedValue = appDayjs(value);

   return parsedValue.isValid() ? parsedValue.format(DATE_TIME_INPUT_FORMAT) : "";
}

export function startOfCurrentMonthDate() {
   return nowInAppTimezone().startOf("month").format(DATE_FORMAT);
}

export function startOfPreviousMonthDate() {
   return nowInAppTimezone().subtract(1, "month").startOf("month").format(DATE_FORMAT);
}

export function endOfPreviousMonthDate() {
   return nowInAppTimezone().subtract(1, "month").endOf("month").format(DATE_FORMAT);
}

export function daysAgoDate(days) {
   return nowInAppTimezone().subtract(days, "day").format(DATE_FORMAT);
}

export function daysBetween(fromValue, toValue) {
   const from = appDayjs(fromValue);
   const to = appDayjs(toValue);

   if (!from.isValid() || !to.isValid()) {
      return null;
   }

   return to.startOf("day").diff(from.startOf("day"), "day");
}

export function todayDate() {
   return nowInAppTimezone().format(DATE_FORMAT);
}

export function nowTimestamp() {
   return nowInAppTimezone().toISOString();
}

export function todayIndexMondayFirst() {
   return (nowInAppTimezone().day() + 6) % 7;
}
