@extends('layouts.customer')

@section('title', 'Place Your Order · Med Alert')
@section('heading', 'Place Order')

@php
    $cls = fn (string $f) => 'field w-full rounded-xl border bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted '
        .($errors->has($f) ? 'border-danger' : 'border-line');

    // Prefill the account holder's own details.
    $prefills = [
        'full_name' => auth()->user()->name,
        'email' => auth()->user()->email,
    ];
@endphp

@section('content')

    <div class="rise mb-5">
        <h2 class="text-xl font-bold tracking-tight text-ink sm:text-2xl">Place Your Order</h2>
        <p class="mt-1 text-sm text-muted">Fill in the details below and we will be in touch to confirm.</p>
    </div>




    <div class="rise max-w-3xl rounded-2xl border border-line bg-card p-5 shadow-sm sm:p-7" style="--delay: 90ms">
        @if ($products->isEmpty())
            <div class="py-10 text-center">
                <p class="text-sm font-medium text-ink">No products are available right now.</p>
                <p class="mt-1 text-sm text-muted">Please check back soon.</p>
            </div>
        @elseif ($fields->isEmpty())
            <div class="py-10 text-center">
                <p class="text-sm font-medium text-ink">This form has not been set up yet.</p>
                <p class="mt-1 text-sm text-muted">Please contact your administrator.</p>
            </div>
        @else
            <form method="POST" action="{{ route('order.store') }}" id="order-form"
                  enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($fields as $field)

                        @if ($field->key === 'product')
                            <div class="{{ $field->width === 'full' ? 'sm:col-span-2' : '' }}">
                                <label for="product_id" class="mb-1.5 block text-sm font-medium text-ink">
                                    {{ $field->label }}<span class="text-danger">*</span>
                                </label>
                                <select name="product_id" id="product_id" required class="{{ $cls('product_id') }}">
                                    <option value="">Choose a product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                                @error('product_id')
                                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                        @elseif ($field->key === 'package')
                            <div class="{{ $field->width === 'full' ? 'sm:col-span-2' : '' }}">
                                <label for="product_price_id" class="mb-1.5 block text-sm font-medium text-ink">
                                    {{ $field->label }}<span class="text-danger">*</span>
                                </label>
                                <select name="product_price_id" id="product_price_id" required disabled
                                        class="{{ $cls('product_price_id') }}">
                                    <option value="">Select a product first</option>
                                </select>
                                @error('product_price_id')
                                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                        @elseif ($field->key === 'team')
                            <div class="{{ $field->width === 'full' ? 'sm:col-span-2' : '' }}">
                                <label for="team_id" class="mb-1.5 block text-sm font-medium text-ink">
                                    {{ $field->label }}
                                </label>
                                <select name="team_id" id="team_id" class="{{ $cls('team_id') }}">
                                    <option value="">No team</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>{{ $team->name }}</option>
                                    @endforeach
                                </select>
                                @error('team_id')
                                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                        @elseif ($field->type === 'quantity')
                            <div class="{{ $field->width === 'full' ? 'sm:col-span-2' : '' }}">
                                <label for="quantity" class="mb-1.5 block text-sm font-medium text-ink">
                                    {{ $field->label }}<span class="text-danger">*</span>
                                </label>
                                <div class="flex items-stretch gap-2">
                                    <button type="button" id="qty-minus" aria-label="Decrease quantity"
                                            class="field flex w-11 shrink-0 items-center justify-center rounded-xl border border-line bg-elevated text-muted hover:border-brand hover:text-brand">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 12h14"/></svg>
                                    </button>
                                    <input type="number" name="quantity" id="quantity" value="{{ old('quantity', 1) }}"
                                           min="1" max="1000" required class="{{ $cls('quantity') }} text-center">
                                    <button type="button" id="qty-plus" aria-label="Increase quantity"
                                            class="field flex w-11 shrink-0 items-center justify-center rounded-xl border border-line bg-elevated text-muted hover:border-brand hover:text-brand">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                </div>
                                @error('quantity')
                                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                        @else
                            @include('partials.form-field', [
                                'field' => $field,
                                'prefill' => $prefills[$field->key] ?? '',
                            ])
                        @endif

                    @endforeach

                    {{-- Total always sits at the end. Commission is admin-only. --}}
                    <div class="sm:col-span-2">
                        <label for="total_display" class="mb-1.5 block text-sm font-medium text-ink">Total Price</label>
                        <div class="flex h-[42px] items-center justify-between rounded-xl border border-brand/25 bg-gradient-to-r from-brand/10 to-brand2/10 px-3.5">
                            <span class="text-xs font-medium text-muted">USD</span>
                            <span id="total_display" class="text-lg font-extrabold tracking-tight text-brand">$0.00</span>
                        </div>
                        <p class="mt-1.5 text-xs text-muted">Confirmed when we contact you.</p>
                    </div>
                </div>

                <button type="submit" id="submit-btn"
                        class="cta flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand2 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/30">
                    <span id="submit-label">Submit Order</span>
                    <svg id="submit-arrow" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    <svg id="submit-spinner" class="spin hidden h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>
                        <path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </button>
            </form>
        @endif
    </div>

