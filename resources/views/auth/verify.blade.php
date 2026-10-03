@extends('auth.layouts.app')

@section('title', 'Verify Email')

@section('form')
    <h4 class="auth-form-title">Verify your email</h4>
    <p class="auth-form-subtitle">A verification link was sent to your email address.</p>

    @if (session('resent'))
        <div class="alert alert-success py-2" style="font-size:0.84rem;border-radius:8px;">
            <i class="bi bi-check-circle-fill me-2"></i>A fresh verification link has been sent.
        </div>
    @endif

    <div class="p-4 mb-4" style="background:#f8f9fa;border-radius:10px;border:1.5px solid #e8eaed;">
        <div class="d-flex gap-3">
            <i class="bi bi-envelope-fill text-danger mt-1" style="font-size:1.2rem;flex-shrink:0;"></i>
            <p class="mb-0" style="font-size:0.85rem;color:#555;line-height:1.6;">
                Before continuing, please check your inbox for a verification link.
                If you did not receive the email, click the button below to resend it.
            </p>
        </div>
    </div>

    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit" class="btn auth-submit-btn w-100">
            <i class="bi bi-send-fill me-2"></i>Resend Verification Email
        </button>
    </form>

    <p class="auth-footer-note mt-4 mb-0">
        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
            class="auth-forgot-link">Sign out</a> and use a different account.
    </p>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
@endsection
