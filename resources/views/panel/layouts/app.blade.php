<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Panel') — JPRIME Fitness Panel</title>
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|nunito:400,600,700|oswald:400,700"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    @vite(['resources/sass/panel.scss', 'resources/js/app.js'])
</head>

<body class="panel-body">
    {{-- Sidebar Overlay (mobile backdrop)  --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="panel-wrapper" id="app">
        {{-- Sidebar --}}
        <aside class="panel-sidebar" id="panelSidebar">
            {{-- Logo --}}
            <div class="sidebar-logo">
                <a href="{{ route('panel.dashboard') }}" class="sidebar-logo-inner">
                    <div class="sidebar-logo-icon">
                        <img src="{{ asset('logo.png') }}" alt="JPRIME FITNESS Logo" />
                    </div>
                    <div>
                        <div class="sidebar-logo-text jprime-logo">JPRIME <span class="text-danger">FITNESS</span></div>
                        <div class="sidebar-logo-sub">Panel Platform</div>
                    </div>
                </a>
            </div>

            {{-- Branch Selector --}}
            <branch-selector :branches-data='@json($branches)'></branch-selector>

            {{-- Navigation --}}
            <nav class="sidebar-nav">
                {{-- Overview --}}
                <div class="sidebar-menu-heading">Overview</div>
                <div class="sidebar-nav-item">
                    <a href="{{ route('panel.dashboard') }}" @class(['active' => request()->routeIs('panel.dashboard')])>
                        <i class="bi bi-speedometer2"></i>
                        <span class="sidebar-nav-label">Dashboard</span>
                    </a>
                </div>

                {{-- People --}}
                <div class="sidebar-menu-heading">People</div>
                <div class="sidebar-nav-item">
                    <a href="{{ route('panel.members.index') }}" @class(['active' => request()->routeIs('panel.members.*')])>
                        <i class="bi bi-people-fill"></i>
                        <span class="sidebar-nav-label">Members</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="{{ route('panel.walkins.index') }}" @class(['active' => request()->routeIs('panel.walkins.*')])>
                        <i class="bi bi-person-plus-fill"></i>
                        <span class="sidebar-nav-label">Walk-ins</span>
                    </a>
                </div>

                @can('manage employees')
                    <div class="sidebar-nav-item">
                        <a href="{{ route('panel.employees.index') }}" @class(['active' => request()->routeIs('panel.employees.*')])>
                            <i class="bi bi-person-workspace"></i>
                            <span class="sidebar-nav-label">Employees</span>
                        </a>
                    </div>
                @endcan

                {{-- Access --}}
                <div class="sidebar-menu-heading">Access</div>
                <div class="sidebar-nav-item">
                    <a href="{{ route('panel.attendance.index') }}" @class(['active' => request()->routeIs('panel.attendance.*')])>
                        <i class="bi bi-door-open-fill"></i>
                        <span class="sidebar-nav-label">Check-ins / Attendance</span>
                    </a>
                </div>

                {{-- Sales --}}
                <div class="sidebar-menu-heading">Sales</div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.sales.*')])>
                        <i class="bi bi-cash-coin"></i>
                        <span class="sidebar-nav-label">Sales</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.pricing.*')])>
                        <i class="bi bi-tag-fill"></i>
                        <span class="sidebar-nav-label">Pricing & Rates</span>
                    </a>
                </div>

                {{-- Operations --}}
                <div class="sidebar-menu-heading">Operations</div>
                <div class="sidebar-nav-item">
                    <a href="{{ route('panel.branches.index') }}" @class(['active' => request()->routeIs('panel.branches.*')])>
                        <i class="bi bi-geo-alt-fill"></i>
                        <span class="sidebar-nav-label">Branches</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="{{ route('panel.inventory.index') }}" @class(['active' => request()->routeIs('panel.inventory.*')])>
                        <i class="bi bi-box-seam-fill"></i>
                        <span class="sidebar-nav-label">Inventory</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.programs.*')])>
                        <i class="bi bi-journal-bookmark-fill"></i>
                        <span class="sidebar-nav-label">Programs</span>
                    </a>
                </div>

                {{-- Reports --}}
                <div class="sidebar-menu-heading">Reports</div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.reports.sales')])>
                        <i class="bi bi-bar-chart-fill"></i>
                        <span class="sidebar-nav-label">Sales Reports</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.reports.attendance')])>
                        <i class="bi bi-graph-up-arrow"></i>
                        <span class="sidebar-nav-label">Attendance Reports</span>
                    </a>
                </div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.reports.payroll')])>
                        <i class="bi bi-receipt"></i>
                        <span class="sidebar-nav-label">Payroll Reports</span>
                    </a>
                </div>

                {{-- System --}}
                <div class="sidebar-menu-heading">System</div>
                <div class="sidebar-nav-item">
                    <a href="#" @class(['active' => request()->routeIs('panel.settings')])>
                        <i class="bi bi-gear-fill"></i>
                        <span class="sidebar-nav-label">Settings</span>
                    </a>
                </div>

            </nav>

            {{-- Appearance Toggle --}}
            <div class="sidebar-appearance">
                <label for="darkModeToggle" class="sidebar-appearance-icon-btn" title="Toggle appearance">
                    <i class="bi bi-moon-fill"></i>
                </label>
                <div class="sidebar-appearance-label">
                    <div>Appearance</div>
                    <div>Light / Dark mode</div>
                </div>
                <div class="form-check form-switch mb-0 ms-auto sidebar-appearance-toggle">
                    <input class="form-check-input" type="checkbox" role="switch" id="darkModeToggle"
                        style="cursor: pointer;" />
                </div>
            </div>

        </aside>

        {{-- Main --}}
        <div class="panel-main" id="panelMain">
            {{-- Top Bar --}}
            <header class="panel-topbar">
                <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-title" title="@yield('title', 'Dashboard')">@yield('title', 'Dashboard')</div>

                <div class="topbar-actions">

                    {{-- Branch Selector: hidden by default, shown on desktop when sidebar is collapsed --}}
                    <branch-selector-collapsed
                        :branches-data='@json($branches)'></branch-selector-collapsed>

                    <button class="topbar-icon-btn" title="Notifications">
                        <i class="bi bi-bell-fill"></i>
                        <span class="topbar-badge"></span>
                    </button>

                    <button class="topbar-icon-btn" title="Search">
                        <i class="bi bi-search"></i>
                    </button>

                    <div class="dropdown">
                        <a class="topbar-user" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="topbar-avatar">
                                @php
                                    $name = Auth::user()->name ?? 'Admin';
                                    $words = collect(explode(' ', trim($name)))
                                        ->filter()
                                        ->values();
                                    $initials =
                                        $words->count() === 0
                                            ? 'A'
                                            : ($words->count() === 1
                                                ? strtoupper(substr($words[0], 0, 1))
                                                : strtoupper(substr($words[0], 0, 1) . substr($words->last(), 0, 1)));
                                @endphp
                                {{ $initials }}
                            </div>
                            <div class="topbar-user-info">
                                <div class="topbar-user-name">
                                    {{ $name }}
                                </div>
                                <div class="text-capitalize topbar-user-role">
                                    {{ Str::replace('_', ' ', auth()->user()->role) }}
                                </div>
                            </div>
                            <i class="bi bi-chevron-down ms-1 d-none d-md-inline"
                                style="font-size: 0.65rem; color: #888;"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0"
                            style="min-width: 180px; font-size: 0.85rem;">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a>
                            </li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a>
                            </li>
                            <li>
                                <hr class="dropdown-divider" />
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign out
                                </a>
                            </li>
                        </ul>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </header>

            {{-- Page Content --}}
            <main class="panel-content">
                @yield('content')
            </main>

        </div>
    </div>

    <script type="text/javascript">
        window.Laravel = {
            jsPermissions: {!! auth()->user()->jsPermissions() !!}
        }
    </script>
    @stack('scripts')
</body>

</html>