@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('order-form');

        if (!form) {
            return;
        }

        const productSelect = document.getElementById('product_id');
        const priceSelect = document.getElementById('product_price_id');
        const quantityInput = document.getElementById('quantity');
        const totalDisplay = document.getElementById('total_display');
        const submitButton = document.getElementById('submit-btn');

        const pricesUrl = @json(route('products.prices', ['product' => '__PRODUCT__']));
        const oldPriceId = @json(old('product_price_id'));

        function money(value) {
            return '$' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function flash(el, value) {
            if (!el || value === el.textContent) return;
            el.textContent = value;
            el.classList.remove('flash');
            void el.offsetWidth;
            el.classList.add('flash');
        }

        function updateTotal() {
            if (!priceSelect) return;

            const option = priceSelect.selectedOptions[0];
            const unitPrice = option && option.dataset.price ? parseFloat(option.dataset.price) : 0;
            const qty = parseInt(quantityInput ? quantityInput.value : '0', 10);
            const count = qty > 0 ? qty : 0;

            flash(totalDisplay, money(unitPrice * count));
        }

        function setPlaceholder(text, disabled) {
            priceSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = text;
            priceSelect.appendChild(option);
            priceSelect.disabled = disabled;
        }

        async function loadPrices(productId, preselectId) {
            if (!priceSelect) return;

            if (!productId) {
                setPlaceholder('Select a product first', true);
                updateTotal();
                return;
            }

            setPlaceholder('Loading…', true);

            try {
                const response = await fetch(pricesUrl.replace('__PRODUCT__', encodeURIComponent(productId)), {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) throw new Error('Request failed');

                const prices = await response.json();

                if (!prices.length) {
                    setPlaceholder('No packages available', true);
                    updateTotal();
                    return;
                }

                setPlaceholder('Choose a package', false);

                prices.forEach(function (price) {
                    const option = document.createElement('option');
                    option.value = price.id;
                    option.textContent = price.label + ' — ' + money(price.price);
                    option.dataset.price = price.price;
                    option.selected = String(price.id) === String(preselectId);
                    priceSelect.appendChild(option);
                });
            } catch (error) {
                setPlaceholder('Could not load packages — please try again', true);
            }

            updateTotal();
        }

        if (productSelect) {
            productSelect.addEventListener('change', () => loadPrices(productSelect.value, null));
        }

        if (priceSelect) {
            priceSelect.addEventListener('change', updateTotal);
        }

        if (quantityInput) {
            quantityInput.addEventListener('input', updateTotal);

            const nudge = (delta) => {
                const current = parseInt(quantityInput.value, 10) || 1;
                quantityInput.value = Math.min(1000, Math.max(1, current + delta));
                updateTotal();
            };

            document.getElementById('qty-minus')?.addEventListener('click', () => nudge(-1));
            document.getElementById('qty-plus')?.addEventListener('click', () => nudge(1));
        }

        // Auto-grow any paragraph field.
        document.querySelectorAll('#order-form textarea').forEach(function (area) {
            const grow = () => {
                area.style.height = 'auto';
                area.style.height = Math.min(area.scrollHeight, 140) + 'px';
            };
            area.addEventListener('input', grow);
            grow();
        });

        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            document.getElementById('submit-label').textContent = 'Submitting…';
            document.getElementById('submit-arrow').classList.add('hidden');
            document.getElementById('submit-spinner').classList.remove('hidden');
        });

        if (productSelect) {
            loadPrices(productSelect.value, oldPriceId);
        }
    })();
</script>
@endpush
