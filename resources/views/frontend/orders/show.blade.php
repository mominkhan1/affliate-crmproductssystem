@extends('layouts.customer')

@section('title', "Order #{$order->id} · Med Alert")
@section('heading', "Order #{$order->id}")

@section('content')
    <div class="rise mb-4">
        <a href="{{ route('order.list') }}"
           class="group inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-brand">
            <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to all orders
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        <div class="space-y-4 lg:col-span-2">

            {{-- Summary --}}
            <div class="rise rounded-2xl border border-line bg-card p-5 shadow-sm sm:p-6" style="--delay: 60ms">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-ink">Order Summary</h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->statusClasses() }}">
                        {{ $order->customerStatusLabel() }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Product</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">{{ $order->product?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Package</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">{{ $order->productPrice?->label ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Quantity</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">{{ $order->quantity }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Commission</dt>
                        <dd class="mt-1 text-lg font-extrabold tracking-tight text-brand">${{ number_format($order->user_commission_total, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-muted">Submitted</dt>
                        <dd class="mt-1 text-sm font-medium text-ink">{{ $order->submittedAtLabel() }}</dd>
                    </div>
                    @foreach ($order->allStatusDates() as $dateLabel => $dateValue)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-muted">{{ $dateLabel }}</dt>
                            <dd class="mt-1 text-sm font-medium text-ink">{{ $dateValue }}</dd>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wider text-muted">Delivery Address</dt>
                        <dd class="mt-1 whitespace-pre-line rounded-xl border border-line bg-elevated p-3 text-sm font-medium text-ink">{{ $order->address }}</dd>
                    </div>
                    @if (filled($order->notes))
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wider text-muted">Notes</dt>
                            <dd class="mt-1 whitespace-pre-line rounded-xl border border-line bg-elevated p-3 text-sm font-medium text-ink">{{ $order->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Anything answered on the order form --}}
            @if (! empty($order->form_data))
                <div class="rise rounded-2xl border border-line bg-card p-5 shadow-sm sm:p-6" style="--delay: 100ms">
                    <h2 class="mb-5 text-sm font-semibold text-ink">Your Details</h2>

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        @foreach ($order->form_data as $key => $value)
                            @php $field = $customFields[$key] ?? null; @endphp
                            <div class="{{ $field && $field->width === 'full' ? 'sm:col-span-2' : '' }}">
                                <dt class="text-xs uppercase tracking-wider text-muted">{{ $field?->label ?? Str::headline($key) }}</dt>
                                <dd class="mt-1 text-sm font-medium text-ink">
                                    @if ($value === null || $value === '')
                                        <span class="text-muted">Not answered</span>
                                    @elseif ($field?->type === 'file')
                                        <a href="{{ Storage::disk('public')->url($value) }}" target="_blank" rel="noopener" class="text-brand hover:underline">View upload</a>
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
        </div>

        {{-- Voice note --}}
        <div class="lg:col-span-1">
            {{-- Invoice --}}
            <div class="rise mb-4 rounded-2xl border border-line bg-card p-4 shadow-sm sm:p-5" style="--delay: 140ms">
                <div class="mb-1 flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/10 text-brand">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </span>
                    <h2 class="text-sm font-semibold text-ink">Invoice</h2>
                </div>

                @if ($order->invoice)
                    <p class="mb-3 text-xs text-muted">Sent {{ $order->invoice->sentAtLabel() }}</p>

                    <div class="rounded-xl border border-line bg-elevated p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-sm font-bold text-ink">{{ $order->invoice->number }}</p>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->invoice->statusClasses() }}">
                                    {{ $order->invoice->statusLabel() }}
                                </span>
                            </div>
                            <p class="text-2xl font-extrabold tracking-tight text-brand">
                                ${{ number_format($order->invoice->amount, 2) }}
                            </p>
                        </div>

                        @if ($order->invoice->statusChangedAtLabel())
                            <p class="mt-3 border-t border-line pt-2.5 text-[11px] text-muted">
                                {{ $order->invoice->statusLabel() }} on {{ $order->invoice->statusChangedAtLabel() }}
                            </p>
                        @endif

                        @if ($order->invoice->note)
                            <p class="mt-2 text-xs text-muted">
                                <span class="font-semibold text-ink">Your note:</span> {{ $order->invoice->note }}
                            </p>
                        @endif

                        @if ($order->invoice->admin_note)
                            <p class="mt-2 rounded-lg border border-line bg-card px-3 py-2 text-xs text-ink">
                                <span class="font-semibold">Reply:</span> {{ $order->invoice->admin_note }}
                            </p>
                        @endif
                    </div>
                @else
                    <p class="mb-3 text-xs text-muted">
                        Send an invoice for this order. You will see the status here once it is reviewed.
                    </p>

                    <form method="POST" action="{{ route('order.invoice.store', $order) }}" class="space-y-3">
                        @csrf

                        <div class="rounded-xl border border-line bg-elevated px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-medium uppercase tracking-wider text-muted">Amount</span>
                                <span class="text-xl font-extrabold tracking-tight text-brand">
                                    ${{ number_format($order->user_commission_total, 2) }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">Your commission on this order, taken from the order itself.</p>
                        </div>

                        <div>
                            <label for="note" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">
                                Note <span class="normal-case tracking-normal">(optional)</span>
                            </label>
                            <textarea name="note" id="note" rows="2" maxlength="1000"
                                      placeholder="Anything the team should know"
                                      class="w-full rounded-xl border {{ $errors->has('note') ? 'border-danger' : 'border-line' }} bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/25">{{ old('note') }}</textarea>
                            @error('note')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="cta flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand2 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand/25">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 20l18-8L3 4v6l12 2-12 2v6z"/>
                            </svg>
                            Send Invoice
                        </button>
                    </form>
                @endif
            </div>

<div class="rise rounded-2xl border border-line bg-card p-5 shadow-sm sm:p-6" style="--delay: 140ms">
                <div class="mb-1 flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/10 text-brand">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v3m0-6a4 4 0 01-4-4V6a4 4 0 118 0v5a4 4 0 01-4 4z"/>
                        </svg>
                    </span>
                    <h2 class="text-sm font-semibold text-ink">Voice Notes</h2>
                </div>
                <p class="mb-4 text-xs text-muted">
                    Attach one or more recordings for our team. Uploading a new one keeps the rest.
                </p>

                @if ($order->voiceNotes->isNotEmpty())
                    <div class="mb-4 space-y-3">
                        @foreach ($order->voiceNotes as $voiceNote)
                            <div class="rounded-xl border border-line bg-elevated p-3">
                                <audio controls preload="metadata" class="w-full">
                                    <source src="{{ $voiceNote->url() }}">
                                </audio>

                                <p class="mt-2 truncate text-xs font-medium text-ink">{{ $voiceNote->name }}</p>
                                <p class="text-[11px] text-muted">
                                    Added {{ $voiceNote->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <a href="{{ $voiceNote->url() }}" download
                                       class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:border-brand hover:text-brand">
                                        Download
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('order.voice-note.store', $order) }}"
                      enctype="multipart/form-data" id="voice-form" class="space-y-3">
                    @csrf

                    <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-muted">
                        Upload a recording
                    </p>

                    {{-- Drop zone --}}
                    <label id="drop-zone"
                           class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-line bg-elevated px-4 py-7 text-center transition hover:border-brand/50 hover:bg-brand/5">
                        <span id="drop-icon" class="mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-brand/10 text-brand transition-transform">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v3m0-6a4 4 0 01-4-4V6a4 4 0 118 0v5a4 4 0 01-4 4z"/>
                            </svg>
                        </span>
                        <span class="text-sm font-medium text-ink">Drop a recording here</span>
                        <span class="mt-0.5 text-xs text-muted">or click to choose a file</span>

                        <input type="file" name="voice_note" id="voice_note" class="hidden"
                               accept="audio/*,.mp3,.m4a,.aac,.wav,.ogg,.oga,.opus,.weba,.amr,.flac,.wma,.caf,.aiff">
                    </label>

                    {{-- Chosen file --}}
                    <div id="file-card" class="hidden rounded-xl border border-line bg-elevated p-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-3a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p id="file-name" class="truncate text-sm font-medium text-ink"></p>
                                <p id="file-size" class="text-xs text-muted"></p>
                            </div>
                            <button type="button" id="file-clear"
                                    class="shrink-0 rounded-lg p-1.5 text-muted transition hover:bg-danger/10 hover:text-danger"
                                    aria-label="Remove selected file">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Progress --}}
                        <div id="progress-wrap" class="mt-3 hidden">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span id="progress-label" class="font-medium text-ink">Uploading…</span>
                                <span id="progress-pct" class="font-semibold text-brand">0%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-line">
                                <div id="progress-bar" class="progress-stripes h-full w-0 rounded-full bg-gradient-to-r from-brand to-brand2 transition-[width] duration-200 ease-out"></div>
                            </div>
                            <p id="progress-detail" class="mt-1.5 text-[11px] text-muted"></p>
                        </div>
                    </div>

                    <p id="voice-error" class="hidden text-xs font-medium text-danger"></p>

                    <p class="text-[11px] text-muted">
                        Audio only, up to {{ round($maxUploadKb / 1024) }} MB.
                        MP3, M4A, WAV, OGG, OPUS, AMR, FLAC and more.
                    </p>

                    @if ($maxUploadKb < $intendedMaxKb)
                        <p class="rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-[11px] text-warning">
                            This server currently accepts only {{ round($maxUploadKb / 1024, 1) }} MB per upload.
                            Ask your administrator to raise PHP's upload limit to {{ round($intendedMaxKb / 1024) }} MB.
                        </p>
                    @endif

                    <div class="flex items-center gap-2">
                        <button type="submit" id="voice-submit" disabled
                                class="cta flex-1 rounded-xl bg-gradient-to-r from-brand to-brand2 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand/25 disabled:cursor-not-allowed disabled:opacity-50">
                            <span id="voice-label">Upload Voice Note</span>
                        </button>

                        <button type="button" id="voice-cancel"
                                class="hidden rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:border-danger hover:text-danger">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            {{-- Activity --}}
            <div class="rise mt-4 rounded-2xl border border-line bg-card p-5 shadow-sm sm:p-6" style="--delay: 160ms">
                <div class="mb-1 flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/10 text-brand">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <h2 class="text-sm font-semibold text-ink">Activity</h2>
                </div>
                <p class="mb-4 text-xs text-muted">Everything that has happened on this order.</p>

                @if ($order->activities->isEmpty())
                    <p class="text-sm text-muted">Nothing recorded yet.</p>
                @else
                    <ul class="space-y-5">
                        @foreach ($order->activities as $activity)
                            <li class="relative pl-5">
                                @unless ($loop->last)
                                    <span class="absolute left-[3px] top-3 h-full w-px bg-line"></span>
                                @endunless
                                <span class="absolute left-0 top-1.5 h-1.5 w-1.5 rounded-full bg-brand"></span>

                                <p class="whitespace-pre-line text-sm font-medium text-ink">{{ $activity->description }}</p>
                                <p class="mt-0.5 text-xs text-muted">
                                    {{ $activity->createdAtLabel() }}
                                    @if ($activity->causer)
                                        &middot; {{ $activity->causer }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<style>
    /* Moving stripes make a long upload feel alive */
    .progress-stripes {
        background-image: linear-gradient(
            45deg,
            rgb(255 255 255 / .18) 25%, transparent 25%,
            transparent 50%, rgb(255 255 255 / .18) 50%,
            rgb(255 255 255 / .18) 75%, transparent 75%, transparent
        ), linear-gradient(to right, rgb(var(--brand)), rgb(var(--brand2)));
        background-size: 1rem 1rem, 100% 100%;
        animation: stripes 1s linear infinite;
    }

    @keyframes stripes { from { background-position: 0 0, 0 0; } to { background-position: 1rem 0, 0 0; } }

    @keyframes pulse-ring {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.08); }
        100% { transform: scale(1); }
    }

    #drop-zone.is-dragging { border-color: rgb(var(--brand)); background: rgb(var(--brand) / .07); }
    #drop-zone.is-dragging #drop-icon { animation: pulse-ring .9s ease-in-out infinite; }

    @media (prefers-reduced-motion: reduce) {
        .progress-stripes { animation: none; }
        #drop-zone.is-dragging #drop-icon { animation: none; }
    }
</style>
<script>
    (function () {
        const form = document.getElementById('voice-form');

        if (!form) {
            return;
        }

        const input = document.getElementById('voice_note');
        const zone = document.getElementById('drop-zone');
        const card = document.getElementById('file-card');
        const nameEl = document.getElementById('file-name');
        const sizeEl = document.getElementById('file-size');
        const clearBtn = document.getElementById('file-clear');
        const submit = document.getElementById('voice-submit');
        const label = document.getElementById('voice-label');
        const cancelBtn = document.getElementById('voice-cancel');
        const errorEl = document.getElementById('voice-error');

        const wrap = document.getElementById('progress-wrap');
        const bar = document.getElementById('progress-bar');
        const pct = document.getElementById('progress-pct');
        const detail = document.getElementById('progress-detail');
        const progressLabel = document.getElementById('progress-label');

        const maxKb = @json($maxUploadKb);
        const allowed = @json($allowedExtensions);
        let request = null;

        const humanSize = (bytes) => bytes < 1048576
            ? (bytes / 1024).toFixed(0) + ' KB'
            : (bytes / 1048576).toFixed(1) + ' MB';

        function fail(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }

        function clearError() {
            errorEl.classList.add('hidden');
        }

        // Swap the animated gradient for a flat result colour.
        function paint(colour) {
            bar.classList.remove('progress-stripes');
            bar.style.backgroundImage = 'none';
            bar.style.backgroundColor = colour;
        }

        function reset() {
            input.value = '';
            card.classList.add('hidden');
            wrap.classList.add('hidden');
            submit.disabled = true;
            cancelBtn.classList.add('hidden');
            bar.style.width = '0%';
            bar.style.backgroundImage = '';
            bar.style.backgroundColor = '';
            bar.classList.add('progress-stripes');
            pct.classList.remove('text-success');
            pct.classList.add('text-brand');
            pct.textContent = '0%';
            progressLabel.textContent = 'Uploading…';
            detail.textContent = '';
        }

        function choose(file) {
            clearError();

            if (!file) {
                return reset();
            }

            const extension = (file.name.split('.').pop() || '').toLowerCase();
            const looksAudio = file.type.startsWith('audio/');

            if (file.type.startsWith('video/')) {
                return fail('Video files are not accepted. Please choose a voice recording.');
            }

            if (!looksAudio && !allowed.includes(extension)) {
                return fail('That is not an audio recording. Try MP3, M4A, WAV, OGG or AMR.');
            }

            if (file.size / 1024 > maxKb) {
                return fail('That recording is ' + humanSize(file.size)
                    + '. The limit is ' + Math.round(maxKb / 1024) + ' MB.');
            }

            nameEl.textContent = file.name;
            sizeEl.textContent = humanSize(file.size);
            card.classList.remove('hidden');
            submit.disabled = false;
        }

        input.addEventListener('change', () => choose(input.files[0]));
        clearBtn.addEventListener('click', reset);

        ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.add('is-dragging');
        }));

        ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragging');
        }));

        zone.addEventListener('drop', function (event) {
            const file = event.dataTransfer.files[0];

            if (file) {
                // Put it on the input so a normal submit would still work.
                const bucket = new DataTransfer();
                bucket.items.add(file);
                input.files = bucket.files;
                choose(file);
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const file = input.files[0];

            if (!file) {
                return fail('Choose a recording first.');
            }

            clearError();
            wrap.classList.remove('hidden');
            cancelBtn.classList.remove('hidden');
            submit.disabled = true;
            label.textContent = 'Uploading…';

            const body = new FormData(form);
            const started = Date.now();

            request = new XMLHttpRequest();
            request.open('POST', form.action);
            request.setRequestHeader('Accept', 'application/json');
            request.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);

            request.upload.addEventListener('progress', function (event) {
                if (!event.lengthComputable) {
                    return;
                }

                const percent = Math.round((event.loaded / event.total) * 100);
                bar.style.width = percent + '%';
                pct.textContent = percent + '%';

                const seconds = Math.max((Date.now() - started) / 1000, 0.1);
                const speed = event.loaded / seconds;
                const left = (event.total - event.loaded) / speed;

                detail.textContent = humanSize(event.loaded) + ' of ' + humanSize(event.total)
                    + ' · ' + humanSize(speed) + '/s'
                    + (percent < 100 && isFinite(left) ? ' · about ' + Math.ceil(left) + 's left' : '');
            });

            request.addEventListener('load', function () {
                let payload = {};

                try {
                    payload = JSON.parse(request.responseText);
                } catch (error) {
                    payload = {};
                }

                if (request.status >= 200 && request.status < 300) {
                    paint('rgb(var(--success))');
                    bar.style.width = '100%';
                    pct.textContent = '100%';
                    pct.classList.replace('text-brand', 'text-success');
                    progressLabel.textContent = 'Done';
                    detail.textContent = payload.message || 'Uploaded.';
                    label.textContent = 'Saved';
                    cancelBtn.classList.add('hidden');

                    // Let the tick register, then show the saved recording.
                    setTimeout(() => window.location.reload(), 700);
                    return;
                }

                let message = payload.errors && payload.errors.voice_note
                    ? payload.errors.voice_note[0]
                    : (payload.message || 'Upload failed. Please try again.');

                if (request.status === 413) {
                    message = 'The server refused the recording because it is too large.';
                } else if (request.status === 419) {
                    message = 'Your session expired. Refresh the page and try again.';
                }

                paint('rgb(var(--danger))');
                progressLabel.textContent = 'Failed';
                detail.textContent = '';
                fail(message);
                label.textContent = 'Try Again';
                submit.disabled = false;
                cancelBtn.classList.add('hidden');
            });

            request.addEventListener('error', function () {
                paint('rgb(var(--danger))');
                progressLabel.textContent = 'Failed';
                fail('The connection dropped during the upload.');
                label.textContent = 'Try Again';
                submit.disabled = false;
            });

            request.addEventListener('abort', function () {
                wrap.classList.add('hidden');
                bar.style.width = '0%';
                label.textContent = 'Upload Voice Note';
                submit.disabled = false;
                cancelBtn.classList.add('hidden');
            });

            request.send(body);
        });

        cancelBtn.addEventListener('click', function () {
            if (request) {
                request.abort();
            }
        });
    })();
</script>
@endpush
