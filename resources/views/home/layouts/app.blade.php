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
                        <a class="nav-link fw-semibold" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#services">Programs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#membership">Membership</a>
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
    @php
        $bp = $businessProfile ?? \App\Models\BusinessProfile::current();
        $footerAddress = collect([$bp->address, $bp->city, $bp->province])->filter()->join(', ');
        $footerHours = ($bp->opening_time && $bp->closing_time)
            ? \Illuminate\Support\Carbon::parse($bp->opening_time)->format('g:i A') . ' – ' . \Illuminate\Support\Carbon::parse($bp->closing_time)->format('g:i A')
            : 'Hours to be announced';
    @endphp
    <footer class="jprime-footer bg-dark text-white pt-5">
        <div class="container">
            <div class="row g-4 pb-4">
                {{-- Brand --}}
                <div class="col-lg-4 col-md-6">
                    <span class="jprime-logo fw-bold text-white fs-5">
                        <img src="{{ asset('logo.png') }}" alt="JPRIME FITNESS Logo" />JPRIME <span class="text-danger">FITNESS</span>
                    </span>
                    <p class="text-white-50 small mt-3 mb-3" style="max-width: 320px;">
                        {{ $bp->name }} is a community-driven gym committed to clean facilities, honest pricing, and real, measurable progress.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="#" class="footer-social" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="footer-social" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="footer-social" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
                        <a href="#" class="footer-social" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>

                {{-- Quick Links --}}
                <div class="col-lg-2 col-md-6 col-6">
                    <h6 class="footer-heading">Explore</h6>
                    <ul class="footer-links">
                        <li><a href="/">Home</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#services">Programs</a></li>
                        <li><a href="#features">Member Experience</a></li>
                        <li><a href="#membership">Membership</a></li>
                    </ul>
                </div>

                {{-- Visit --}}
                <div class="col-lg-3 col-md-6 col-6">
                    <h6 class="footer-heading">Visit Us</h6>
                    <ul class="footer-info">
                        <li>
                            <i class="bi bi-geo-alt-fill text-danger"></i>
                            <span>{{ $footerAddress ?: 'Address coming soon' }}</span>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill text-danger"></i>
                            <span>{{ $footerHours }}</span>
                        </li>
                        @if ($bp->timezone)
                            <li>
                                <i class="bi bi-globe text-danger"></i>
                                <span>{{ $bp->timezone }}</span>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Get In Touch --}}
                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-heading">Get in Touch</h6>
                    <ul class="footer-info">
                        <li>
                            <i class="bi bi-envelope-fill text-danger"></i>
                            <a href="mailto:hello@jprimefitness.ph">hello@jprimefitness.ph</a>
                        </li>
                        <li>
                            <i class="bi bi-chat-dots-fill text-danger"></i>
                            <a href="#contact">Send us a message</a>
                        </li>
                        <li>
                            <i class="bi bi-person-badge-fill text-danger"></i>
                            <a href="#contact">Coaching inquiries</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 py-3">
                <div class="text-white-50 small">
                    &copy; {{ now()->year }} {{ $bp->name }}. All rights reserved.
                </div>
                <div class="text-white-50 small">
                    Built for the community &middot; Powered by clean training and honest pricing
                </div>
            </div>
        </div>
    </footer>
    @stack('scripts')
</body>

</html>
