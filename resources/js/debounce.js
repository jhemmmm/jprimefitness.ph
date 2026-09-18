// Trailing-edge debounce for Vue component methods. Timers are keyed on the
// component instance (`this`), so two mounted instances never cancel each other,
// and a call whose instance has since unmounted is dropped.
export function debounce(fn, wait = 500) {
   const timers = new WeakMap();

   return function (...args) {
      clearTimeout(timers.get(this));
      timers.set(
         this,
         setTimeout(() => {
            if (!this.$?.isUnmounted) fn.apply(this, args);
         }, wait)
      );
   };
}
