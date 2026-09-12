@extends('layouts.admin')

@section('title', 'Form Builder · Med Alert')
@section('heading', 'Form Builder')

@section('content')
    <div class="rise mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">
            Drag a field from the left onto the form. Click any field to edit it. Changes go live when you save.
        </p>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.form-builder.preview') }}" target="_blank" rel="noopener"
               class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:border-accent hover:text-accent">
                Preview
            </a>
            <button type="button" id="save-form"
                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-accent to-accent2 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent/25 transition hover:opacity-90 disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span id="save-label">Save Form</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

        {{-- Palette --}}
        <aside class="rise lg:col-span-3" style="--delay: 60ms">
            <div class="rounded-2xl border border-line bg-card p-4 lg:sticky lg:top-20 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                <h2 class="mb-1 text-sm font-semibold text-ink">Fields</h2>
                <p class="mb-3 text-xs text-muted">Drag onto the form, or click to add.</p>

                <div class="grid grid-cols-2 gap-2 lg:grid-cols-1">
                    @foreach ($types as $type => $meta)
                        <button type="button" draggable="true" data-type="{{ $type }}"
                                class="palette-item group flex items-center gap-2.5 rounded-xl border border-line bg-elevated px-3 py-2.5 text-left transition hover:border-accent hover:bg-accent/5 active:cursor-grabbing">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/>
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-ink">{{ $meta['label'] }}</span>
                                <span class="hidden truncate text-[11px] text-muted lg:block">{{ $meta['hint'] }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </aside>

        {{-- Canvas --}}
        <section class="rise lg:col-span-6" style="--delay: 120ms">
            <div class="rounded-2xl border border-line bg-card p-4 sm:p-5">
                <div class="sticky top-16 z-10 -mx-4 mb-3 flex items-center justify-between border-b border-line bg-card px-4 pb-3 sm:-mx-5 sm:px-5">
                    <h2 class="text-sm font-semibold text-ink">Your Form</h2>
                    <span id="field-count" class="rounded-full bg-elevated px-2.5 py-1 text-xs font-medium text-muted"></span>
                </div>

                {{-- Scrolls within itself so a long form does not push the
                     palette and settings panels off screen. --}}
                <div id="canvas" class="min-h-[220px] space-y-2 lg:max-h-[calc(100vh-13rem)] lg:overflow-y-auto lg:pr-1"></div>

                <div id="empty-hint" class="hidden rounded-xl border-2 border-dashed border-line py-12 text-center">
                    <p class="text-sm text-muted">Drop fields here</p>
                </div>
            </div>
        </section>

        {{-- Settings --}}
        <aside class="rise lg:col-span-3" style="--delay: 180ms">
            <div id="settings" class="rounded-2xl border border-line bg-card p-4 lg:sticky lg:top-20 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                <h2 class="mb-3 text-sm font-semibold text-ink">Field Settings</h2>
                <p id="settings-empty" class="rounded-xl border border-dashed border-line px-3 py-8 text-center text-xs text-muted">
                    Select a field to edit it
                </p>
                <div id="settings-body" class="hidden space-y-4"></div>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const TYPES = @json($types);
    const SPECIAL = @json(App\Models\FormField::SPECIAL_KEYS);

    let fields = @json($fields);
    let selectedIndex = null;
    let dragFrom = null;

    const canvas = document.getElementById('canvas');
    const emptyHint = document.getElementById('empty-hint');
    const countEl = document.getElementById('field-count');
    const settingsBody = document.getElementById('settings-body');
    const settingsEmpty = document.getElementById('settings-empty');
    const saveBtn = document.getElementById('save-form');
    const saveLabel = document.getElementById('save-label');

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    /* ---------------- rendering ---------------- */

    function render() {
        canvas.innerHTML = '';
        emptyHint.classList.toggle('hidden', fields.length > 0);
        countEl.textContent = fields.length + (fields.length === 1 ? ' field' : ' fields');

        fields.forEach((field, index) => {
            const meta = TYPES[field.type] || TYPES.text;
            const locked = !!field.is_system;
            const special = SPECIAL.includes(field.key) || field.type === 'quantity';

            const row = document.createElement('div');
            row.className = 'field-row group flex items-center gap-3 rounded-xl border bg-elevated px-3 py-2.5 transition '
                + (index === selectedIndex
                    ? 'border-accent ring-2 ring-accent/20'
                    : 'border-line hover:border-accent/40');
            row.draggable = true;
            row.dataset.index = index;

            row.innerHTML = `
                <span class="drag-handle cursor-grab text-muted transition group-hover:text-accent active:cursor-grabbing" title="Drag to reorder">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M8 6h.01M8 12h.01M8 18h.01M16 6h.01M16 12h.01M16 18h.01"/>
                    </svg>
                </span>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="${meta.icon}"/>
                    </svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-ink">
                        ${esc(field.label)}${field.is_required ? '<span class="text-danger"> *</span>' : ''}
                    </span>
                    <span class="block truncate text-[11px] text-muted">
                        ${meta.label} &middot; ${field.width === 'full' ? 'full width' : 'half width'}${locked ? ' &middot; built in' : ''}
                    </span>
                </span>
                ${special ? '<span class="rounded-md bg-info/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-info" title="Filled in automatically from your products">auto</span>' : ''}
                ${locked
                    ? '<span class="text-muted" title="Built in field, cannot be deleted"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>'
                    : `<button type="button" class="remove-field rounded-lg p-1.5 text-muted transition hover:bg-danger/10 hover:text-danger" title="Remove">
                           <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                       </button>`}
            `;

            row.addEventListener('click', (e) => {
                if (e.target.closest('.remove-field')) return;
                selectedIndex = index;
                render();
                renderSettings();
            });

            const removeBtn = row.querySelector('.remove-field');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    Modal.confirm({
                        title: 'Remove field',
                        message: `"${field.label}" will be removed from the form. Answers already collected are kept.`,
                        confirmText: 'Remove',
                        onConfirm: () => {
                            fields.splice(index, 1);
                            selectedIndex = null;
                            render();
                            renderSettings();
                        },
                    });
                });
            }

            row.addEventListener('dragstart', (e) => {
                dragFrom = index;
                row.classList.add('opacity-40');
                e.dataTransfer.effectAllowed = 'move';
            });

            row.addEventListener('dragend', () => {
                dragFrom = null;
                row.classList.remove('opacity-40');
                document.querySelectorAll('.drop-line').forEach(el => el.remove());
            });

            row.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (dragFrom === null) return;

                const box = row.getBoundingClientRect();
                const after = e.clientY > box.top + box.height / 2;

                document.querySelectorAll('.drop-line').forEach(el => el.remove());
                const line = document.createElement('div');
                line.className = 'drop-line h-0.5 rounded bg-accent';
                row.parentNode.insertBefore(line, after ? row.nextSibling : row);
            });

            row.addEventListener('drop', (e) => {
                e.preventDefault();
                document.querySelectorAll('.drop-line').forEach(el => el.remove());
                if (dragFrom === null) return;

                const box = row.getBoundingClientRect();
                let to = e.clientY > box.top + box.height / 2 ? index + 1 : index;

                const [moved] = fields.splice(dragFrom, 1);
                if (dragFrom < to) to--;
                fields.splice(to, 0, moved);

                selectedIndex = to;
                dragFrom = null;
                render();
                renderSettings();
            });

            canvas.appendChild(row);
        });
    }

    /* ---------------- settings panel ---------------- */

    function renderSettings() {
        const field = fields[selectedIndex];

        settingsEmpty.classList.toggle('hidden', !!field);
        settingsBody.classList.toggle('hidden', !field);

        if (!field) return;

        const meta = TYPES[field.type] || TYPES.text;
        const locked = !!field.is_system;
        const input = 'w-full rounded-xl border border-line bg-elevated px-3 py-2 text-sm text-ink placeholder-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30';

        settingsBody.innerHTML = `
            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Label</label>
                <input type="text" id="s-label" value="${esc(field.label)}" class="${input}">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Type</label>
                <select id="s-type" class="${input}" ${locked ? 'disabled' : ''}>
                    ${Object.entries(TYPES).map(([k, m]) =>
                        `<option value="${k}" ${field.type === k ? 'selected' : ''}>${m.label}</option>`).join('')}
                </select>
                ${locked ? '<p class="mt-1 text-[11px] text-muted">Built in fields keep their type.</p>' : ''}
            </div>

            ${(SPECIAL.includes(field.key) || field.type === 'quantity') ? `
            <div class="rounded-xl border border-info/30 bg-info/10 p-3">
                <p class="text-xs font-semibold text-ink">Filled in automatically</p>
                <p class="mt-1 text-[11px] text-muted">
                    ${field.key === 'product'
                        ? 'Every active product appears here on the storefront. Manage them under Products.'
                        : field.key === 'package'
                            ? 'Loads the packages of the product the customer picks, with live pricing.'
                            : field.key === 'team'
                                ? 'Each customer only sees their own active teams here. Manage them under Teams. Optional by default.'
                                : 'Renders as a stepper and multiplies the total. Remove it and every order counts as one unit.'}
                </p>
            </div>` : (meta.has_options ? `
            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Options</label>
                <textarea id="s-options" rows="4" placeholder="One per line" class="${input}">${esc((field.options || []).join('\n'))}</textarea>
                <p class="mt-1 text-[11px] text-muted">One choice per line.</p>
            </div>` : '')}

            ${['select','radio','checkbox','file'].includes(field.type) ? '' : `
            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Placeholder</label>
                <input type="text" id="s-placeholder" value="${esc(field.placeholder)}" class="${input}">
            </div>`}

            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Help text</label>
                <input type="text" id="s-help" value="${esc(field.help_text)}" placeholder="Shown under the field" class="${input}">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">Width</label>
                <div class="grid grid-cols-2 gap-2">
                    ${['half','full'].map(w => `
                        <button type="button" data-width="${w}"
                                class="s-width rounded-xl border px-3 py-2 text-sm font-medium transition ${
                                    field.width === w ? 'border-accent bg-accent/10 text-accent' : 'border-line text-muted hover:border-accent/40'}">
                            ${w === 'half' ? 'Half row' : 'Full row'}
                        </button>`).join('')}
                </div>
            </div>

            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-line px-3 py-2.5">
                <span class="text-sm font-medium text-ink">Required</span>
                <input type="checkbox" id="s-required" ${field.is_required ? 'checked' : ''}
                       class="h-4 w-4 rounded border-line text-accent focus:ring-accent/30">
            </label>
        `;

        const bind = (id, prop, ev = 'input') => {
            const el = document.getElementById(id);
            if (el) el.addEventListener(ev, () => {
                field[prop] = el.type === 'checkbox' ? el.checked : el.value;
                if (prop === 'label' || prop === 'is_required') render();
            });
        };

        bind('s-label', 'label');
        bind('s-placeholder', 'placeholder');
        bind('s-help', 'help_text');
        bind('s-required', 'is_required', 'change');

        const typeEl = document.getElementById('s-type');
        if (typeEl) typeEl.addEventListener('change', () => {
            field.type = typeEl.value;
            render();
            renderSettings();
        });

        const optionsEl = document.getElementById('s-options');
        if (optionsEl) optionsEl.addEventListener('input', () => {
            field.options = optionsEl.value.split('\n').map(o => o.trim()).filter(Boolean);
        });

        settingsBody.querySelectorAll('.s-width').forEach(btn => {
            btn.addEventListener('click', () => {
                field.width = btn.dataset.width;
                render();
                renderSettings();
            });
        });
    }

    /* ---------------- adding fields ---------------- */

    function addField(type) {
        const meta = TYPES[type] || TYPES.text;

        if (type === 'quantity' && fields.some(f => f.type === 'quantity')) {
            Modal.alert({
                title: 'Quantity is already on the form',
                message: 'Only one Quantity field can be used. Remove the existing one first if you want to move it.',
            });
            return;
        }

        fields.push({
            id: null,
            key: null,
            type: type,
            label: meta.label + ' field',
            placeholder: '',
            help_text: '',
            is_required: false,
            is_system: false,
            width: 'half',
            options: meta.has_options ? ['Option one', 'Option two'] : null,
        });

        selectedIndex = fields.length - 1;
        render();
        renderSettings();
        canvas.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.querySelectorAll('.palette-item').forEach(item => {
        item.addEventListener('click', () => addField(item.dataset.type));
        item.addEventListener('dragstart', (e) => {
            dragFrom = null;
            e.dataTransfer.setData('text/type', item.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    [canvas, emptyHint].forEach(zone => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('ring-2', 'ring-accent/30', 'rounded-xl');
        });
        zone.addEventListener('dragleave', () => {
            zone.classList.remove('ring-2', 'ring-accent/30', 'rounded-xl');
        });
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('ring-2', 'ring-accent/30', 'rounded-xl');
            document.querySelectorAll('.drop-line').forEach(el => el.remove());

            const type = e.dataTransfer.getData('text/type');
            if (type) addField(type);
        });
    });

    /* ---------------- saving ---------------- */

    saveBtn.addEventListener('click', async () => {
        saveBtn.disabled = true;
        saveLabel.textContent = 'Saving…';

        try {
            const response = await fetch(@json(route('admin.form-builder.save')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ fields: fields }),
            });

            const payload = await response.json();

            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(payload.message || errors || 'Could not save.');
            }

            fields = payload.fields;
            selectedIndex = null;
            render();
            renderSettings();

            saveLabel.textContent = 'Saved';
            setTimeout(() => { saveLabel.textContent = 'Save Form'; }, 1600);
        } catch (error) {
            Modal.alert({ title: 'Could not save', message: error.message });
            saveLabel.textContent = 'Save Form';
        } finally {
            saveBtn.disabled = false;
        }
    });

    render();
    renderSettings();
})();
</script>
@endpush
