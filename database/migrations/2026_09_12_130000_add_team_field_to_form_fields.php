<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds "Team" as a built in field on the order form, the same way
     * product/package were seeded — so it shows up immediately rather than
     * waiting for an admin to first open the Form Builder (which is the only
     * place FormBuilderController::ensureSystemFields() runs).
     */
    public function up(): void
    {
        if (DB::table('form_fields')->where('key', 'team')->exists()) {
            return;
        }

        $nextOrder = 1 + (int) DB::table('form_fields')->max('sort_order');

        DB::table('form_fields')->insert([
            'key' => 'team',
            'type' => 'select',
            'label' => 'Team',
            'placeholder' => null,
            'help_text' => null,
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'width' => 'half',
            'options' => null,
            'sort_order' => $nextOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('form_fields')->where('key', 'team')->delete();
    }
};
