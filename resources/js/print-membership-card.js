import { formatDate } from "./dates";

function escapeHtml(value) {
   if (value === null || value === undefined) {
      return "";
   }

   return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
}

function formatExpiration(qr) {
   if (!qr.end_date) {
      return "OPEN-ENDED";
   }

   return formatDate(qr.end_date).toUpperCase();
}

function buildHtml(qr) {
   const logoUrl = window.location.origin + "/logo.png";
   const memberName = escapeHtml(qr.member_name || "MEMBER");
   const planName = escapeHtml((qr.plan_name || "").toUpperCase());
   const expiration = escapeHtml(formatExpiration(qr));
   const qrSrc = escapeHtml(qr.qr_data_uri || "");
   const memberId = escapeHtml(qr.membership_id ? "#" + String(qr.membership_id).padStart(6, "0") : "");

   return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>JPrime Fitness Membership Card</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=Oswald:400,500,600,700" rel="stylesheet">
<style>
   *, *::before, *::after { box-sizing: border-box; }

   @page {
      size: 3.375in 2.125in;
      margin: 0;
   }

   html, body {
      margin: 0;
      padding: 0;
      background: #2c2c30;
      font-family: "Oswald", "Segoe UI", Arial, sans-serif;
      color: #fff;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
   }

   .stage {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
   }

   .card {
      width: 3.375in;
      height: 2.125in;
      position: relative;
      overflow: hidden;
      border-radius: 12px;
      background:
         radial-gradient(circle at 20% 25%, rgba(255,255,255,0.06), transparent 55%),
         radial-gradient(circle at 80% 80%, rgba(225,20,26,0.18), transparent 60%),
         linear-gradient(135deg, #131316 0%, #0a0a0c 100%);
      box-shadow: 0 18px 40px rgba(0,0,0,0.45);
   }

   .card::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image:
         repeating-linear-gradient(45deg, rgba(255,255,255,0.015) 0 2px, transparent 2px 4px);
      pointer-events: none;
   }

   .accent {
      position: absolute;
      top: -10%;
      right: -22%;
      width: 60%;
      height: 130%;
      background: linear-gradient(160deg, #e1141a 0%, #b00d12 100%);
      transform: skewX(-18deg);
      box-shadow: -8px 0 18px rgba(0,0,0,0.35);
   }

   .accent::after {
      content: "";
      position: absolute;
      inset: 0;
      background:
         repeating-linear-gradient(45deg, rgba(255,255,255,0.04) 0 3px, transparent 3px 6px);
   }

   .stripe {
      position: absolute;
      right: 42%;
      top: 0;
      bottom: 0;
      width: 3px;
      background: #fff;
      transform: skewX(-18deg);
      opacity: 0.95;
   }

   .grid {
      position: absolute;
      inset: 0;
      display: grid;
      grid-template-columns: 1.35fr 1fr;
      grid-template-rows: auto 1fr auto;
      padding: 12px 14px;
      gap: 0 10px;
   }

   .brand {
      grid-column: 1 / 2;
      grid-row: 1 / 2;
      display: flex;
      align-items: center;
      gap: 8px;
   }

   .brand img {
      width: 28px;
      height: 28px;
      object-fit: contain;
      filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));
   }

   .brand .wordmark {
      font-weight: 700;
      font-size: 17px;
      letter-spacing: 1.5px;
      line-height: 1;
   }

   .brand .wordmark .accent-word {
      color: #e1141a;
   }

   .tagline {
      grid-column: 1 / 2;
      grid-row: 1 / 2;
      align-self: end;
      margin-top: 30px;
      font-size: 7px;
      font-weight: 500;
      letter-spacing: 3px;
      color: rgba(255,255,255,0.7);
   }

   .tagline .dot {
      color: #e1141a;
      margin: 0 4px;
   }

   .info {
      grid-column: 1 / 2;
      grid-row: 2 / 3;
      align-self: end;
      padding-bottom: 4px;
   }

   .info .label {
      font-size: 8px;
      font-weight: 500;
      letter-spacing: 2.5px;
      color: rgba(255,255,255,0.55);
      margin-bottom: 2px;
   }

   .info .name {
      font-size: 18px;
      font-weight: 600;
      letter-spacing: 0.5px;
      line-height: 1.05;
      text-transform: uppercase;
      color: #fff;
      max-width: 1.9in;
      word-break: break-word;
   }

   .meta {
      grid-column: 1 / 2;
      grid-row: 3 / 4;
      display: flex;
      gap: 18px;
      align-items: flex-end;
   }

   .meta .block {
      display: flex;
      flex-direction: column;
   }

   .meta .label {
      font-size: 7px;
      font-weight: 500;
      letter-spacing: 2.5px;
      color: rgba(255,255,255,0.55);
   }

   .meta .value {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 1px;
      color: #fff;
      margin-top: 2px;
   }

   .qr-wrap {
      grid-column: 2 / 3;
      grid-row: 1 / 4;
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding-right: 2px;
   }

   .qr-tile {
      background: #fff;
      padding: 4px;
      border-radius: 6px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.35);
   }

   .qr-tile img {
      display: block;
      width: 1.05in;
      height: 1.05in;
   }

   .qr-caption {
      font-size: 7px;
      font-weight: 600;
      letter-spacing: 2px;
      color: #fff;
      text-shadow: 0 1px 2px rgba(0,0,0,0.5);
   }

   .badge {
      position: absolute;
      bottom: 10px;
      right: 14px;
      z-index: 3;
      font-size: 7px;
      font-weight: 600;
      letter-spacing: 2px;
      color: rgba(255,255,255,0.85);
      text-shadow: 0 1px 2px rgba(0,0,0,0.45);
   }

   .toolbar {
      position: fixed;
      top: 16px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 8px;
   }

   .toolbar button {
      font: 600 12px "Oswald", sans-serif;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      padding: 8px 16px;
      border-radius: 6px;
      border: 0;
      cursor: pointer;
      background: #e1141a;
      color: #fff;
   }

   .toolbar button.secondary {
      background: rgba(255,255,255,0.12);
      color: #fff;
   }

   @media print {
      .toolbar { display: none; }
      .stage { padding: 0; min-height: auto; }
      .card { box-shadow: none; border-radius: 0; }
      html, body { background: #fff; }
   }
</style>
</head>
<body>
   <div class="toolbar">
      <button type="button" onclick="window.print()">Print</button>
      <button type="button" class="secondary" onclick="window.close()">Close</button>
   </div>

   <div class="stage">
      <div class="card">
         <div class="accent"></div>
         <div class="stripe"></div>

         <div class="grid">
            <div class="brand">
               <img src="${escapeHtml(logoUrl)}" alt="JPrime Fitness">
               <div class="wordmark">JPRIME <span class="accent-word">FITNESS</span></div>
            </div>

            <div class="tagline">STRENGTH<span class="dot">&bull;</span>DISCIPLINE<span class="dot">&bull;</span>RESULTS</div>

            <div class="info">
               <div class="label">Member</div>
               <div class="name">${memberName}</div>
            </div>

            <div class="meta">
               <div class="block">
                  <div class="label">Valid Thru</div>
                  <div class="value">${expiration}</div>
               </div>
               ${planName ? `<div class="block"><div class="label">Plan</div><div class="value">${planName}</div></div>` : ""}
            </div>

            <div class="qr-wrap">
               <div class="qr-tile"><img src="${qrSrc}" alt="Membership QR"></div>
               <div class="qr-caption">SCAN TO CHECK-IN</div>
            </div>
         </div>

         ${memberId ? `<div class="badge">${memberId}</div>` : ""}
      </div>
   </div>

   <script>
      (function () {
         function ready() {
            var images = Array.prototype.slice.call(document.images);
            var pending = images.filter(function (img) { return !img.complete; }).length;
            if (pending === 0) {
               setTimeout(function () { window.focus(); window.print(); }, 250);
               return;
            }
            images.forEach(function (img) {
               img.addEventListener("load", function () {
                  pending -= 1;
                  if (pending === 0) {
                     setTimeout(function () { window.focus(); window.print(); }, 250);
                  }
               });
               img.addEventListener("error", function () {
                  pending -= 1;
                  if (pending === 0) {
                     setTimeout(function () { window.focus(); window.print(); }, 250);
                  }
               });
            });
         }

         if (document.readyState === "complete") {
            ready();
         } else {
            window.addEventListener("load", ready);
         }
      })();
   </script>
</body>
</html>`;
}

export function printMembershipCard(qr) {
   if (!qr || !qr.qr_data_uri) {
      return false;
   }

   const win = window.open("", "_blank", "width=960,height=640");
   if (!win) {
      return false;
   }

   win.document.open();
   win.document.write(buildHtml(qr));
   win.document.close();
   return true;
}
