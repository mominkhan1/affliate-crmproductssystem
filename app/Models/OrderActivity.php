<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'description', 'causer'])]
class OrderActivity extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * When this happened, in the configured display timezone.
     */
    public function createdAtLabel(): string
    {
        return $this->created_at->timezone(config('app.display_timezone'))->format('M j, Y \a\t g:i A');
    }
}
