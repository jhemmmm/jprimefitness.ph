<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'JPRIME FITNESS')</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800,900|Oswald:400,500,600,700" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script>
        window.JPrime = window.JPrime || {};
        window.JPrime.timezone = @js(config('app.timezone'));
    </script>

    <!-- App Styles & Scripts (Vite) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    @stack('styles')
</head>

<body>
    {{-- Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-dark jprime-navbar fixed-top">
        <div class="container">

            {{-- Logo --}}
            <a class="navbar-brand jprime-logo fw-bold fs-5 text-white text-decoration-none" href="/">
                <img src="{{ asset('logo.png') }}" alt="JPRIME FITNESS Logo" />JPRIME <span
                    class="text-danger">FITNESS</span>
            </a>

            {{-- Mobile Toggle --}}
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            {{-- Nav Links --}}
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->is('/') ? 'active' : '' }}" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="mt-3 mt-lg-0">
                    <a href="#contact" class="btn btn-danger rounded-1 px-4 fw-semibold">Join Now</a>
                </div>
            </div>

        </div>
    </nav>

    {{-- Page Content --}}
    <main id="app">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="jprime-footer bg-dark py-4">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                <div>
                    <span class="jprime-logo fw-bold text-white fs-6">
                        <img src="{{ asset('logo.png') }}" alt="JPRIME FITNESS Logo" />JPRIME <span
                            class="text-danger">FITNESS</span>
                    </span>
                    <div class="text-muted small mt-1">Single-location training hub</div>
                </div>
                <div class="d-flex gap-4">
                    <a href="#" class="text-white-50 text-decoration-none small">
                        <i class="bi bi-facebook me-1"></i>Facebook
                    </a>
                    <a href="#" class="text-white-50 text-decoration-none small">
                        <i class="bi bi-instagram me-1"></i>Instagram
                    </a>
                    <a href="#" class="text-white-50 text-decoration-none small">
                        <i class="bi bi-tiktok me-1"></i>TikTok
                    </a>
                </div>
            </div>
        </div>
    </footer>
    @stack('scripts')
</body>

</html>
