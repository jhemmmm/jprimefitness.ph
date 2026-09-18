@extends('home.layouts.app')

@section('title', $businessProfile->name . ' - Renew your membership')

@section('content')
    <section class="py-5 bg-light" style="min-height: 70vh;">
        <div class="container">
            <div class="section-eyebrow mb-5">Existing members</div>
            <h2 class="fw-bold mb-1">Renew your membership</h2>
            <p class="text-muted mb-5">Pick a plan and choose how you'd like to pay. Your details stay as they are on file.</p>

            <div class="row g-4">
                <div class="col-lg-7">
                    <registration-form renew :business='@json($businessProfile)' :rate-plans='@json($ratePlans)' initial-email="{{ $email }}" initial-discount="{{ $discount }}"></registration-form>
                </div>

                <div class="col-lg-5">
                    <div class="card mb-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">What happens next</h5>
                            <div class="step-item">
                                <div class="step-number flex-shrink-0">1</div>
                                <div>
                                    <h6 class="fw-bold mb-1">Pick a plan</h6>
                                    <p class="text-muted small mb-0">This link is tied to your membership record - your details stay as they are.</p>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number flex-shrink-0">2</div>
                                <div>
                                    <h6 class="fw-bold mb-1">Pay</h6>
                                    <p class="text-muted small mb-0">Online via PayMongo, or at the front desk. Student, senior citizen and PWD rates are paid on-site so staff can check your ID.</p>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number flex-shrink-0">3</div>
                                <div>
                                    <h6 class="fw-bold mb-1">Keep training</h6>
                                    <p class="text-muted small mb-0">Your new plan starts right after your current one ends (or today if it has expired) and a fresh QR code is emailed to you.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-2">New here?</h5>
                            <p class="text-muted small mb-3">If you've never been a member, register on the home page instead.</p>
                            <a href="/#register" class="btn btn-outline-danger rounded-1 fw-semibold">Create a membership</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
