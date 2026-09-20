@extends('home.layouts.app')

@section('title', $businessProfile->name . ' - Renew your membership')

@section('content')
    <section class="band bg-light" style="min-height: 70vh; padding-top: 8rem;">
        <div class="container">
            <h2>Renew your membership</h2>
            <p class="mb-5">Pick a plan and choose how you'd like to pay. Your details stay as they are on file.</p>

            <div class="row g-5">
                <div class="col-lg-7">
                    <registration-form renew :business='@json($businessProfile)' :rate-plans='@json($ratePlans)' initial-email="{{ $email }}" initial-discount="{{ $discount }}"></registration-form>
                </div>

                <aside class="col-lg-4 offset-lg-1">
                    <h3>What happens next</h3>
                    <ol class="step-list mb-5">
                        <li><span><strong>Pick a plan.</strong> This link is tied to your membership record - your details stay as they are.</span></li>
                        <li><span><strong>Pay.</strong> Online via PayMongo, or at the front desk. Student, senior citizen and PWD rates are paid on-site so staff can check your ID.</span></li>
                        <li><span><strong>Keep training.</strong> Your new plan starts right after your current one ends (or today if it has expired) and a fresh QR code is emailed to you.</span></li>
                    </ol>

                    <h3>New here?</h3>
                    <p class="mb-3">If you've never been a member, register on the home page instead.</p>
                    <a href="/#register" class="btn btn-outline-dark">Create a membership</a>
                </aside>
            </div>
        </div>
    </section>
@endsection
