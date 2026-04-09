const regionNames = typeof Intl !== "undefined" && typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "region" }) : null;

const COUNTRY_CODES = [
   "PH",
   "US",
   "CA",
   "AU",
   "NZ",
   "GB",
   "IE",
   "SG",
   "MY",
   "ID",
   "TH",
   "VN",
   "JP",
   "KR",
   "HK",
   "TW",
   "CN",
   "IN",
   "AE",
   "SA",
   "QA",
   "KW",
   "BH",
   "OM",
   "DE",
   "FR",
   "ES",
   "IT",
   "NL",
   "CH",
   "SE",
   "NO",
   "DK",
   "ZA",
   "BR",
   "MX",
];

const fallbackTimezones = [
   "UTC",
   "Asia/Manila",
   "Asia/Singapore",
   "Asia/Hong_Kong",
   "Asia/Tokyo",
   "Asia/Seoul",
   "Asia/Bangkok",
   "Asia/Kuala_Lumpur",
   "Asia/Jakarta",
   "Asia/Dubai",
   "Asia/Riyadh",
   "Europe/London",
   "Europe/Paris",
   "Europe/Berlin",
   "America/New_York",
   "America/Chicago",
   "America/Los_Angeles",
   "Australia/Sydney",
];

const supportedTimezones = typeof Intl !== "undefined" && typeof Intl.supportedValuesOf === "function" ? Intl.supportedValuesOf("timeZone") : [];

export const COUNTRY_OPTIONS = COUNTRY_CODES.map((code) => ({
   value: code,
   label: `${regionNames?.of(code) || code} (${code})`,
}));

export const TIMEZONE_OPTIONS = (supportedTimezones.length ? supportedTimezones : fallbackTimezones)
   .map((timezone) => ({
      value: timezone,
      label: timezone,
   }))
   .sort((left, right) => left.label.localeCompare(right.label));

export function ensureSelectOption(options, value, formatter = null) {
   if (!value) {
      return options;
   }

   if (options.some((option) => option.value === value)) {
      return options;
   }

   return [
      {
         value,
         label: formatter ? formatter(value) : value,
      },
      ...options,
   ];
}
