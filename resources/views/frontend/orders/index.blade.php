@extends('layouts.customer')

@section('title', 'All Orders · Med Alert')
@section('heading', 'All Orders')

@php
    $input = 'w-full rounded-xl border border-line bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/25';

    // Spreadsheet cells: ruled on every side, the way the sheet is ruled.
    $th = 'border border-[#3E9E77] px-3 py-3 text-center text-xs font-bold uppercase tracking-wide';
    $td = 'border border-[#CBDDD3] px-3 py-2.5 text-center';
@endphp

@section('content')

    {{-- Totals for the current filter --}}
    <div class="rise mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[auto_1fr]">
        <div class="rounded-2xl border border-line bg-card p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-muted">Orders</p>
            <p class="mt-1 text-2xl font-bold text-ink">{{ number_format($totalOrders) }}</p>
        </div>

        <div class="rounded-2xl border border-line bg-card p-4 shadow-sm">
            <p class="mb-2.5 text-xs font-medium uppercase tracking-wider text-muted">By status</p>
            <div class="flex flex-wrap gap-2">
                @if ($statusCounts->isEmpty())
                    <p class="text-sm text-muted">No orders match this filter.</p>
                @else
                    @foreach ($statusMeta as $key => $meta)
                        @continue ($statusCounts->get($key, 0) == 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $meta['tone'] }}/10 px-2.5 py-1 text-xs font-medium text-{{ $meta['tone'] }}">
                            {{ $meta['label'] }}
                            <span class="font-semibold">{{ (int) $statusCounts->get($key) }}</span>
                        </span>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('order.list') }}" id="filter-form"
          class="rise mb-4 rounded-2xl border border-line bg-card p-4 shadow-sm sm:p-5" style="--delay: 60ms">

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="sm:col-span-2 xl:col-span-1">
                <label for="q" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Search</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                    </svg>
                    <input type="search" name="q" id="q" value="{{ $filters['q'] }}"
                           placeholder="Name, address or #id" class="{{ $input }} pl-9">
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
                <label for="status" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Status</label>
                <select name="status" id="status" class="{{ $input }}">
                    <option value="all">All statuses</option>
                    @foreach ($statusMeta as $value => $meta)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $meta['label'] }}</option>
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

            <div>
                <label for="sort" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Sort by</label>
                <select name="sort" id="sort" class="{{ $input }}">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="custom-range" class="mt-3 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 {{ $filters['period'] === 'custom' ? 'grid' : 'hidden' }}">
            <div>
                <label for="from" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">From</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="{{ $input }}">
            </div>
            <div>
                <label for="to" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">To</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="{{ $input }}">
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button type="submit"
                    class="rounded-xl bg-gradient-to-r from-brand to-brand2 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition hover:opacity-90">
                Apply filters
            </button>

            @if ($activeFilterCount > 0)
                <a href="{{ route('order.list') }}"
                   class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:border-danger hover:text-danger">
                    Clear ({{ $activeFilterCount }})
                </a>
            @endif

            <div class="ml-auto flex items-center gap-2">
                <label for="per_page" class="text-xs text-muted">Per page</label>
                <select name="per_page" id="per_page"
                        class="rounded-lg border border-line bg-elevated px-2.5 py-1.5 text-sm text-ink focus:border-brand focus:outline-none">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($filters['per_page'] === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- Orders, read the way the team's sheet reads --}}
    <div class="rise overflow-hidden rounded-2xl border border-line bg-card shadow-sm" style="--delay: 120ms">
        @if ($orders->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1140px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-[#5CBF8E] text-[#0A2A1A]">
                            <th class="{{ $th }}">Lead Submission Date</th>
                            <th class="{{ $th }} text-left">Full Name</th>
                            <th class="{{ $th }}">Phone Number</th>
                            <th class="{{ $th }} text-left">Product</th>
                            <th class="{{ $th }}">MMR</th>
                            <th class="{{ $th }}">Opp Value</th>
                            <th class="{{ $th }}">Status</th>
                            <th class="{{ $th }}">Date to be Charged</th>
                            <th class="{{ $th }}">Sale completion</th>
                            <th class="{{ $th }}">Final Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr onclick="window.location='{{ route('order.show', $order) }}'"
                                title="Open order #{{ $order->id }} — {{ $order->address }}"
                                class="cursor-pointer {{ $loop->even ? 'bg-[#EAF6EE]' : 'bg-white' }} transition hover:bg-brand/10">

                                <td class="{{ $td }} whitespace-nowrap">{{ $order->submittedAt()->format('n/j/Y') }}</td>

                                <td class="{{ $td }} text-left font-semibold text-ink">
                                    <span class="flex items-center gap-1.5">
                                        <span class="truncate">{{ $order->full_name }}</span>
                                        @if ($order->hasVoiceNotes())
                                            <svg class="h-3.5 w-3.5 shrink-0 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v3m0-6a4 4 0 01-4-4V6a4 4 0 118 0v5a4 4 0 01-4 4z"/>
                                            </svg>
                                        @endif
                                    </span>
                                </td>

                                <td class="{{ $td }} whitespace-nowrap tabular-nums">{{ $order->phone }}</td>
                                <td class="{{ $td }} whitespace-nowrap text-left">{{ $order->product?->name ?? '—' }}</td>
                                <td class="{{ $td }} whitespace-nowrap tabular-nums">${{ number_format($order->productPrice?->price ?? 0, 2) }}</td>
                                <td class="{{ $td }} whitespace-nowrap font-semibold tabular-nums">${{ number_format($order->total_price, 2) }}</td>

                                {{-- The status fills its cell, so a row is readable at a glance --}}
                                <td class="border border-[#CBDDD3] px-3 py-2.5 text-center text-xs font-bold whitespace-nowrap {{ $order->sheetStatusClasses() }}">
                                    {{ $order->customerStatusLabel() }}
                                </td>

                                {{-- Each date belongs to one status; showing it once that status has
                                     moved on would leave two dates on a row that only has one story. --}}
                                <td class="{{ $td }} whitespace-nowrap">
                                    {{ $order->status === 'post_date' ? $order->post_date?->format('n/j/Y') : '' }}
                                </td>
                                <td class="{{ $td }} whitespace-nowrap">
                                    {{ $order->status === 'sale' ? $order->sale_date?->format('n/j/Y') : '' }}
                                </td>

                                <td class="{{ $td }} whitespace-nowrap">
                                    @if ($order->isFinal())
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-semibold {{ $order->statusClasses() }}">
                                            {{ $order->customerStatusLabel() }}
                                        </span>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t border-line px-4 py-2 text-xs text-muted lg:hidden">Swipe sideways to see every column.</p>
        @else
            <div class="px-6 py-14 text-center">
                <p class="text-sm font-medium text-ink">No orders match these filters.</p>
                @if ($activeFilterCount > 0)
                    <a href="{{ route('order.list') }}" class="mt-2 inline-block text-sm font-medium text-brand hover:underline">Clear filters</a>
                @else
                    <a href="{{ route('order.create') }}" class="mt-2 inline-block text-sm font-medium text-brand hover:underline">Place your first order</a>
                @endif
            </div>
        @endif
    </div>

    @if ($orders->total() > 0)
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted">
                Showing <span class="font-semibold text-ink">{{ $orders->firstItem() }}</span>
                to <span class="font-semibold text-ink">{{ $orders->lastItem() }}</span>
                of <span class="font-semibold text-ink">{{ number_format($orders->total()) }}</span>
                {{ Str::plural('order', $orders->total()) }}
            </p>

            @if ($orders->hasPages())
                <div>{{ $orders->links('vendor.pagination.admin') }}</div>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('filter-form');
        const period = document.getElementById('period');
        const custom = document.getElementById('custom-range');

        period.addEventListener('change', function () {
            const isCustom = period.value === 'custom';
            custom.classList.toggle('hidden', !isCustom);
            custom.classList.toggle('grid', isCustom);

            if (!isCustom) {
                form.submit();
            }
        });

        ['status', 'product_id', 'sort', 'per_page'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () {
                form.submit();
            });
        });
    })();
</script>
@endpush
