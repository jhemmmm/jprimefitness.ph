<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'Sign In') - JPrime Fitness Admin</title>

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|oswald:400,500,600,700"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ffffff">
    <meta name="msapplication-TileColor" content="#000000">
    <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">
    @vite(['resources/sass/panel.scss'])
</head>

<body class="auth-body">

    <div class="auth-layout">

        {{-- Left Panel: Branding --}}
        <div class="auth-brand d-none d-lg-flex">
            <img class="band-bg" src="/images/gym/sign.webp" alt="">
            <div class="auth-brand-inner">
                <div class="auth-brand-logo">
                    <a href="/" class="text-white text-decoration-none">
                        <div class="jprime-logo fw-bold fs-3 text-white">
                            <img src="{{ asset('logo.png') }}" alt="JPrime Fitness Logo" />JPrime <span
                                class="text-danger">Fitness</span>
                        </div>
                        <div class="auth-brand-sub mt-1">Admin Platform</div>
                    </a>
                </div>

                <h2 class="auth-brand-headline">
                    Run the gym.<br />Track every member.
                </h2>
                <p class="auth-brand-desc">
                    One unified dashboard for memberships, check-ins, PT sessions,
                    payroll, revenue, and everything in between.
                </p>

                <div class="auth-brand-pills">
                    <div class="auth-brand-pill"><i class="bi bi-geo-alt-fill"></i> Gym operations</div>
                    <div class="auth-brand-pill"><i class="bi bi-people-fill"></i> Member tracking</div>
                    <div class="auth-brand-pill"><i class="bi bi-bar-chart-fill"></i> Live analytics</div>
                </div>
            </div>
        </div>

        {{-- Right Panel: Form --}}
        <div class="auth-form-panel">
            <div class="auth-form-inner">

                {{-- Mobile logo --}}
                <div class="d-lg-none mb-4">
                    <a href="/" class="text-decoration-none">
                        <div class="jprime-logo auth-logo-card fw-bold fs-5" style="color:#111;"><img
                                src="{{ asset('logo.png') }}" alt="JPrime Fitness Logo" />JPrime <span
                                class="text-danger">Fitness</span></div>
                        <div class="auth-logo-card-sub ">
                            Admin Platform</div>
                    </a>
                </div>

                @yield('form')

            </div>
        </div>

    </div>
    @stack('scripts')

</body>

</html>
