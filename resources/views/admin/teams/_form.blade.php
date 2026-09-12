@php
    $inputClasses = 'w-full rounded-xl border bg-elevated px-3.5 py-2.5 text-sm text-ink placeholder-muted transition focus:outline-none focus:ring-2 focus:ring-accent/30';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="rise rounded-2xl border border-line bg-card p-5 sm:p-6">
        <h2 class="mb-5 text-sm font-semibold text-ink">Team Details</h2>

        <div class="space-y-5">
            <div>
                <label for="user_id" class="mb-1.5 block text-sm font-medium text-ink">Customer</label>
                <select name="user_id" id="user_id" required
                        class="{{ $inputClasses }} {{ $errors->has('user_id') ? 'border-danger' : 'border-line focus:border-accent' }}">
                    <option value="">Select a customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('user_id', $team->user_id) === $customer->id)>
                            {{ $customer->name }} ({{ $customer->email }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-muted">This team is private to the customer you pick — no other account sees it.</p>
                @error('user_id')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-ink">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $team->name) }}" required
                       class="{{ $inputClasses }} {{ $errors->has('name') ? 'border-danger' : 'border-line focus:border-accent' }}"
                       placeholder="East Coast Sales">
                @error('name')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $team->is_active))
                       class="h-4 w-4 rounded border-line text-accent focus:ring-accent/30">
                <span class="text-sm font-medium text-ink">Active</span>
                <span class="text-sm text-muted">— shown on the public order form</span>
            </label>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
                class="rounded-xl bg-gradient-to-r from-accent to-accent2 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent/25 transition hover:opacity-90">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.teams.index') }}"
           class="rounded-xl border border-line px-5 py-2.5 text-sm font-medium text-muted transition hover:border-accent/40 hover:text-accent">
            Cancel
        </a>
    </div>
</form>
