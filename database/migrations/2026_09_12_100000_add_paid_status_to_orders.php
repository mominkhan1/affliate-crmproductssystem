<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The pipeline as of the last enum rebuild, plus "paid".
     *
     * "paid" already exists throughout the app (Order::STATUS_META,
     * EARNING_STATUSES, the paid_date column) but was dropped from the
     * MySQL enum when expand_order_statuses_full rebuilt it, so setting an
     * order to Paid fails with a truncation error.
     */
    private const STATUSES = [
        'new', 'post_date', 'going_to_return', 'sale', 'awaiting_payment',
        'confirmation_department', 'duplicate', 'cancelled', 'confirmation_failure',
        'card_declined', 'callback', 'active_account', 'paid',
    ];

    private const PREVIOUS_STATUSES = [
        'new', 'post_date', 'going_to_return', 'sale', 'awaiting_payment',
        'confirmation_department', 'duplicate', 'cancelled', 'confirmation_failure',
        'card_declined', 'callback', 'active_account',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setEnum(self::STATUSES);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->where('status', 'paid')->update(['status' => 'active_account']);

        $this->setEnum(self::PREVIOUS_STATUSES);
    }

    /**
     * Point the status column at the given set of values.
     *
     * @param  array<int, string>  $statuses
     */
    private function setEnum(array $statuses): void
    {
        // Only MySQL has ENUM; elsewhere the column is already a plain string.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $list = "'".implode("','", $statuses)."'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM($list) NOT NULL DEFAULT 'new'");
    }
};
