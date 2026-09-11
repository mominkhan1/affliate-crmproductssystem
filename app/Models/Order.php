<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'full_name',
    'email',
    'phone',
    'address',
    'product_id',
    'product_price_id',
    'quantity',
    'total_price',
    'user_commission_total',
    'admin_commission_total',
    'status',
    'post_date',
    'sale_date',
    'return_date',
    'paid_date',
    'notes',
    'form_data',
])]
class Order extends Model
{
    use SoftDeletes;

    /**
     * The COD follow-up pipeline, in the order it normally progresses.
     *
     * @var array<string, array{label: string, tone: string, customer: string}>
     */
    public const STATUS_META = [
        'new' => ['label' => 'New',                     'tone' => 'warning'],
        'callback' => ['label' => 'Callback',                'tone' => 'brand'],
        'confirmation_department' => ['label' => 'Confirmation Department', 'tone' => 'info'],
        'post_date' => ['label' => 'Post Date',               'tone' => 'info'],
        'awaiting_payment' => ['label' => 'Awaiting Payment',        'tone' => 'warning'],
        'sale' => ['label' => 'Sale',                    'tone' => 'success'],
        'active_account' => ['label' => 'Active Account',          'tone' => 'success'],
        'paid' => ['label' => 'Paid',                    'tone' => 'success'],
        'going_to_return' => ['label' => 'Chargeback',              'tone' => 'danger'],
        'card_declined' => ['label' => 'Card Declined',           'tone' => 'danger'],
        'confirmation_failure' => ['label' => 'Confirmation Failure',    'tone' => 'danger'],
        'duplicate' => ['label' => 'Duplicate',               'tone' => 'muted'],
        'cancelled' => ['label' => 'Cancelled',               'tone' => 'danger'],
    ];

    /**
     * The spreadsheet palette the customer order table is read in.
     *
     * Partners already work from a sheet where a converted order is a solid
     * green row and a lost one solid red, with the stages in between carrying
     * a soft tint. Keeping those exact fills means the two read the same.
     *
     * @var array<string, string>
     */
    public const STATUS_SHEET = [
        'new' => 'bg-[#F8C4B8] text-[#7B1D0E]',
        'callback' => 'bg-[#FCE0AE] text-[#7C4A03]',
        'confirmation_department' => 'bg-[#CFE4F7] text-[#0B4A78]',
        'post_date' => 'bg-[#D6EDD1] text-[#1B6B33]',
        'awaiting_payment' => 'bg-[#FCE0AE] text-[#7C4A03]',
        'sale' => 'bg-[#0F7A3D] text-white',
        'active_account' => 'bg-[#12603A] text-white',
        'paid' => 'bg-[#0B4A2A] text-white',
        'going_to_return' => 'bg-[#E03A2B] text-white',
        'card_declined' => 'bg-[#E03A2B] text-white',
        'confirmation_failure' => 'bg-[#E5D6F1] text-[#5B2D82]',
        'duplicate' => 'bg-[#F1F3F5] text-[#3F4855]',
        'cancelled' => 'bg-[#E03A2B] text-white',
    ];

    /**
     * Statuses that ask the admin for a date, and where that date is kept.
     *
     * @var array<string, array{column: string, label: string, help: string}>
     */
    public const STATUS_DATES = [
        'post_date' => [
            'column' => 'post_date',
            'label' => 'Payment Date',
            'help' => 'when the customer will pay',
        ],
        'sale' => [
            'column' => 'sale_date',
            'label' => 'Sale Date',
            'help' => 'when the sale was made',
        ],
        'going_to_return' => [
            'column' => 'return_date',
            'label' => 'Chargeback Date',
            'help' => 'when the chargeback happened',
        ],
        'paid' => [
            'column' => 'paid_date',
            'label' => 'Paid Date',
            'help' => 'when payment was collected',
        ],
    ];

    /**
     * Statuses that count as a converted sale, and so earn commission.
     */
    public const EARNING_STATUSES = ['sale', 'active_account', 'paid'];

    /**
     * Statuses that take a commission back off the customer's balance.
     *
     * An order only reaches these after it was already a sale, so the
     * commission it earned has to come back off the total.
     */
    public const REVERSING_STATUSES = ['going_to_return'];

    /**
     * Statuses still working towards an outcome.
     */
    public const OPEN_STATUSES = [
        'new', 'callback', 'confirmation_department', 'post_date', 'awaiting_payment',
    ];

    /**
     * Statuses that ended without a sale.
     *
     * A chargeback is not the same loss as the rest of these: it only
     * happens after an order was already earning, so it is counted on its
     * own via REVERSING_STATUSES rather than folded into "cancelled".
     */
    public const LOST_STATUSES = [
        'going_to_return', 'card_declined', 'confirmation_failure', 'duplicate', 'cancelled',
    ];

    /**
     * Statuses that ended without ever having been a sale.
     *
     * The rest of LOST_STATUSES, minus the chargeback — an order here never
     * earned anything, so there is nothing to claw back, unlike REVERSING_STATUSES.
     */
    public const CANCELLED_STATUSES = [
        'card_declined', 'confirmation_failure', 'duplicate', 'cancelled',
    ];

