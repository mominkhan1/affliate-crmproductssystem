@php
    // The login screen reuses this layout without the sidebar chrome.
    $bare = $bare ?? false;

    $navigation = [
        [
            'route' => 'admin.dashboard',
            'label' => 'Dashboard',
            'active' => 'admin.dashboard',
            'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
        ],
        [
            'route' => 'admin.products.index',
            'label' => 'Products',
            'active' => 'admin.products.*',
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
        [
            'route' => 'admin.teams.index',
            'label' => 'Teams',
            'active' => 'admin.teams.*',
            'icon' => 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
        ],
        [
            'route' => 'admin.orders.index',
            'label' => 'Orders',
            'active' => 'admin.orders.index|admin.orders.show|admin.orders.edit',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        [
            'route' => 'admin.orders.trash',
            'label' => 'Trash',
            'active' => 'admin.orders.trash',
            'icon' => 'M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M9 7V4h6v3M4 7h16',
            'badge' => \App\Models\Order::onlyTrashed()->count(),
        ],
        [
            'route' => 'admin.form-builder',
            'label' => 'Form Builder',
            'active' => 'admin.form-builder*',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'route' => 'admin.users.index',
            'label' => 'Users',
            'active' => 'admin.users.*',
            'icon' => 'M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.3-.36-1.86m0 0a5 5 0 00-9.28 0M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.3.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.admin-head')
</head>
<body class="min-h-screen bg-surface font-sans text-ink antialiased">

@if ($bare)
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        @yield('content')
    </main>
@else
    <div class="flex min-h-screen">

        {{-- Sidebar --}}
        <aside id="sidebar" data-print-hide
               class="fixed inset-y-0 left-0 z-40 hidden w-64 shrink-0 flex-col bg-sidebar lg:flex">
            <div class="flex h-16 items-center gap-2.5 border-b border-white/10 px-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-accent2 text-sm font-bold text-white shadow-lg shadow-accent/25">M</span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">Med Alert</p>
                    <p class="text-[11px] text-slate-400">Admin Panel</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                @foreach ($navigation as $item)
                    @php $isActive = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                              {{ $isActive
                                  ? 'is-active bg-white/10 text-white'
                                  : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-[18px] w-[18px] {{ $isActive ? 'text-accent2' : '' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (! empty($item['badge']))
                            <span class="rounded-full bg-gradient-to-r from-accent to-accent2 px-2 py-0.5 text-[11px] font-semibold text-white shadow-sm shadow-accent/30">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-white/10 p-3">
                <div class="mb-2 flex items-center gap-2.5 rounded-xl px-3 py-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
                        {{ Str::upper(Str::substr(auth()->user()?->name ?? 'A', 0, 1)) }}
                    </span>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-xs font-medium text-white">{{ auth()->user()?->name }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ auth()->user()?->email }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400 transition hover:bg-danger/15 hover:text-danger">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        {{-- Backdrop for the mobile sidebar --}}
        <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/60 backdrop-blur-sm lg:hidden"></div>

        {{-- Content --}}
        <div class="flex min-h-screen w-full flex-col lg:pl-64">
            <header data-print-hide class="sticky top-0 z-[25] flex h-16 items-center gap-4 border-b border-line bg-card/80 px-4 backdrop-blur-xl sm:px-6">
                <button type="button" id="sidebar-toggle"
                        class="rounded-lg p-2 text-muted transition hover:bg-elevated hover:text-ink lg:hidden"
                        aria-label="Toggle navigation">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold text-ink sm:text-lg">@yield('heading', 'Dashboard')</h1>
                </div>

                {{-- Theme toggle --}}
                <button type="button" id="theme-toggle"
                        class="group relative flex h-9 w-9 items-center justify-center rounded-xl border border-line bg-elevated text-muted transition hover:border-accent/40 hover:text-accent"
                        aria-label="Toggle dark mode" title="Toggle dark mode">
                    <svg id="icon-sun" class="absolute h-[18px] w-[18px] transition-all duration-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"/>
                        <path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                    <svg id="icon-moon" class="absolute h-[18px] w-[18px] transition-all duration-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                    </svg>
                </button>

            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div data-print-hide class="rise mb-6 flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 px-4 py-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-medium text-success">{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="rise mb-6 flex items-start gap-3 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-danger" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3L13.71 4a2 2 0 00-3.42 0L3.36 16a2 2 0 001.71 3z"/>
                        </svg>
                        <span class="text-sm font-medium text-danger">{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const toggle = document.getElementById('sidebar-toggle');

            function close() {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('flex');
                backdrop.classList.add('hidden');
            }

            toggle.addEventListener('click', function () {
                const isHidden = sidebar.classList.contains('hidden');
                sidebar.classList.toggle('hidden', !isHidden);
                sidebar.classList.toggle('flex', isHidden);
                backdrop.classList.toggle('hidden', !isHidden);
            });

            backdrop.addEventListener('click', close);
        })();
    </script>
@endif

{{-- Theme toggle works on the login screen too, so it sits outside the sidebar block. --}}
<script>
    (function () {
        const root = document.documentElement;
        const button = document.getElementById('theme-toggle');
        const sun = document.getElementById('icon-sun');
        const moon = document.getElementById('icon-moon');

        function paintIcons() {
            const dark = root.classList.contains('dark');
            // Show the icon for the theme you would switch *to*.
            sun.style.opacity = dark ? '1' : '0';
            sun.style.transform = dark ? 'rotate(0) scale(1)' : 'rotate(-90deg) scale(.5)';
            moon.style.opacity = dark ? '0' : '1';
            moon.style.transform = dark ? 'rotate(90deg) scale(.5)' : 'rotate(0) scale(1)';
        }

        if (button) {
            paintIcons();

            button.addEventListener('click', function () {
                root.classList.toggle('dark');
                localStorage.setItem('admin-theme', root.classList.contains('dark') ? 'dark' : 'light');
                paintIcons();
            });
        }
    })();
</script>

@include('partials.modal')

@stack('scripts')
</body>
</html>
