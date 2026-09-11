@extends('layouts.admin')

@section('title', 'Dashboard · Med Alert')
@section('heading', 'Dashboard')

@php
    /*
     |----------------------------------------------------------------
     | Area chart geometry — plain SVG, no charting library needed.
     |----------------------------------------------------------------
     */
    $w = 720; $h = 240;
    $padL = 46; $padR = 18; $padT = 18; $padB = 34;
    $cw = $w - $padL - $padR;
    $ch = $h - $padT - $padB;

    $peak = (int) $series->max('orders');
    $niceMax = max(4, (int) (ceil(max(1, $peak) / 4) * 4));
    $count = $series->count();
    $baseline = $padT + $ch;

    $points = [];
    foreach ($series->values() as $i => $row) {
        $points[] = [
            'x' => round($padL + ($count > 1 ? $i * $cw / ($count - 1) : $cw / 2), 2),
            'y' => round($padT + $ch * (1 - $row['orders'] / $niceMax), 2),
            'row' => $row,
        ];
    }

    $linePath = 'M '.implode(' L ', array_map(fn ($p) => $p['x'].' '.$p['y'], $points));
    $areaPath = $linePath.' L '.end($points)['x'].' '.$baseline.' L '.$points[0]['x'].' '.$baseline.' Z';

    // Approximate path length so the draw-in animation lands exactly.
    $pathLength = 0;
    for ($i = 1; $i < count($points); $i++) {
        $pathLength += sqrt(
            ($points[$i]['x'] - $points[$i - 1]['x']) ** 2 +
            ($points[$i]['y'] - $points[$i - 1]['y']) ** 2
        );
    }
    $pathLength = (int) ceil($pathLength) + 10;

    /*
     |----------------------------------------------------------------
     | Donut geometry for the status split.
     |----------------------------------------------------------------
     */
    $radius = 58;
    $circumference = 2 * M_PI * $radius;
    $donutTotal = max(1, $totalOrders);

    // One ring segment per status in the pipeline.
    $segments = $statusCounts->filter(fn ($s) => $s['value'] > 0)->values()->all();

    if (empty($segments)) {
        $segments = [['label' => 'No orders', 'value' => 0, 'token' => 'muted']];
    }

    $rotation = 0;
    foreach ($segments as $i => $segment) {
        $share = $segment['value'] / $donutTotal;
        $segments[$i]['share'] = $share;
        $segments[$i]['dash'] = round($circumference * $share, 2);
        $segments[$i]['gap'] = round($circumference * (1 - $share), 2);
        $segments[$i]['offset'] = round(-$circumference * $rotation, 2);
        $rotation += $share;
    }

    $topRevenue = collect($topProducts)->max('revenue') ?: 1;

    $cards = [
        [
            'label' => 'Total Orders', 'value' => $totalOrders, 'money' => false, 'token' => 'accent',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2',
        ],
        [
            'label' => 'In Progress', 'value' => $openOrders, 'money' => false, 'token' => 'warning',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'label' => 'Completed', 'value' => $completedOrders, 'money' => false, 'token' => 'success',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'label' => 'Cancelled', 'value' => $cancelledOrders, 'money' => false, 'token' => 'danger',
            'icon' => 'M6 18L18 6M6 6l12 12',
        ],
        [
            'label' => 'Chargebacks', 'value' => $chargebackOrders, 'money' => false, 'token' => 'danger',
            'icon' => 'M10 14L21 3m-9 0H3v18h18v-9M15 9l-6 6m0-6l6 6',
        ],
    ];
@endphp

