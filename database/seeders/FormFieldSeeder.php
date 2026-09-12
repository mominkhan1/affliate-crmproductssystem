<?php

namespace Database\Seeders;

use App\Models\FormField;
use Illuminate\Database\Seeder;

/**
 * Seeds the builder with the form that already exists, so the very first
 * visit to the builder shows the live form rather than a blank canvas.
 */
class FormFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fields = [
            ['key' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'placeholder' => 'John Smith', 'is_required' => true],
            ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'placeholder' => 'john@example.com', 'is_required' => true],
            ['key' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'placeholder' => '(555) 123 4567', 'is_required' => true],
            ['key' => 'address', 'type' => 'textarea', 'label' => 'Address', 'placeholder' => '1234 MAIN ST APT 5, LOS ANGELES CA 90001', 'is_required' => true],
            ['key' => 'product', 'type' => 'select', 'label' => 'Select Product', 'is_required' => true],
            ['key' => 'package', 'type' => 'select', 'label' => 'Select Price / Package', 'is_required' => true],
            ['key' => 'team', 'type' => 'select', 'label' => 'Team', 'is_required' => false],
            ['key' => 'quantity', 'type' => 'quantity', 'label' => 'Quantity', 'is_required' => true],
        ];

        foreach ($fields as $index => $field) {
            FormField::updateOrCreate(
                ['key' => $field['key']],
                [
                    ...$field,
                    // Quantity is optional to remove, but is still a built in
                    // field while present.
                    'is_system' => $field['key'] !== 'quantity',
                    'is_active' => true,
                    'width' => 'half',
                    'sort_order' => $index,
                ],
            );
        }
    }
}
