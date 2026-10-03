@extends('auth.layouts.app')

@section('title', 'Forgot Password')

@section('form')
    <h4 class="auth-form-title">Forgot your password?</h4>
    <p class="auth-form-subtitle">Enter your email and we'll send you a reset link.</p>

    @if (session('status'))
        <div class="alert alert-success py-2 mb-4" style="font-size:0.84rem;border-radius:8px;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label auth-label">Email address</label>
            <div class="auth-input-group">
                <i class="bi bi-envelope-fill auth-input-icon"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                    class="form-control auth-input @error('email') is-invalid @enderror" placeholder="your@email.com"
                    required autocomplete="email" autofocus />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <button type="submit" class="btn auth-submit-btn w-100">
            <i class="bi bi-send-fill me-2"></i>Send Reset Link
        </button>
    </form>

    <p class="auth-footer-note mt-4 mb-0">
        Remember your password?
        <a href="{{ route('login') }}" class="auth-forgot-link">Sign in</a>
    </p>
@endsection
