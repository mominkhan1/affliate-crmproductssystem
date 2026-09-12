@extends('layouts.admin')

@section('title', 'Form Preview · Med Alert')
@section('heading', 'Form Preview')

@section('content')
    <div class="rise mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-start gap-3 rounded-2xl border border-info/30 bg-info/10 px-4 py-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-info" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-ink">
                This is exactly what a signed in customer sees. It cannot be submitted from here.
            </p>
        </div>

        <a href="{{ route('admin.form-builder') }}"
           class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:border-accent hover:text-accent">
            Back to builder
        </a>
    </div>

    <div class="rise mx-auto max-w-3xl" style="--delay: 60ms">
        <div class="rounded-2xl border border-line bg-card p-5 sm:p-7">

            <div class="mb-6 flex items-center justify-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-accent to-accent2 text-lg font-bold text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </span>
                <div class="text-left">
                    <h1 class="text-2xl font-extrabold tracking-tight text-ink">Med Alert</h1>
                    <p class="text-xs text-muted">Place Your Order Below</p>
                </div>
            </div>

            @if ($fields->isEmpty())
                <p class="py-10 text-center text-sm text-muted">No fields yet. Add some in the builder.</p>
            @else
                {{-- Inputs are disabled so nothing can be submitted from the preview. --}}
                <fieldset disabled class="grid grid-cols-1 gap-4 opacity-95 sm:grid-cols-2">
                    @foreach ($fields as $field)
                        @php
                            $span = $field->width === 'full' ? 'sm:col-span-2' : '';
                            $input = 'w-full rounded-xl border border-line bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted';
                        @endphp

                        @if ($field->key === 'product')
                            <div class="{{ $span }}">
                                <label class="mb-1.5 block text-sm font-medium text-ink">{{ $field->label }}<span class="text-danger">*</span></label>
                                <select class="{{ $input }}">
                                    <option>Choose a product</option>
                                    @foreach ($products as $product)
                                        <option>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                        @elseif ($field->key === 'package')
                            <div class="{{ $span }}">
                                <label class="mb-1.5 block text-sm font-medium text-ink">{{ $field->label }}<span class="text-danger">*</span></label>
                                <select class="{{ $input }}"><option>Select a product first</option></select>
                            </div>

                        @elseif ($field->key === 'team')
                            <div class="{{ $span }}">
                                <label class="mb-1.5 block text-sm font-medium text-ink">{{ $field->label }}</label>
                                <select class="{{ $input }}">
                                    <option>No team</option>
                                    @foreach ($teams as $team)
                                        <option>{{ $team->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                        @elseif ($field->type === 'quantity')
                            <div class="{{ $span }}">
                                <label class="mb-1.5 block text-sm font-medium text-ink">{{ $field->label }}<span class="text-danger">*</span></label>
                                <div class="flex items-stretch gap-2">
                                    <span class="flex w-11 shrink-0 items-center justify-center rounded-xl border border-line bg-elevated text-muted">&minus;</span>
                                    <input type="number" value="1" class="{{ $input }} text-center">
                                    <span class="flex w-11 shrink-0 items-center justify-center rounded-xl border border-line bg-elevated text-muted">+</span>
                                </div>
                            </div>

                        @else
                            @include('partials.form-field', ['field' => $field, 'prefill' => ''])
                        @endif
                    @endforeach

                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-ink">Total Price</label>
                        <div class="flex h-[42px] items-center justify-between rounded-xl border border-accent/25 bg-accent/10 px-3.5">
                            <span class="text-xs font-medium text-muted">USD</span>
                            <span class="text-lg font-extrabold text-accent">$0.00</span>
                        </div>
                    </div>
                </fieldset>

                <button type="button" disabled
                        class="mt-5 w-full cursor-not-allowed rounded-xl bg-gradient-to-r from-accent to-accent2 px-4 py-3.5 text-sm font-bold text-white opacity-70">
                    Submit Order
                </button>
            @endif
        </div>

        <p class="mt-3 text-center text-xs text-muted">
            {{ $fields->count() }} {{ Str::plural('field', $fields->count()) }} &middot; live layout
        </p>
    </div>
@endsection
