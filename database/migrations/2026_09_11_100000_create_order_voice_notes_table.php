<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order used to carry a single voice note. A customer may now attach
     * several, so the recording moves into its own table.
     */
    public function up(): void
    {
        Schema::create('order_voice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->timestamps();
        });

        // Bring each order's existing single recording into the new table
        // rather than losing it.
        DB::table('orders')
            ->whereNotNull('voice_note_path')
            ->get(['id', 'voice_note_path', 'voice_note_name', 'voice_note_uploaded_at'])
            ->each(function ($order) {
                DB::table('order_voice_notes')->insert([
                    'order_id' => $order->id,
                    'path' => $order->voice_note_path,
                    'name' => $order->voice_note_name ?: basename($order->voice_note_path),
                    'created_at' => $order->voice_note_uploaded_at ?? now(),
                    'updated_at' => $order->voice_note_uploaded_at ?? now(),
                ]);
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['voice_note_path', 'voice_note_name', 'voice_note_uploaded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('voice_note_path')->nullable();
            $table->string('voice_note_name')->nullable();
            $table->timestamp('voice_note_uploaded_at')->nullable();
        });

        // Only the most recent recording per order can come back this way.
        DB::table('order_voice_notes')
            ->orderBy('created_at')
            ->get()
            ->groupBy('order_id')
            ->each(function ($notes, $orderId) {
                $latest = $notes->last();

                DB::table('orders')->where('id', $orderId)->update([
                    'voice_note_path' => $latest->path,
                    'voice_note_name' => $latest->name,
                    'voice_note_uploaded_at' => $latest->created_at,
                ]);
            });

        Schema::dropIfExists('order_voice_notes');
    }
};
