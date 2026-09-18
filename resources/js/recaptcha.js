// Resolves to "" when reCAPTCHA isn't configured (local/testing) so callers can always send the token.
export function getRecaptchaToken(action) {
   const siteKey = window.JPrime && window.JPrime.recaptchaSiteKey;
   if (!siteKey || !window.grecaptcha) return Promise.resolve("");
   return new Promise((resolve) => {
      window.grecaptcha.ready(function () {
         window.grecaptcha
            .execute(siteKey, { action })
            .then((token) => resolve(token || ""))
            .catch(() => resolve(""));
      });
   });
}
