<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['order_id', 'path', 'name'])]
class OrderVoiceNote extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Public URL of the stored recording.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
