@extends('auth.layouts.app')

@section('title', 'Confirm Password')

@section('form')
    <h4 class="auth-form-title">Confirm your password</h4>
    <p class="auth-form-subtitle">Please re-enter your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-4">
            <label for="password" class="form-label auth-label">Password</label>
            <div class="auth-input-group">
                <i class="bi bi-lock-fill auth-input-icon"></i>
                <input id="password" type="password" name="password"
                    class="form-control auth-input @error('password') is-invalid @enderror" placeholder="••••••••" required
                    autocomplete="current-password" autofocus />
                <button type="button" class="auth-pw-toggle" onclick="togglePw(this)" tabindex="-1">
                    <i class="bi bi-eye-slash-fill"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <button type="submit" class="btn auth-submit-btn w-100">
            <i class="bi bi-shield-check me-2"></i>Confirm Password
        </button>

        @if (Route::has('password.request'))
            <p class="auth-footer-note mt-4 mb-0">
                <a href="{{ route('password.request') }}" class="auth-forgot-link">Forgot your password?</a>
            </p>
        @endif
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