@section('content')

    {{-- Filter bar: every figure below reflects this selection --}}
    @php
        $input = 'w-full rounded-xl border border-line bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30';
    @endphp

    {{-- `.rise` leaves a stacking context behind, so the cards below would
         otherwise paint over the open account dropdown. --}}
    <form method="GET" action="{{ route('admin.dashboard') }}" id="dash-filter"
          class="rise relative z-20 mb-4 rounded-2xl border border-line bg-card p-4 sm:p-5">

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">

            <div class="sm:col-span-2 xl:col-span-1">
                <label for="q" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Search</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                    </svg>
                    <input type="search" name="q" id="q" value="{{ $filters['q'] }}"
                           placeholder="Customer, account, phone or #id"
                           class="{{ $input }} pl-9">
                </div>
            </div>

            <div>
                <label for="period" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Date range</label>
                <select name="period" id="period" class="{{ $input }}">
                    @foreach ($periods as $value => $label)
                        <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="product_id" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Product</label>
                <select name="product_id" id="product_id" class="{{ $input }}">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($filters['product_id'] === $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Several accounts can be compared at once --}}
            <div class="relative" id="user-picker">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Submitted by</label>

                <button type="button" id="user-picker-toggle"
                        class="{{ $input }} flex items-center justify-between gap-2 text-left"
                        aria-haspopup="true" aria-expanded="false">
                    <span id="user-picker-label" class="truncate">
                        @if (count($filters['user_ids']) === 0)
                            All users
                        @elseif (count($filters['user_ids']) === 1)
                            {{ $customers->firstWhere('id', $filters['user_ids'][0])?->name ?? '1 user' }}
                        @else
                            {{ count($filters['user_ids']) }} users selected
                        @endif
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div id="user-picker-panel"
                     class="absolute left-0 right-0 z-40 mt-1 hidden overflow-hidden rounded-xl border border-line bg-card shadow-2xl">
                    <div class="border-b border-line p-2">
                        <input type="search" id="user-picker-search" placeholder="Search accounts"
                               class="w-full rounded-lg border border-line bg-elevated px-3 py-2 text-sm text-ink placeholder-muted focus:border-accent focus:outline-none">
                    </div>

                    <div class="max-h-56 overflow-y-auto overscroll-contain p-1">
                        @forelse ($customers as $customer)
                            <label class="user-option flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition hover:bg-elevated"
                                   data-name="{{ Str::lower($customer->name.' '.$customer->email) }}">
                                <input type="checkbox" name="user_ids[]" value="{{ $customer->id }}"
                                       @checked(in_array($customer->id, $filters['user_ids'], true))
                                       class="user-checkbox h-4 w-4 rounded border-line text-accent focus:ring-accent/30">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-ink">{{ $customer->name }}</span>
                                    <span class="block truncate text-[11px] text-muted">{{ $customer->email }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="px-2.5 py-4 text-center text-xs text-muted">No customer accounts yet.</p>
                        @endforelse

                        <p id="user-picker-empty" class="hidden px-2.5 py-4 text-center text-xs text-muted">No accounts match.</p>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-line bg-elevated px-2.5 py-2">
                        <button type="button" id="user-picker-clear"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-muted transition hover:text-danger">
                            Clear
                        </button>
                        <button type="button" id="user-picker-apply"
                                class="rounded-lg bg-gradient-to-r from-accent to-accent2 px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90">
                            Apply
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label for="status" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Status</label>
                <select name="status" id="status" class="{{ $input }}">
                    <option value="all">All statuses</option>
                    @foreach ($statusMeta as $value => $meta)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div id="dash-custom" class="grid grid-cols-2 gap-3 sm:col-span-2 {{ $filters['period'] === 'custom' ? '' : 'hidden' }}">
                <div>
                    <label for="from" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">From</label>
                    <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="to" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">To</label>
                    <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="{{ $input }}">
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button type="submit"
                    class="rounded-xl bg-gradient-to-r from-accent to-accent2 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent/25 transition hover:opacity-90">
                Apply filters
            </button>

            @if ($activeFilterCount > 0)
                <a href="{{ route('admin.dashboard') }}"
                   class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:border-danger hover:text-danger">
                    Clear ({{ $activeFilterCount }})
                </a>
            @endif

            <a href="{{ route('admin.orders.index', request()->query()) }}"
               class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:border-accent hover:text-accent">
                See these orders
            </a>

            <span class="ml-auto inline-flex items-center gap-2 rounded-lg bg-elevated px-3 py-1.5 text-xs font-medium text-muted">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Showing: {{ $rangeLabel }}
            </span>
        </div>
    </form>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($cards as $i => $card)
            <div class="rise lift rounded-2xl border border-line bg-card p-5" style="--delay: {{ $i * 70 }}ms">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-muted">{{ $card['label'] }}</p>
                        <p class="mt-2.5 text-3xl font-bold tracking-tight text-ink"
                           data-countup="{{ $card['value'] }}">0</p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-{{ $card['token'] }}/10 text-{{ $card['token'] }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                        </svg>
                    </span>
                </div>

                <div class="mt-4 h-1 w-full overflow-hidden rounded-full bg-{{ $card['token'] }}/10">
                    <div class="grow h-full rounded-full bg-{{ $card['token'] }}"
                         style="width: {{ $totalOrders > 0 ? round($card['value'] / max(1, $totalOrders) * 100) : 0 }}%; --delay: {{ 300 + $i * 70 }}ms"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Revenue strip --}}
    <div class="rise mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style="--delay: 280ms">
        @php
            $money = [
                ['label' => 'User Commission', 'value' => $userCommission, 'token' => 'success'],
                ['label' => 'Admin Commission', 'value' => $adminCommission, 'token' => 'info'],
                ['label' => 'Total Commission', 'value' => $totalCommission, 'token' => 'warning'],
                ['label' => 'Avg. Sale Commission', 'value' => $averageSaleCommission, 'token' => 'accent2'],
            ];
        @endphp
        @foreach ($money as $item)
            <div class="lift rounded-2xl border border-line bg-card p-5">
                <p class="text-xs font-medium uppercase tracking-wider text-muted">{{ $item['label'] }}</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-{{ $item['token'] }}"
                   data-countup="{{ round($item['value'], 2) }}" data-prefix="$" data-decimals="2">$0.00</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">

        {{-- Activity chart --}}
        <div class="rise rounded-2xl border border-line bg-card p-5 xl:col-span-2" style="--delay: 340ms">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Order Activity</h2>
                    <p class="mt-0.5 text-xs text-muted">{{ $rangeLabel }}</p>
                </div>

                <div class="flex items-center gap-2">
                    @if ($trend >= 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8m0 0h-5m5 0v5"/>
                            </svg>
                            {{ number_format(abs($trend), 1) }}%
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l6 6 4-4 8 8m0 0v-5m0 5h-5"/>
                            </svg>
                            {{ number_format(abs($trend), 1) }}%
                        </span>
                    @endif
                    <span class="text-xs text-muted">vs. previous period</span>
                </div>
            </div>

            <div class="relative">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" role="img" aria-label="Orders per day">
                    <defs>
                        <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgb(var(--accent))" stop-opacity=".28"/>
                            <stop offset="100%" stop-color="rgb(var(--accent))" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="lineStroke" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="rgb(var(--accent))"/>
                            <stop offset="100%" stop-color="rgb(var(--accent2))"/>
                        </linearGradient>
                    </defs>

                    {{-- Grid + y axis --}}
                    @for ($i = 0; $i <= 4; $i++)
                        @php
                            $gy = round($padT + $ch * $i / 4, 2);
                            $gv = (int) round($niceMax * (1 - $i / 4));
                        @endphp
                        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $w - $padR }}" y2="{{ $gy }}"
                              stroke="rgb(var(--line))" stroke-width="1" stroke-dasharray="{{ $i === 4 ? '0' : '4 6' }}"/>
                        <text x="{{ $padL - 10 }}" y="{{ $gy + 4 }}" text-anchor="end"
                              font-size="11" fill="rgb(var(--muted))">{{ $gv }}</text>
                    @endfor

                    {{-- Area + line --}}
                    <path d="{{ $areaPath }}" fill="url(#areaFill)" class="fade-in" style="--delay: 500ms"/>
                    <path d="{{ $linePath }}" fill="none" stroke="url(#lineStroke)" stroke-width="2.5"
                          stroke-linecap="round" stroke-linejoin="round"
                          class="draw" style="--len: {{ $pathLength }}; --delay: 250ms"/>

                    {{-- Points + x axis --}}
                    @foreach ($points as $i => $point)
                        <g class="fade-in" style="--delay: {{ 700 + $i * 30 }}ms">
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5"
                                    fill="rgb(var(--card))" stroke="rgb(var(--accent))" stroke-width="2"/>
                            <title>{{ $point['row']['label'] }} — {{ $point['row']['orders'] }} orders, ${{ number_format($point['row']['revenue'], 2) }}</title>
                        </g>

                        @if ($i % 2 === 0 || $i === $count - 1)
                            <text x="{{ $point['x'] }}" y="{{ $h - 10 }}" text-anchor="middle"
                                  font-size="11" fill="rgb(var(--muted))">{{ $point['row']['short'] }}</text>
                        @endif
                    @endforeach
                </svg>

                @if ($peak === 0)
                    <div class="absolute inset-0 flex items-center justify-center">
                        <p class="rounded-lg bg-card/80 px-3 py-1.5 text-xs text-muted backdrop-blur">No orders in this period yet</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Status donut --}}
        <div class="rise rounded-2xl border border-line bg-card p-5" style="--delay: 400ms">
            <h2 class="text-sm font-semibold text-ink">Status Split</h2>
            <p class="mt-0.5 text-xs text-muted">All time, by pipeline stage</p>

            <div class="my-5 flex justify-center">
                <div class="relative">
                    <svg width="160" height="160" viewBox="0 0 160 160" role="img" aria-label="Order status breakdown">
                        <circle cx="80" cy="80" r="{{ $radius }}" fill="none"
                                stroke="rgb(var(--line))" stroke-width="16"/>

                        @foreach ($segments as $i => $segment)
                            @if ($segment['value'] > 0)
                                <circle cx="80" cy="80" r="{{ $radius }}" fill="none"
                                        stroke="rgb(var(--{{ $segment['token'] }}))" stroke-width="16"
                                        stroke-linecap="butt"
                                        stroke-dasharray="{{ $segment['dash'] }} {{ $segment['gap'] }}"
                                        stroke-dashoffset="{{ $segment['offset'] }}"
                                        transform="rotate(-90 80 80)"
                                        class="fade-in" style="--delay: {{ 500 + $i * 150 }}ms">
                                    <title>{{ $segment['label'] }}: {{ $segment['value'] }}</title>
                                </circle>
                            @endif
                        @endforeach
                    </svg>

                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold text-ink" data-countup="{{ $totalOrders }}">0</span>
                        <span class="text-[11px] uppercase tracking-wider text-muted">Orders</span>
                    </div>
                </div>
            </div>

            <div class="space-y-2.5">
                @foreach ($segments as $segment)
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-{{ $segment['token'] }}"></span>
                            <span class="text-muted">{{ $segment['label'] }}</span>
                        </span>
                        <span class="font-semibold text-ink">
                            {{ $segment['value'] }}
                            <span class="ml-1 text-xs font-normal text-muted">{{ number_format($segment['share'] * 100, 0) }}%</span>
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 rounded-xl border border-line bg-elevated p-3.5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted">Completed rate</span>
                    <span class="text-sm font-bold text-success">{{ number_format($conversionRate, 1) }}%</span>
                </div>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-success/10">
                    <div class="grow h-full rounded-full bg-gradient-to-r from-success to-accent2"
                         style="width: {{ round($conversionRate) }}%; --delay: 800ms"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">

        {{-- Top products --}}
        <div class="rise rounded-2xl border border-line bg-card p-5" style="--delay: 460ms">
            <h2 class="text-sm font-semibold text-ink">Top Products</h2>
            <p class="mt-0.5 text-xs text-muted">By revenue</p>

            <div class="mt-5 space-y-4">
                @forelse ($topProducts as $i => $product)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-medium text-ink">{{ $product['name'] }}</span>
                            <span class="shrink-0 font-semibold text-muted">${{ number_format($product['revenue'], 2) }}</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-elevated">
                            <div class="grow h-full rounded-full bg-gradient-to-r from-accent to-accent2"
                                 style="width: {{ round($product['revenue'] / $topRevenue * 100) }}%; --delay: {{ 600 + $i * 90 }}ms"></div>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ $product['orders'] }} {{ Str::plural('order', $product['orders']) }}</p>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-muted">No sales data yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Recent orders --}}
        <div class="rise overflow-hidden rounded-2xl border border-line bg-card xl:col-span-2" style="--delay: 520ms">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="text-sm font-semibold text-ink">Recent Orders</h2>
                <a href="{{ route('admin.orders.index') }}"
                   class="group inline-flex items-center gap-1 text-sm font-medium text-accent transition hover:gap-2">
                    View all
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-elevated text-xs uppercase tracking-wider text-muted">
                        <tr>
                            <th class="px-5 py-3 font-medium">ID</th>
                            <th class="px-5 py-3 font-medium">Customer</th>
                            <th class="px-5 py-3 font-medium">Product</th>
                            <th class="px-5 py-3 font-medium">Total</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($recentOrders as $order)
                            <tr class="row-hover cursor-pointer hover:bg-elevated"
                                onclick="window.location='{{ route('admin.orders.show', $order) }}'">
                                <td class="px-5 py-3.5 font-semibold text-accent">#{{ $order->id }}</td>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-ink">{{ $order->full_name }}</p>
                                    <p class="text-xs text-muted">
                                        by {{ $order->user?->name ?? 'account removed' }}
                                    </p>
                                </td>
                                <td class="px-5 py-3.5 text-muted">
                                    {{ $order->product?->name ?? '—' }}
                                    <span class="text-xs">({{ $order->productPrice?->label ?? '—' }})</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 font-semibold text-ink">${{ number_format($order->total_price, 2) }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $order->statusClasses() }}">
                                        {{ $order->statusLabel() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <span class="block text-ink">{{ $order->submittedAt()->format('M j, Y') }}</span>
                                    <span class="block text-xs text-muted">{{ $order->submittedAt()->format('g:i A') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-muted">No orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@include('partials.user-picker-script')
<script>
    (function () {
        const form = document.getElementById('dash-filter');
        const period = document.getElementById('period');
        const custom = document.getElementById('dash-custom');

        period.addEventListener('change', function () {
            const isCustom = period.value === 'custom';
            custom.classList.toggle('hidden', !isCustom);

            if (!isCustom) {
                form.submit();
            }
        });

        ['product_id', 'status'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () {
                form.submit();
            });
        });
    })();

    (function () {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        document.querySelectorAll('[data-countup]').forEach(function (el) {
            const target = parseFloat(el.dataset.countup) || 0;
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const prefix = el.dataset.prefix || '';

            function render(value) {
                el.textContent = prefix + value.toLocaleString(undefined, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                });
            }

            if (reduced || target === 0) {
                render(target);
                return;
            }

            const duration = 1000;
            let started = null;

            function step(now) {
                if (started === null) {
                    started = now;
                }

                const progress = Math.min((now - started) / duration, 1);
                // easeOutExpo keeps the last digits from crawling
                const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);

                render(target * eased);

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            }

            requestAnimationFrame(step);
        });
    })();
</script>
@endpush
