@extends('auth.layouts.app')

@section('title', 'Reset Password')

@section('form')
    <h4 class="auth-form-title">Set a new password</h4>
    <p class="auth-form-subtitle">Choose a strong password for your account.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label auth-label">Email address</label>
            <div class="auth-input-group">
                <i class="bi bi-envelope-fill auth-input-icon"></i>
                <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}"
                    class="form-control auth-input @error('email') is-invalid @enderror" required autocomplete="email"
                    autofocus />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label auth-label">New password</label>
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
            <label for="password-confirm" class="form-label auth-label">Confirm new password</label>
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
            <i class="bi bi-check-lg me-2"></i>Reset Password
        </button>
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
