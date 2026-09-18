// First message per field from a Laravel 422 `errors` bag, for `is-invalid` bindings.
export function firstErrors(errors) {
   return Object.fromEntries(Object.entries(errors || {}).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]));
}
