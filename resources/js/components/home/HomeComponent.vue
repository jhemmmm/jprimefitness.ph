<template>
   <div class="content">
      <!-- Hero -->
      <section class="jprime-hero band-dark" id="home">
         <img class="band-bg" src="/images/gym/floor.webp" alt="" fetchpriority="high" />
         <div class="container">
            <h1>Show up. Get stronger.</h1>
            <p class="hero-sub">
               Independent gym<template v-if="business.city"> in {{ business.city }}</template>. Free weights, racks, cables, machines and cardio on one floor.
               <template v-if="startingLine"> {{ startingLine }}</template>
            </p>
            <div class="d-flex flex-wrap gap-2">
               <a href="#register" class="btn btn-primary btn-lg px-4">Join Now</a>
               <a href="#pricing" class="btn btn-outline-light btn-lg px-4">See Rates</a>
            </div>
         </div>
      </section>
      <div class="hero-strip">
         <div class="container d-flex flex-column flex-md-row gap-2 gap-md-4 py-3">
            <span v-if="addressLine"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ addressLine }}</span>
            <span><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ hoursShort }}</span>
            <a :href="directionsUrl" target="_blank" rel="noopener noreferrer" class="ms-md-auto fw-bold">Get directions</a>
         </div>
      </div>

      <!-- The gym -->
      <section class="band" id="about">
         <div class="container">
            <div class="row g-5 align-items-start">
               <div class="col-lg-5">
                  <h2>The gym</h2>
                  <p class="lead mb-4">One floor with everything you need: a full free-weight area, racks, cables and machines, mirrors down the length of the room and a cardio corner by the windows.</p>
                  <ul class="gym-list mb-4" v-if="amenities.length">
                     <li v-for="a in amenities" :key="a">{{ a }}</li>
                  </ul>
                  <a href="#pricing" class="link-dark link-offset-2 fw-bold">See membership rates</a>
               </div>
               <div class="col-lg-7">
                  <div class="gym-grid">
                     <button type="button" class="gym-grid-item" v-for="(photo, index) in photos" :key="photo.src" :aria-label="'View photo: ' + photo.alt" @click="openPhoto(index)">
                        <img :src="photo.src" :alt="photo.alt" loading="lazy" />
                     </button>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- Rates -->
      <section class="band border-top" id="pricing">
         <div class="container">
            <div class="row g-5">
               <div class="col-lg-4">
                  <h2>Rates</h2>
                  <p>All prices in Philippine peso. Student, senior citizen and PWD discounts are available and are paid at the front desk so staff can check your ID.</p>
                  <p class="mb-0" v-if="walkInLine"><strong>Walk-in:</strong> {{ walkInLine }}. Pay at the counter, no registration needed.</p>
               </div>
               <div class="col-lg-8">
                  <h3>Membership</h3>
                  <table class="table rate-table" v-if="membershipPlans.length">
                     <caption class="visually-hidden">Membership plans</caption>
                     <thead>
                        <tr>
                           <th scope="col">Plan</th>
                           <th scope="col" class="text-end">Price</th>
                           <th scope="col" class="text-end d-none d-md-table-cell">Per day</th>
                           <th scope="col"><span class="visually-hidden">Join</span></th>
                        </tr>
                     </thead>
                     <tbody>
                        <tr v-for="plan in membershipPlans" :key="'rp-' + plan.id">
                           <th scope="row">
                              {{ plan.name }}
                              <div class="small fw-normal text-muted">{{ plan.duration_days }} day<span v-if="plan.duration_days != 1">s</span><span v-if="plan.description"> &middot; {{ plan.description }}</span></div>
                           </th>
                           <td class="text-end rate-price">&#8369;{{ $filters.formatQuantity(plan.price) }}</td>
                           <td class="text-end text-muted d-none d-md-table-cell">&#8369;{{ $filters.formatMoney(perDay(plan)) }}</td>
                           <td class="text-end"><a :href="`#register?plan=${plan.id}`" class="btn btn-sm btn-outline-dark" @click.prevent="selectPlanForRegister(plan.id)">Join</a></td>
                        </tr>
                     </tbody>
                  </table>
                  <p class="text-muted" v-else>Membership plans will be published soon.</p>

                  <h3 class="mt-5">Personal training</h3>
                  <table class="table rate-table" v-if="ptProducts.length">
                     <caption class="visually-hidden">Personal training packages</caption>
                     <thead>
                        <tr>
                           <th scope="col">Package</th>
                           <th scope="col" class="text-end">Sessions</th>
                           <th scope="col" class="text-end">Price</th>
                           <th scope="col" class="text-end d-none d-md-table-cell">Per session</th>
                        </tr>
                     </thead>
                     <tbody>
                        <tr v-for="pt in ptProducts" :key="'pt-' + pt.id">
                           <th scope="row">
                              {{ pt.name }}
                              <div class="small fw-normal text-muted" v-if="pt.description">{{ pt.description }}</div>
                           </th>
                           <td class="text-end">{{ pt.session_count }}</td>
                           <td class="text-end rate-price">&#8369;{{ $filters.formatQuantity(pt.price) }}</td>
                           <td class="text-end text-muted d-none d-md-table-cell">&#8369;{{ $filters.formatMoney(perSession(pt)) }}</td>
                        </tr>
                     </tbody>
                  </table>
                  <p class="text-muted" v-else>PT packages will be published soon.</p>
                  <p class="mb-0">Coaching is booked at the gym. <a href="#contact" class="link-dark link-offset-2 fw-bold" @click="form.topic = 'Coaching / PT'">Ask about coaching</a></p>
               </div>
            </div>
         </div>
      </section>

      <!-- Membership -->
      <section class="band band-dark" id="membership">
         <img class="band-bg" src="/images/gym/sign.webp" alt="" loading="lazy" />
         <div class="container">
            <div class="row g-5">
               <div class="col-lg-6">
                  <h2>How membership works</h2>
                  <ol class="step-list mb-4">
                     <li><span><strong>Register online.</strong> Fill in the form below and pick a plan.</span></li>
                     <li><span><strong>Pay online or at the gym.</strong> GCash, Maya or Instant Pay online, or cash at the front desk.</span></li>
                     <li><span><strong>Get your QR card.</strong> Once payment is confirmed, your membership QR is emailed to you.</span></li>
                     <li><span><strong>Scan in and train.</strong> Show the QR at the kiosk on every visit.</span></li>
                  </ol>
                  <a href="#register" class="btn btn-primary btn-lg px-4">Join Now</a>
               </div>
               <div class="col-lg-5 offset-lg-1">
                  <h3>Every membership includes</h3>
                  <ul class="gym-list">
                     <li>Full access to the floor during opening hours</li>
                     <li>QR check-in at the kiosk, no sign-in sheet</li>
                     <li>Digital membership card sent by email</li>
                     <li>A reminder before your plan runs out</li>
                     <li>Personal training add-ons with every session logged</li>
                  </ul>
               </div>
            </div>
         </div>
      </section>

      <!-- Register -->
      <section class="band bg-light" id="register">
         <div class="container">
            <h2>Join {{ business.name }}</h2>
            <p class="mb-5">Fill in your details, pick a plan and choose how to pay. Takes about two minutes.</p>
            <div class="row g-5">
               <div class="col-lg-7">
                  <registration-form ref="registrationForm" :business="business" :rate-plans="ratePlans" />
               </div>
               <aside class="col-lg-4 offset-lg-1">
                  <h3>After you submit</h3>
                  <ol class="step-list mb-5">
                     <li><span>Your registration is saved right away.</span></li>
                     <li><span>Pay online with GCash, Maya or Instant Pay, or at the front desk when you come in.</span></li>
                     <li><span>Once payment is confirmed, your QR membership card is emailed to you.</span></li>
                  </ol>
                  <h3>Bring on your first visit</h3>
                  <ul class="gym-list">
                     <li>Valid ID (school, senior or PWD ID if you chose a discount)</li>
                     <li>Payment, if you did not pay online</li>
                     <li>Training clothes and clean indoor shoes</li>
                     <li>Towel and water bottle</li>
                  </ul>
               </aside>
            </div>
         </div>
      </section>

      <!-- Contact -->
      <section class="band border-top" id="contact">
         <div class="container">
            <div class="row g-5">
               <div class="col-lg-5">
                  <h2>Find us</h2>
                  <address class="mb-4">
                     <strong>{{ business.name }}</strong><br />
                     {{ addressLine || "Address coming soon" }}
                  </address>
                  <table class="table table-borderless w-auto hours-table mb-3" v-if="operatingHourGroups.length">
                     <caption class="visually-hidden">Opening hours</caption>
                     <tbody>
                        <tr v-for="g in operatingHourGroups" :key="g.days">
                           <th scope="row">{{ g.days }}</th>
                           <td>{{ g.hours }}</td>
                        </tr>
                     </tbody>
                  </table>
                  <a :href="directionsUrl" target="_blank" rel="noopener noreferrer" class="link-dark link-offset-2 fw-bold">Get directions</a>
                  <div class="ratio ratio-4x3 border mt-4" v-if="addressLine">
                     <iframe :src="mapEmbedUrl" :title="'Map to ' + business.name" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                  </div>
               </div>

               <div class="col-lg-6 offset-lg-1">
                  <h2>Send a message</h2>
                  <div v-if="contact.success" class="alert alert-success py-2" role="alert">
                     {{ contact.success }}
                  </div>
                  <div v-if="contact.bannerError" class="alert alert-danger py-2" role="alert">
                     {{ contact.bannerError }}
                  </div>
                  <form method="POST" action="#" @submit.prevent="onSubmit" novalidate>
                     <div class="mb-3">
                        <label for="c-name" class="form-label small fw-semibold">Full Name</label>
                        <input id="c-name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': contactErrors.name }" placeholder="Your name" />
                        <div v-if="contactErrors.name" class="invalid-feedback d-block">{{ contactErrors.name[0] }}</div>
                     </div>
                     <div class="mb-3">
                        <label for="c-email" class="form-label small fw-semibold">Email</label>
                        <input id="c-email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': contactErrors.email }" placeholder="you@example.com" />
                        <div v-if="contactErrors.email" class="invalid-feedback d-block">{{ contactErrors.email[0] }}</div>
                     </div>
                     <div class="mb-3">
                        <label for="c-contact" class="form-label small fw-semibold">Phone / Messenger <span class="text-muted fw-normal">(optional)</span></label>
                        <input id="c-contact" v-model="form.contact" type="text" class="form-control" :class="{ 'is-invalid': contactErrors.contact }" placeholder="Alternate way to reach you" />
                        <div v-if="contactErrors.contact" class="invalid-feedback d-block">{{ contactErrors.contact[0] }}</div>
                     </div>
                     <div class="mb-3">
                        <label for="c-topic" class="form-label small fw-semibold">Inquiry</label>
                        <select id="c-topic" v-model="form.topic" class="form-select" :class="{ 'is-invalid': contactErrors.topic }">
                           <option>Membership</option>
                           <option>Coaching / PT</option>
                           <option>Walk-in Visit</option>
                           <option>General Question</option>
                        </select>
                        <div v-if="contactErrors.topic" class="invalid-feedback d-block">{{ contactErrors.topic[0] }}</div>
                     </div>
                     <div class="mb-3">
                        <label for="c-message" class="form-label small fw-semibold">Message</label>
                        <textarea id="c-message" v-model="form.message" class="form-control" :class="{ 'is-invalid': contactErrors.message }" rows="4" placeholder="Type your message..."></textarea>
                        <div v-if="contactErrors.message" class="invalid-feedback d-block">{{ contactErrors.message[0] }}</div>
                     </div>
                     <button type="submit" class="btn btn-primary py-2 px-4" :disabled="contact.submitting">
                        <span v-if="contact.submitting"><span class="spinner-border spinner-border-sm me-2"></span>Sending…</span>
                        <span v-else>Send message</span>
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </section>

      <!-- Photo lightbox -->
      <div class="modal fade gym-lightbox" ref="lightbox" tabindex="-1" aria-label="Gym photos" aria-hidden="true">
         <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
               <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
               <div id="gym-carousel" class="carousel slide" ref="carousel">
                  <div class="carousel-inner">
                     <div class="carousel-item" :class="{ active: index === activePhoto }" v-for="(photo, index) in photos" :key="'slide-' + photo.src">
                        <img :src="photo.src" :alt="photo.alt" class="d-block w-100" loading="lazy" />
                     </div>
                  </div>
                  <button type="button" class="carousel-control-prev" data-bs-target="#gym-carousel" data-bs-slide="prev">
                     <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                     <span class="visually-hidden">Previous</span>
                  </button>
                  <button type="button" class="carousel-control-next" ref="nextButton" data-bs-target="#gym-carousel" data-bs-slide="next">
                     <span class="carousel-control-next-icon" aria-hidden="true"></span>
                     <span class="visually-hidden">Next</span>
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { getRecaptchaToken } from "../../recaptcha";

