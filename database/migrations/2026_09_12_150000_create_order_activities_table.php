<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status labels as they stood when this migration was written. Copied
     * rather than read from App\Models\Order::STATUS_META, since a
     * migration must keep working even if that constant changes later.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'new' => 'New',
        'callback' => 'Callback',
        'confirmation_department' => 'Confirmation Department',
        'post_date' => 'Post Date',
        'awaiting_payment' => 'Awaiting Payment',
        'sale' => 'Sale',
        'active_account' => 'Active Account',
        'paid' => 'Paid',
        'going_to_return' => 'Chargeback',
        'card_declined' => 'Card Declined',
        'confirmation_failure' => 'Confirmation Failure',
        'duplicate' => 'Duplicate',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            // Who did it — an admin's or customer's name. Null for events
            // reconstructed by this migration, where that is not known.
            $table->string('causer')->nullable();
            $table->timestamps();
        });

        // Existing orders get a starting point in the timeline, built from
        // fields that already exist, rather than opening on an empty list.
        DB::table('orders')
            ->select('id', 'status', 'created_at', 'status_changed_at')
            ->orderBy('id')
            ->chunkById(200, function ($orders) {
                $rows = [];

                foreach ($orders as $order) {
                    $rows[] = [
                        'order_id' => $order->id,
                        'description' => 'Order submitted.',
                        'causer' => null,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->created_at,
                    ];

                    if ($order->status !== 'new' && $order->status_changed_at) {
                        $label = self::STATUS_LABELS[$order->status] ?? $order->status;

                        $rows[] = [
                            'order_id' => $order->id,
                            'description' => 'Status changed to '.$label.'.',
                            'causer' => null,
                            'created_at' => $order->status_changed_at,
                            'updated_at' => $order->status_changed_at,
                        ];
                    }
                }

                DB::table('order_activities')->insert($rows);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_activities');
    }
};
