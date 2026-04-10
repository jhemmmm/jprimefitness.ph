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

export function todayDate() {
   return nowInAppTimezone().format(DATE_FORMAT);
}

export function nowTimestamp() {
   return nowInAppTimezone().toISOString();
}

export function todayIndexMondayFirst() {
   return (nowInAppTimezone().day() + 6) % 7;
}
