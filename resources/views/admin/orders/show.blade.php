@extends('layouts.admin')

@section('title', "Order #{$order->id} · Med Alert")
@section('heading', "Order #{$order->id}")

@section('content')
    <div class="rise mb-5">
        <a href="{{ route('admin.orders.index') }}"
           class="group inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-accent">
            <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to orders
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Customer + order detail --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="rise rounded-2xl border border-line bg-card p-5 sm:p-6" style="--delay: 60ms">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink">Customer</h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $order->statusClasses() }}">
                        {{ $order->statusLabel() }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Full Name</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">{{ $order->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Email</dt>
                        <dd class="mt-1 text-sm font-medium">
                            <a href="mailto:{{ $order->email }}" class="text-accent hover:underline">{{ $order->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Phone</dt>
                        <dd class="mt-1 text-sm font-medium">
                            <a href="tel:{{ $order->phone }}" class="text-accent hover:underline">{{ $order->phone }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Submitted</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">
                            {{ $order->submittedAtLabel() }}
                            <span class="block text-xs font-normal text-muted">{{ $order->submittedAt()->diffForHumans() }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Status Changed</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">
                            @if ($order->statusChangedAtLabel())
                                {{ $order->statusChangedAtLabel() }}
                                <span class="block text-xs font-normal text-muted">
                                    {{ $order->timeToStatus() }} after it was submitted
                                </span>
                            @else
                                <span class="text-muted">Not changed yet</span>
                            @endif
                        </dd>
                    </div>
                    @foreach ($order->allStatusDates() as $dateLabel => $dateValue)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-muted">{{ $dateLabel }}</dt>
                            <dd class="mt-1 text-sm font-medium text-ink">{{ $dateValue }}</dd>
                        </div>
                    @endforeach
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Account</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">
                            @if ($order->user)
                                {{ $order->user->name }}
                                <span class="text-xs text-muted">({{ $order->user->email }})</span>
                            @else
                                <span class="text-muted">Guest / account removed</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wider text-muted">Address</dt>
                        <dd class="mt-1 whitespace-pre-line rounded-xl border border-line bg-elevated p-3 text-sm font-medium text-ink">{{ $order->address }}</dd>
                    </div>
                </dl>
            </div>

            @if (! empty($order->form_data))
                <div class="rise rounded-2xl border border-line bg-card p-5 sm:p-6" style="--delay: 90ms">
                    <h2 class="mb-5 text-sm font-semibold text-ink">Form Answers</h2>

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        @foreach ($order->form_data as $key => $value)
                            @php $field = $customFields[$key] ?? null; @endphp
                            <div class="{{ $field && $field->width === 'full' ? 'sm:col-span-2' : '' }}">
                                <dt class="text-xs uppercase tracking-wider text-muted">{{ $field?->label ?? Str::headline($key) }}</dt>
                                <dd class="mt-1 text-sm font-medium text-ink">
                                    @if ($value === null || $value === '')
                                        <span class="text-muted">Not answered</span>
                                    @elseif ($field?->type === 'file')
                                        <a href="{{ Storage::disk('public')->url($value) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1.5 text-accent hover:underline">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                            View upload
                                        </a>
                                    @elseif ($field?->type === 'checkbox')
                                        {{ $value ? 'Yes' : 'No' }}
                                    @else
                                        <span class="whitespace-pre-line">{{ $value }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if ($order->invoice)
                <div class="rise rounded-2xl border border-line bg-card p-5 sm:p-6" style="--delay: 90ms">
                    <div class="mb-3 flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Invoice {{ $order->invoice->number }}</h2>
                            <p class="text-xs text-muted">Sent {{ $order->invoice->sentAtLabel() }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-elevated px-4 py-3">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->invoice->statusClasses() }}">
                            {{ $order->invoice->statusLabel() }}
                        </span>
                        <span class="text-xl font-extrabold tracking-tight text-ink">
                            ${{ number_format($order->invoice->amount, 2) }}
                        </span>
                    </div>

                    @if ($order->invoice->note)
                        <p class="mt-3 text-xs text-muted">
                            <span class="font-semibold text-ink">Their note:</span> {{ $order->invoice->note }}
                        </p>
                    @endif

                    <a href="{{ route('admin.users.show', $order->user_id) }}#invoice-{{ $order->invoice->id }}"
                       class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-accent transition hover:underline">
                        Change the status on their profile
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            @endif

            @if ($order->voiceNotes->isNotEmpty())
                <div class="rise rounded-2xl border border-line bg-card p-5 sm:p-6" style="--delay: 100ms">
                    <div class="mb-3 flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v3m0-6a4 4 0 01-4-4V6a4 4 0 118 0v5a4 4 0 01-4 4z"/>
                            </svg>
                        </span>
                        <h2 class="text-sm font-semibold text-ink">Customer Voice Notes</h2>
                    </div>

                    <div class="space-y-4">
                        @foreach ($order->voiceNotes as $voiceNote)
                            <div @if (! $loop->first) class="border-t border-line pt-4" @endif>
                                <p class="mb-2 text-xs text-muted">
                                    {{ $voiceNote->name }} &middot;
                                    added {{ $voiceNote->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                                </p>

                                <audio controls preload="metadata" class="w-full">
                                    <source src="{{ $voiceNote->url() }}">
                                </audio>

                                <a href="{{ $voiceNote->url() }}" download
                                   class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:border-accent hover:text-accent">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-9-4l4 4m0 0l4-4m-4 4V4"/>
                                    </svg>
                                    Download
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rise overflow-hidden rounded-2xl border border-line bg-card" style="--delay: 120ms">
                <h2 class="border-b border-line px-5 py-4 text-sm font-semibold text-ink sm:px-6">Order</h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-elevated text-xs uppercase tracking-wider text-muted">
                            <tr>
                                <th class="px-5 py-3 font-medium sm:px-6">Product</th>
                                <th class="px-5 py-3 font-medium">Option</th>
                                <th class="px-5 py-3 font-medium">Unit Price</th>
                                <th class="px-5 py-3 font-medium">Qty</th>
                                <th class="px-5 py-3 font-medium">User Commission</th>
                                <th class="px-5 py-3 font-medium">Admin Commission</th>
                                <th class="px-5 py-3 text-right font-medium sm:px-6">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="px-5 py-4 font-medium text-ink sm:px-6">{{ $order->product?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-muted">{{ $order->productPrice?->label ?? '—' }}</td>
                                <td class="px-5 py-4 text-muted">
                                    {{ $order->productPrice ? '$'.number_format($order->productPrice->price, 2) : '—' }}
                                </td>
                                <td class="px-5 py-4 text-muted">{{ $order->quantity }}</td>
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-success">
                                    ${{ number_format($order->user_commission_total, 2) }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-info">
                                    ${{ number_format($order->admin_commission_total, 2) }}
                                </td>
                                <td class="px-5 py-4 text-right text-lg font-bold text-accent sm:px-6">
                                    ${{ number_format($order->total_price, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Status + notes --}}
        <div class="lg:col-span-1">
            <div class="rise rounded-2xl border border-line bg-card p-5 sm:p-6" style="--delay: 180ms">
                <h2 class="mb-5 text-sm font-semibold text-ink">Manage Order</h2>

                <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="status" class="mb-1.5 block text-sm font-medium text-ink">Status</label>
                        <select name="status" id="status"
                                class="w-full rounded-xl border bg-elevated px-3.5 py-2.5 text-sm text-ink transition focus:outline-none focus:ring-2 focus:ring-accent/30
                                       {{ $errors->has('status') ? 'border-danger' : 'border-line focus:border-accent' }}">
                            @foreach (App\Models\Order::STATUS_META as $value => $meta)
                                <option value="{{ $value }}" @selected(old('status', $order->status) === $value)>
                                    {{ $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Each of these statuses asks for its own date --}}
                    @foreach (App\Models\Order::STATUS_DATES as $statusKey => $meta)
                        @php $column = $meta['column']; @endphp
                        <div class="status-date-field {{ old('status', $order->status) === $statusKey ? '' : 'hidden' }}"
                             data-status="{{ $statusKey }}">
                            <label for="{{ $column }}" class="mb-1.5 block text-sm font-medium text-ink">
                                {{ $meta['label'] }}
                                <span class="font-normal text-muted">&mdash; {{ $meta['help'] }}</span>
                            </label>
                            <input type="date" name="{{ $column }}" id="{{ $column }}"
                                   value="{{ old($column, $order->{$column}?->format('Y-m-d')) }}"
                                   class="w-full rounded-xl border bg-elevated px-3.5 py-2.5 text-sm text-ink transition focus:outline-none focus:ring-2 focus:ring-accent/30
                                          {{ $errors->has($column) ? 'border-danger' : 'border-line focus:border-accent' }}">
                            @error($column)
                                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="notes" class="mb-1.5 block text-sm font-medium text-ink">Notes</label>
                        <textarea name="notes" id="notes" rows="7"
                                  placeholder="Visible to the customer on their order page"
                                  class="w-full rounded-xl border bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted transition focus:outline-none focus:ring-2 focus:ring-accent/30
                                         {{ $errors->has('notes') ? 'border-danger' : 'border-line focus:border-accent' }}">{{ old('notes', $order->notes) }}</textarea>
                        @error('notes')
                            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-accent to-accent2 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent/25 transition hover:opacity-90 hover:shadow-xl hover:shadow-accent/30">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const status = document.getElementById('status');
        const fields = Array.from(document.querySelectorAll('.status-date-field'));

        if (!status || fields.length === 0) {
            return;
        }

        function sync() {
            fields.forEach(function (wrapper) {
                const matches = wrapper.dataset.status === status.value;
                wrapper.classList.toggle('hidden', !matches);

                if (matches) {
                    wrapper.querySelector('input').focus();
                }
            });
        }

        status.addEventListener('change', sync);
    })();
</script>
@endpush