export default {
   props: {
      business: { type: Object, default: () => ({}) },
      ratePlans: { type: Array, default: () => [] },
      ptProducts: { type: Array, default: () => [] },
   },
   data: function () {
      return {
         form: { name: "", email: "", contact: "", topic: "Membership", message: "" },
         contact: { submitting: false, success: null, bannerError: null },
         contactErrors: {},
         activePhoto: 0,
         photos: [
            { src: "/images/gym/weights.webp", alt: "Dumbbell rack, olympic plates and squat rack on the free-weight floor" },
            { src: "/images/gym/bench.webp", alt: "Adjustable bench and cable machine beside the windows" },
            { src: "/images/gym/cardio.webp", alt: "Two treadmills, an elliptical and a spin bike in the cardio corner" },
            { src: "/images/gym/sign.webp", alt: "The JPrime Fitness sign on the black wall by the dumbbell rack" },
         ],
      };
   },
   mounted: function () {
      // Move keyboard focus onto the carousel when the lightbox opens so arrow keys work right away.
      this.$refs.lightbox.addEventListener("shown.bs.modal", () => this.$refs.nextButton.focus());
      // Bootstrap moves .active itself on prev/next; keep our state in step so the next openPhoto() patches the right slide.
      this.$refs.carousel.addEventListener("slid.bs.carousel", (e) => {
         this.activePhoto = e.to;
      });
   },
   beforeUnmount: function () {
      Modal.getInstance(this.$refs.lightbox)?.dispose();
   },
   computed: {
      addressLine: function () {
         return this.$filters.addressLine(this.business);
      },
      amenities: function () {
         return Array.isArray(this.business.amenities) ? this.business.amenities.filter(Boolean) : [];
      },
      hoursShort: function () {
         if (this.operatingHourGroups.length) {
            return this.operatingHourGroups.map((group) => `${group.days} ${group.hoursShort}`).join(", ");
         }

         return "Open daily";
      },
      weeklyOperatingHours: function () {
         const entries = Array.isArray(this.business.operating_hours) ? this.business.operating_hours : [];

         return entries
            .map((entry) => ({
               day: entry.day,
               opening_time: entry.opening_time,
               closing_time: entry.closing_time,
            }))
            .filter((entry) => entry.day && entry.opening_time && entry.closing_time);
      },
      operatingHourGroups: function () {
         const groups = [];

         this.weeklyOperatingHours.forEach((day) => {
            const opening = this.$filters.formatTime(day.opening_time);
            const closing = this.$filters.formatTime(day.closing_time);
            const openingShort = this.$filters.formatTime(day.opening_time, true);
            const closingShort = this.$filters.formatTime(day.closing_time, true);

            if (!opening || !closing) {
               return;
            }

            const hours = `${opening} – ${closing}`;
            const hoursShort = `${openingShort}–${closingShort}`;
            const lastGroup = groups[groups.length - 1];

            if (lastGroup?.hours === hours) {
               lastGroup.days.push(day.day);

               return;
            }

            groups.push({
               days: [day.day],
               hours,
               hoursShort,
            });
         });

         return groups.map((group) => ({
            days: this.formatDayRange(group.days),
            hours: group.hours,
            hoursShort: group.hoursShort,
         }));
      },
      mapQuery: function () {
         return encodeURIComponent(this.addressLine || this.business.name || "");
      },
      directionsUrl: function () {
         return "https://www.google.com/maps/search/?api=1&query=" + this.mapQuery;
      },
      mapEmbedUrl: function () {
         return "https://www.google.com/maps?q=" + this.mapQuery + "&output=embed";
      },
      // Mirrors RegistrationForm.membershipPlans so "Join" only appears on plans the form can select.
      membershipPlans: function () {
         return this.ratePlans.filter((p) => !p.is_walk_in_only);
      },
      walkInLine: function () {
         return this.ratePlans
            .filter((p) => p.is_walk_in_only)
            .map((p) => `${p.name} ₱${this.$filters.formatQuantity(p.price)}`)
            .join(" · ");
      },
      // "Memberships from ₱1,600 a month." / "… ₱800 for 14 days." / "… ₱800." when the duration is unknown
      startingLine: function () {
         const plan = this.membershipPlans.reduce((best, p) => (!best || parseFloat(p.price) < parseFloat(best.price) ? p : best), null);
         if (!plan) return "";
         const days = parseInt(plan.duration_days || 0, 10);
         const term = { 1: "a day", 7: "a week", 30: "a month", 90: "a quarter", 180: "a half year", 365: "a year" }[days] || (days > 0 ? `for ${days} days` : "");
         return `Memberships from ₱${this.$filters.formatQuantity(plan.price)}${term ? " " + term : ""}.`;
      },
   },
   methods: {
      openPhoto: function (index) {
         this.activePhoto = index; // set before show() so the right slide is in place with no transition
         Modal.getOrCreateInstance(this.$refs.lightbox).show();
      },
      perDay: function (plan) {
         const price = parseFloat(plan.price || 0);
         const days = parseInt(plan.duration_days || 0, 10);
         return days > 0 ? price / days : 0;
      },
      perSession: function (pt) {
         const price = parseFloat(pt.price || 0);
         const n = parseInt(pt.session_count || 0, 10);
         return n > 0 ? price / n : 0;
      },
      formatDayRange: function (days) {
         if (days.length === 1) return days[0];
         return `${days[0]}-${days[days.length - 1]}`;
      },
      onSubmit: function () {
         if (this.contact.submitting) return;
         this.contact.submitting = true;
         this.contact.bannerError = null;
         this.contact.success = null;
         this.contactErrors = {};

         getRecaptchaToken("register")
            .then((recaptchaToken) =>
               axios.post("/contact", {
                  name: this.form.name,
                  email: this.form.email,
                  contact: this.form.contact || null,
                  topic: this.form.topic,
                  message: this.form.message,
                  recaptcha_token: recaptchaToken,
               }),
            )
            .then((res) => {
               this.contact.success = res.data?.message || "Thanks! We received your message and will reply as soon as we can.";
               this.form.name = "";
               this.form.email = "";
               this.form.contact = "";
               this.form.topic = "Membership";
               this.form.message = "";
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.contactErrors = err.response.data.errors || {};
                  this.contact.bannerError = err.response.data.message || "Please correct the highlighted fields.";
               } else {
                  this.contact.bannerError = err.response?.data?.message || "Network error. Please check your connection and try again.";
               }
            })
            .finally(() => {
               this.contact.submitting = false;
            });
      },
      selectPlanForRegister: function (planId) {
         this.$refs.registrationForm.selectPlan(planId);
         const target = document.getElementById("register");
         if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
      },
   },
};
</script>
