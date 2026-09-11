<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let one invoice bill a week's worth of orders rather than a single one.
     *
     * A partner works a week and then claims for it, so an invoice now carries
     * a period and the set of orders it covers. The old one-order invoice is
     * still valid — its order_id simply stays filled — and every existing row
     * is copied into the pivot so "already billed" has one place to look.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('user_id');
            $table->date('period_end')->nullable()->after('period_start');
        });

        // A period invoice belongs to no single order.
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });

        Schema::create('invoice_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // The commission as it stood when the invoice was raised, so a
            // later status change cannot quietly restate a sent claim.
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('order_value', 10, 2)->default(0);
            $table->timestamps();

            // An order is only ever billed once.
            $table->unique('order_id');
        });

        // Bring the invoices that already exist into the pivot.
        $existing = DB::table('invoices')->whereNotNull('order_id')->get(['id', 'order_id']);

        foreach ($existing as $invoice) {
            $order = DB::table('orders')->where('id', $invoice->order_id)->first();

            if (! $order) {
                continue;
            }

            DB::table('invoice_order')->insert([
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'commission' => $order->user_commission_total,
                'order_value' => $order->total_price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_order');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }
};