    /**
     * Every valid status key.
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return array_keys(self::STATUS_META);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total_price' => 'decimal:2',
            'user_commission_total' => 'decimal:2',
            'admin_commission_total' => 'decimal:2',
            'status_changed_at' => 'datetime',
            'post_date' => 'date',
            'sale_date' => 'date',
            'return_date' => 'date',
            'paid_date' => 'date',
            'form_data' => 'array',
        ];
    }

    /**
     * Stamp the moment the status moves, leaving created_at untouched.
     */
    protected static function booted(): void
    {
        static::updating(function (self $order) {
            if ($order->isDirty('status')) {
                $order->status_changed_at = now();
            }
        });
    }

    /**
     * The invoice raised against this order, if one has been sent.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Every recording attached to this order, newest first.
     */
    public function voiceNotes(): HasMany
    {
        return $this->hasMany(OrderVoiceNote::class)->orderByDesc('id');
    }

    /**
     * The invoices this order has been billed on.
     *
     * One row at most, but going through the pivot means "has this been
     * claimed for?" has a single answer whether the invoice covered this
     * order alone or a whole week of them.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class)
            ->withPivot(['commission', 'order_value'])
            ->withTimestamps();
    }

    /**
     * Orders that have earned commission and have not yet been claimed for.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereIn('status', self::EARNING_STATUSES)
            ->whereDoesntHave('invoices');
    }

    /**
     * The account that placed the order, if any.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The product that was ordered.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The price option that was ordered.
     */
    public function productPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class);
    }

    /**
     * Admin-facing label, e.g. "Contacted".
     */
    public function statusLabel(): string
    {
        return self::STATUS_META[$this->status]['label'] ?? ucfirst($this->status);
    }

    /**
     * Customer-facing wording, which is friendlier than the internal label.
     */
    public function customerStatusLabel(): string
    {
        // Customers and admins read the same word, so a phone call about
        // "Sale" or "Post Date" means the same thing on both sides.
        return $this->statusLabel();
    }

    /**
     * The Tailwind classes used to render this order's status badge.
     */
    public function statusClasses(): string
    {
        $tone = self::STATUS_META[$this->status]['tone'] ?? 'muted';

        return "bg-{$tone}/10 text-{$tone}";
    }

    /**
     * Whether this order has reached an end state (won or lost), rather
     * than still working its way through the pipeline.
     */
    public function isFinal(): bool
    {
        return ! in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * The fill and text colour this order's status cell takes in the table.
     */
    public function sheetStatusClasses(): string
    {
        return self::STATUS_SHEET[$this->status] ?? 'bg-[#F1F3F5] text-[#3F4855]';
    }

    /**
     * When the order was submitted, in the configured display timezone.
     */
    public function submittedAt(): CarbonInterface
    {
        return $this->created_at->timezone(config('app.display_timezone'));
    }

    /**
     * Full submission date and time, e.g. "Aug 17, 2026 at 3:42 PM".
     */
    public function submittedAtLabel(): string
    {
        return $this->submittedAt()->format('M j, Y \a\t g:i A');
    }

    /**
     * When the status was last changed, in the display timezone.
     *
     * An order submitted on the 14th and cleared on the 20th keeps both
     * dates: the submission date never moves, this records the change.
     */
    public function statusChangedAt(): ?CarbonInterface
    {
        return $this->status_changed_at?->timezone(config('app.display_timezone'));
    }

    /**
     * Full status change date and time, or null if it never moved.
     */
    public function statusChangedAtLabel(): ?string
    {
        return $this->statusChangedAt()?->format('M j, Y \a\t g:i A');
    }

    /**
     * How long the order took to reach its current status.
     */
    public function timeToStatus(): ?string
    {
        if (! $this->status_changed_at) {
            return null;
        }

        return $this->created_at->diffForHumans($this->status_changed_at, [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
        ]);
    }

    /**
     * The agreed payment date, e.g. "Aug 25, 2026", or null.
     */
    public function postDateLabel(): ?string
    {
        return $this->post_date?->format('M j, Y');
    }

    /**
     * The date belonging to the current status, if that status has one.
     */
    public function statusDate(): ?CarbonInterface
    {
        $column = self::STATUS_DATES[$this->status]['column'] ?? null;

        return $column ? $this->{$column} : null;
    }

    /**
     * What that date is called, e.g. "Sale Date".
     */
    public function statusDateLabel(): ?string
    {
        return self::STATUS_DATES[$this->status]['label'] ?? null;
    }

    /**
     * That date formatted for display, or null.
     */
    public function statusDateValue(): ?string
    {
        return $this->statusDate()?->format('M j, Y');
    }

    /**
     * Every date this order has picked up, keyed by its label.
     *
     * @return array<string, string>
     */
    public function allStatusDates(): array
    {
        $dates = [];

        foreach (self::STATUS_DATES as $meta) {
            $value = $this->{$meta['column']};

            if ($value) {
                $dates[$meta['label']] = $value->format('M j, Y');
            }
        }

        return $dates;
    }

    /**
     * Whether any voice note is attached.
     */
    public function hasVoiceNotes(): bool
    {
        return $this->voiceNotes->isNotEmpty();
    }

    /**
     * How far through the pipeline this order is, as a percentage.
     */
    public function progress(): int
    {
        if (in_array($this->status, self::LOST_STATUSES, true)) {
            return 100;
        }

        $steps = ['new', 'callback', 'confirmation_department', 'awaiting_payment', 'sale', 'active_account'];
        $position = array_search($this->status, $steps, true);

        return $position === false ? 0 : (int) round(($position + 1) / count($steps) * 100);
    }
}
