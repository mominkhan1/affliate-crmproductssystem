<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormField;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormBuilderController extends Controller
{
    /**
     * Show the drag and drop builder.
     */
    public function index(): View
    {
        $this->ensureSystemFields();

        return view('admin.form-builder.index', [
            'fields' => FormField::orderBy('sort_order')->get(),
            'types' => FormField::TYPES,
        ]);
    }

    /**
     * Render the live form exactly as a customer sees it.
     *
     * The storefront itself redirects admins away, so preview needs its own
     * route inside the admin area.
     */
    public function preview(): View
    {
        return view('admin.form-builder.preview', [
            'fields' => FormField::visible()->get(),
            'products' => Product::active()->orderBy('name')->get(),
            // Teams are private per customer, and this preview isn't any one
            // customer, so there is nothing correct to list here.
            'teams' => collect(),
        ]);
    }

    /**
     * Save the whole layout in one go.
     *
     * The builder posts the complete field list, so this replaces the saved
     * layout rather than patching it field by field.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.id' => ['nullable', 'integer', 'exists:form_fields,id'],
            'fields.*.key' => ['nullable', 'string', 'max:64'],
            'fields.*.type' => ['required', Rule::in(array_keys(FormField::TYPES))],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:255'],
            'fields.*.is_required' => ['required', 'boolean'],
            'fields.*.width' => ['required', Rule::in(['half', 'full'])],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['nullable', 'string', 'max:120'],
        ]);

        // A second quantity field would fight the first for the same value.
        $quantityCount = collect($data['fields'])->where('type', 'quantity')->count();

        if ($quantityCount > 1) {
            return response()->json([
                'message' => 'Only one Quantity field can be on the form.',
            ], 422);
        }

        // Every system field must survive, since order columns depend on them.
        $submittedIds = collect($data['fields'])->pluck('id')->filter()->all();
        $missing = FormField::where('is_system', true)->whereNotIn('id', $submittedIds)->pluck('label');

        if ($missing->isNotEmpty()) {
            return response()->json([
                'message' => 'These built in fields cannot be removed: '.$missing->join(', ').'.',
            ], 422);
        }

        DB::transaction(function () use ($data) {
            $keptIds = [];

            foreach ($data['fields'] as $order => $row) {
                $existing = ! empty($row['id']) ? FormField::find($row['id']) : null;

                $attributes = [
                    'type' => $existing?->is_system ? $existing->type : $row['type'],
                    'label' => $row['label'],
                    'placeholder' => $row['placeholder'] ?? null,
                    'help_text' => $row['help_text'] ?? null,
                    'is_required' => $row['is_required'],
                    'width' => $row['width'],
                    'options' => $this->cleanOptions($row['options'] ?? null),
                    'sort_order' => $order,
                    'is_active' => true,
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $keptIds[] = $existing->id;

                    continue;
                }

                $field = FormField::create([
                    ...$attributes,
                    'key' => $this->uniqueKey($row['label']),
                    'is_system' => false,
                ]);

                $keptIds[] = $field->id;
            }

            // Anything the admin dragged out is removed; system fields are
            // already guaranteed to be present by the check above.
            FormField::whereNotIn('id', $keptIds)->where('is_system', false)->delete();
        });

        return response()->json([
            'message' => 'Form saved.',
            'fields' => FormField::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Make sure the built in fields exist.
     *
     * The order form cannot function without product, package and quantity,
     * so if they are missing, for instance because the seeder never ran on a
     * new environment, they are recreated rather than leaving a broken form.
     */
    private function ensureSystemFields(): void
    {
        $defaults = [
            ['key' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'placeholder' => 'John Smith'],
            ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'placeholder' => 'john@example.com'],
            ['key' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'placeholder' => '(555) 123 4567'],
            ['key' => 'address', 'type' => 'textarea', 'label' => 'Address', 'placeholder' => '1234 MAIN ST APT 5, LOS ANGELES CA 90001'],
            ['key' => 'product', 'type' => 'select', 'label' => 'Select Product', 'placeholder' => null],
            ['key' => 'package', 'type' => 'select', 'label' => 'Select Price / Package', 'placeholder' => null],
            ['key' => 'team', 'type' => 'select', 'label' => 'Team', 'placeholder' => null],
        ];

        foreach ($defaults as $index => $field) {
            if (FormField::where('key', $field['key'])->exists()) {
                continue;
            }

            FormField::create([
                ...$field,
                'is_system' => true,
                // Picking a team is optional; everything else here is not.
                'is_required' => $field['key'] !== 'team',
                'is_active' => true,
                'width' => 'half',
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Tidy the options list, dropping blanks.
     *
     * @param  array<int, string|null>|null  $options
     * @return array<int, string>|null
     */
    private function cleanOptions(?array $options): ?array
    {
        if (! $options) {
            return null;
        }

        $clean = collect($options)
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        return $clean ?: null;
    }

    /**
     * Build a stable, unique machine name from the label.
     */
    private function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $base = Str::limit($base, 50, '');
        $key = $base;
        $suffix = 2;

        while (FormField::where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
