@extends('auth.layouts.app')

@section('title', 'Register')

@section('form')
    <h4 class="auth-form-title">Create an account</h4>
    <p class="auth-form-subtitle">Fill in the details below to register.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-4">
            <label for="first_name" class="form-label auth-label">First Name</label>
            <div class="auth-input-group">
                <i class="bi bi-person-fill auth-input-icon"></i>
                <input id="first_name" type="text" name="first_name"
                    class="form-control auth-input @error('first_name') is-invalid @enderror" placeholder="Juan"
                    value="{{ old('first_name') }}" required autocomplete="given-name" autofocus />
                @error('first_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="last_name" class="form-label auth-label">Last Name</label>
            <div class="auth-input-group">
                <i class="bi bi-person-fill auth-input-icon"></i>
                <input id="last_name" type="text" name="last_name"
                    class="form-control auth-input @error('last_name') is-invalid @enderror" placeholder="dela Cruz"
                    value="{{ old('last_name') }}" required autocomplete="family-name" />
                @error('last_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="email" class="form-label auth-label">Email Address</label>
            <div class="auth-input-group">
                <i class="bi bi-envelope-fill auth-input-icon"></i>
                <input id="email" type="email" name="email"
                    class="form-control auth-input @error('email') is-invalid @enderror" placeholder="you@example.com"
                    value="{{ old('email') }}" required autocomplete="email" />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label auth-label">Password</label>
            <div class="auth-input-group">
                <i class="bi bi-lock-fill auth-input-icon"></i>
                <input id="password" type="password" name="password"
                    class="form-control auth-input @error('password') is-invalid @enderror" placeholder="••••••••" required
                    autocomplete="new-password" />
                <button type="button" class="auth-pw-toggle" onclick="togglePw(this)" tabindex="-1">
                    <i class="bi bi-eye-slash-fill"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="password-confirm" class="form-label auth-label">Confirm Password</label>
            <div class="auth-input-group">
                <i class="bi bi-lock-fill auth-input-icon"></i>
                <input id="password-confirm" type="password" name="password_confirmation" class="form-control auth-input"
                    placeholder="••••••••" required autocomplete="new-password" />
                <button type="button" class="auth-pw-toggle" onclick="togglePw(this)" tabindex="-1">
                    <i class="bi bi-eye-slash-fill"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn auth-submit-btn w-100">
            <i class="bi bi-person-plus-fill me-2"></i>Create Account
        </button>

        <p class="auth-footer-note mt-4 mb-0">
            Already have an account?
            <a href="{{ route('login') }}" class="auth-forgot-link">Sign in</a>
        </p>
    </form>
@endsection

@push('scripts')
    <script>
        function togglePw(btn) {
            const input = btn.closest('.auth-input-group').querySelector('input');
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-fill';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-slash-fill';
            }
        }
    </script>
@endpush
