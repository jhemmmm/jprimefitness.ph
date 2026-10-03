@extends('home.layouts.app')

@section('title', $businessProfile->name . ' - Registration ' . ($result === 'success' ? 'Successful' : 'Cancelled'))

@section('content')
    <section class="py-5" style="min-height: 70vh; background: #f8f9fa;">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-5 text-center">
                            @if ($result === 'success')
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem"></i>
                                <h2 class="fw-bold mt-3 mb-2">Payment confirmed</h2>
                                <p class="text-muted mb-4">
                                    Thanks for paying online. Your membership is being activated and your QR code will arrive in your inbox shortly.
                                </p>
                            @else
                                <i class="bi bi-x-circle-fill text-warning" style="font-size: 4rem"></i>
                                <h2 class="fw-bold mt-3 mb-2">Payment was cancelled</h2>
                                <p class="text-muted mb-4">
                                    No worries - your registration is saved. Drop by the gym any time to complete payment, or try again from the home page.
                                </p>
                            @endif
                            <a href="/" class="btn btn-danger rounded-1 fw-semibold px-4 py-2">
                                <i class="bi bi-arrow-left me-1"></i>Back to home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
