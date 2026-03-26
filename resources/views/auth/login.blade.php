@extends('auth.layouts.app')

@section('title', 'Sign In')

@section('form')
    <h4 class="auth-form-title">Welcome back</h4>
    <p class="auth-form-subtitle">Sign in to your admin account</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label auth-label">Email address</label>
            <div class="auth-input-group">
                <i class="bi bi-envelope-fill auth-input-icon"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                    class="form-control auth-input @error('email') is-invalid @enderror"
                    placeholder="admin@jprimefitness.ph" required autocomplete="email" autofocus />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="password" class="form-label auth-label mb-0">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="auth-forgot-link">Forgot password?</a>
                @endif
            </div>
            <div class="auth-input-group">
                <i class="bi bi-lock-fill auth-input-icon"></i>
                <input id="password" type="password" name="password"
                    class="form-control auth-input @error('password') is-invalid @enderror" placeholder="••••••••" required
                    autocomplete="current-password" />
                <button type="button" class="auth-pw-toggle" onclick="togglePw(this)" tabindex="-1">
                    <i class="bi bi-eye-slash-fill"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember"
                    {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label auth-remember" for="remember">Keep me signed in</label>
            </div>
        </div>

        <button type="submit" class="btn auth-submit-btn w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>

    <p class="auth-footer-note mt-4 mb-0">
        Having trouble? Contact your system administrator.
    </p>
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
