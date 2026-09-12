<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // A team belongs to one customer account. Nullable so deleting
            // the account doesn't wipe teams that past orders still point
            // to — same "keep the history, null the link" rule as orders.
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
