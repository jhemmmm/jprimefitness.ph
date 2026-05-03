@extends('home.layouts.app')

@section('title', $businessProfile->name . ' — Terms and Conditions')

@section('content')
<div style="padding-top: 80px;">
    <div class="container py-5" style="max-width: 820px;">

        <h1 class="fw-bold mb-1">Terms and Conditions</h1>
        <p class="text-muted mb-5">Last updated: {{ now()->format('F j, Y') }}</p>

        <p class="mb-4">
            These Terms and Conditions govern your use of the facilities and services of
            <strong>{{ $businessProfile->name }}</strong> ("the Gym", "we", "us"). By registering for a membership or
            purchasing a walk-in pass, you agree to be bound by these terms.
        </p>

        {{-- 1 --}}
        <h5 class="fw-bold mt-5 mb-3">1. Membership Activation</h5>
        <p>
            Your membership is only activated after full payment has been confirmed. Submitting a registration form
            does not constitute an active membership. Until payment is confirmed, your account will remain in a
            pending state and you will not have access to gym facilities under a membership plan.
        </p>
        <p>
            For online payments processed through PayMongo, activation occurs once the payment gateway confirms a
            successful transaction. For on-site payments, activation occurs upon receipt and verification of
            payment by gym staff.
        </p>

        {{-- 2 --}}
        <h5 class="fw-bold mt-5 mb-3">2. Membership Plans and Duration</h5>
        <p>
            Memberships are valid for the duration specified in the chosen plan (e.g., 30 days for Monthly, 90 days
            for 3 Months) beginning on the confirmed start date. Memberships are non-transferable and may only be
            used by the registered member.
        </p>
        <p>
            Walk-in passes (Daily Pass) grant access for a single visit on the date of purchase and cannot be
            applied toward a membership plan.
        </p>

        {{-- 3 --}}
        <h5 class="fw-bold mt-5 mb-3">3. Fees and Payment</h5>
        <p>
            All prices are in Philippine Peso (PHP) and are inclusive of applicable taxes. Prices are subject to
            change without prior notice; however, any change will not affect a membership plan already paid for.
        </p>
        <p>
            Accepted payment methods include GCash, Maya, major credit and debit cards (via PayMongo), and cash on
            site. The Gym is not liable for any charges, fees, or issues imposed by your bank or payment provider.
        </p>

        {{-- 4 --}}
        <h5 class="fw-bold mt-5 mb-3">4. Cancellation and Refund Policy</h5>
        <p>
            Memberships are generally non-refundable once activated. Refund requests before activation may be
            considered on a case-by-case basis at the sole discretion of management. To request a refund or discuss
            your membership, please contact us directly at the gym or through our official email.
        </p>
        <p>
            The Gym reserves the right to cancel or suspend a membership without refund if a member is found to be
            in violation of these Terms, gym rules, or applicable Philippine law.
        </p>

        {{-- 5 --}}
        <h5 class="fw-bold mt-5 mb-3">5. Gym Rules and Member Conduct</h5>
        <ul class="mb-3">
            <li class="mb-2">Members must present a valid QR code or ID upon entry.</li>
            <li class="mb-2">Proper athletic attire and clean, closed-toe shoes are required at all times on the gym floor.</li>
            <li class="mb-2">Members must re-rack weights and wipe down equipment after use.</li>
            <li class="mb-2">Aggressive, threatening, or disrespectful behaviour toward staff or other members will result in immediate suspension or termination of membership.</li>
            <li class="mb-2">The use of illegal substances or alcohol within gym premises is strictly prohibited.</li>
            <li class="mb-2">Smoking is not permitted anywhere on the premises.</li>
            <li class="mb-2">The Gym reserves the right to update its house rules at any time; updated rules will be posted on the premises.</li>
        </ul>

        {{-- 6 --}}
        <h5 class="fw-bold mt-5 mb-3">6. Health and Safety</h5>
        <p>
            By entering the gym, you confirm that you are in adequate physical health to participate in exercise
            activities. You are encouraged to consult a physician before beginning any new fitness programme,
            especially if you have a pre-existing medical condition, injury, or are pregnant.
        </p>
        <p>
            The Gym and its staff are not responsible for injuries sustained during the use of equipment or
            participation in any exercise activity. Members are responsible for using equipment safely and
            correctly. If you are unsure how to use a piece of equipment, please ask a staff member for guidance.
        </p>

        {{-- 7 --}}
        <h5 class="fw-bold mt-5 mb-3">7. Personal Belongings</h5>
        <p>
            The Gym is not responsible for the loss, theft, or damage of personal belongings brought onto the
            premises. Members are advised not to bring valuables and to use available lockers where provided.
        </p>

        {{-- 8 --}}
        <h5 class="fw-bold mt-5 mb-3">8. Privacy and Data Protection</h5>
        <p>
            We collect personal information (name, contact details, date of birth, emergency contact) during
            registration for the purpose of managing your membership and ensuring your safety. Your data will not
            be sold to third parties.
        </p>
        <p>
            We may use your contact information to send you updates regarding your membership, payment
            confirmations, and relevant gym announcements. By registering, you consent to receiving these
            communications. You may opt out at any time by contacting us.
        </p>
        <p>
            Your information is stored securely and handled in accordance with the
            <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> of the Philippines.
        </p>

        {{-- 9 --}}
        <h5 class="fw-bold mt-5 mb-3">9. Limitation of Liability</h5>
        <p>
            To the fullest extent permitted by applicable Philippine law, {{ $businessProfile->name }}, its owners,
            employees, and agents shall not be liable for any indirect, incidental, or consequential damages
            arising out of or in connection with your use of our facilities or services.
        </p>

        {{-- 10 --}}
        <h5 class="fw-bold mt-5 mb-3">10. Amendments</h5>
        <p>
            We reserve the right to update these Terms and Conditions at any time. The most current version will
            be available on our website and posted at the gym. Continued use of our facilities after any changes
            constitutes your acceptance of the updated terms.
        </p>

        {{-- 11 --}}
        <h5 class="fw-bold mt-5 mb-3">11. Governing Law</h5>
        <p>
            These Terms and Conditions shall be governed by and construed in accordance with the laws of the
            Republic of the Philippines. Any disputes shall be subject to the exclusive jurisdiction of the
            appropriate courts in the Philippines.
        </p>

        {{-- Contact --}}
        <div class="card border-0 bg-light mt-5 p-4">
            <h6 class="fw-bold mb-2">Questions?</h6>
            <p class="mb-0 text-muted small">
                If you have any questions about these Terms, please visit us at the gym or send us a message
                through the <a href="/#contact" class="text-danger fw-semibold">Contact section</a> on our home page.
            </p>
        </div>

        <div class="mt-5 pt-3 border-top">
            <a href="/" class="btn btn-outline-secondary rounded-1 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to Home
            </a>
        </div>

    </div>
</div>
@endsection
