<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key', 'type', 'label', 'placeholder', 'help_text',
    'is_required', 'is_active', 'is_system', 'width', 'options', 'sort_order',
])]
class FormField extends Model
{
    /**
     * The field types an admin can drag onto the form.
     *
     * @var array<string, array{label: string, icon: string, hint: string, has_options: bool}>
     */
    public const TYPES = [
        'text' => ['label' => 'Text', 'icon' => 'M4 6h16M4 12h10', 'hint' => 'Single line, e.g. a name', 'has_options' => false],
        'textarea' => ['label' => 'Paragraph', 'icon' => 'M4 6h16M4 10h16M4 14h12M4 18h8', 'hint' => 'Multi line, e.g. an address', 'has_options' => false],
        'email' => ['label' => 'Email', 'icon' => 'M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8a2 2 0 012-2h14a2 2 0 012 2', 'hint' => 'Validated email address', 'has_options' => false],
        'tel' => ['label' => 'Phone', 'icon' => 'M3 5a2 2 0 012-2h2.6a1 1 0 01.97.75l1 3.5a1 1 0 01-.3 1L8 10a13 13 0 006 6l1.7-1.3a1 1 0 011-.2l3.5 1a1 1 0 01.8 1V19a2 2 0 01-2 2A16 16 0 013 5z', 'hint' => 'Phone number', 'has_options' => false],
        'number' => ['label' => 'Number', 'icon' => 'M7 4v16M17 4v16M4 8h16M4 16h16', 'hint' => 'Numeric only', 'has_options' => false],
        'date' => ['label' => 'Date', 'icon' => 'M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'hint' => 'Date picker, e.g. date of birth', 'has_options' => false],
        'select' => ['label' => 'Dropdown', 'icon' => 'M8 9l4 4 4-4M4 5h16v14H4z', 'hint' => 'Pick one from a list', 'has_options' => true],
        'radio' => ['label' => 'Radio', 'icon' => 'M12 12m-3 0a3 3 0 106 0a3 3 0 10-6 0M12 21a9 9 0 100-18 9 9 0 000 18z', 'hint' => 'Pick one, all shown', 'has_options' => true],
        'checkbox' => ['label' => 'Checkbox', 'icon' => 'M9 12l2 2 4-4M4 5h16v14H4z', 'hint' => 'Single yes/no tick', 'has_options' => false],
        'card_number' => ['label' => 'Card Number', 'icon' => 'M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z', 'hint' => 'Only the last 4 digits are stored', 'has_options' => false],
        'card_expiry' => ['label' => 'Card Expiry', 'icon' => 'M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'hint' => 'MM / YY', 'has_options' => false],
        'card_cvv' => ['label' => 'CVV', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'hint' => 'Never stored, by law', 'has_options' => false],
        'quantity' => ['label' => 'Quantity', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'hint' => 'How many units, with a stepper', 'has_options' => false],
        'file' => ['label' => 'File upload', 'icon' => 'M7 16a4 4 0 01-.9-7.9 5 5 0 019.7-1.7A4.5 4.5 0 1117 16H7zm5-6v6m0-6l-2 2m2-2l2 2', 'hint' => 'Image or document', 'has_options' => false],
    ];

    /**
     * Fields that map to real order columns and cannot be removed.
     *
     * @var array<int, string>
     */
    public const SYSTEM_KEYS = ['full_name', 'email', 'phone', 'address', 'product', 'package', 'team'];

    /**
     * Keys rendered by their own dedicated markup rather than a plain input.
     *
     * @var array<int, string>
     */
    public const SPECIAL_KEYS = ['product', 'package', 'team'];

    /**
     * Card related types, which are handled with extra care on submit.
     *
     * @var array<int, string>
     */
    public const PAYMENT_TYPES = ['card_number', 'card_expiry', 'card_cvv'];

    /**
     * Whether this field collects card data.
     */
    public function isPayment(): bool
    {
        return in_array($this->type, self::PAYMENT_TYPES, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'options' => 'array',
        ];
    }

    /**
     * Only the fields that should appear on the form, in order.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Whether this field is one of the product/package/quantity blocks.
     */
    public function isSpecial(): bool
    {
        return in_array($this->key, self::SPECIAL_KEYS, true)
            || $this->type === 'quantity';
    }

    /**
     * Whether the answer lives in its own order column.
     */
    public function isColumn(): bool
    {
        return $this->is_system && ! $this->isSpecial();
    }

    /**
     * The validation rules this field contributes.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        $rules = [$this->is_required ? 'required' : 'nullable'];

        if ($this->type === 'quantity') {
            return ['required', 'integer', 'min:1', 'max:1000'];
        }

        $rules[] = match ($this->type) {
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'file' => 'file',
            'checkbox' => 'boolean',
            default => 'string',
        };

        if ($this->type === 'card_number') {
            // 13 to 19 digits, spaces allowed while typing.
            $rules[] = 'regex:/^[0-9 ]{13,23}$/';

            return $rules;
        }

        if ($this->type === 'card_expiry') {
            $rules[] = 'regex:/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2}|[0-9]{4})$/';

            return $rules;
        }

        if ($this->type === 'card_cvv') {
            $rules[] = 'regex:/^[0-9]{3,4}$/';

            return $rules;
        }

        if ($this->type === 'file') {
            $rules[] = 'max:4096';
        } elseif ($this->type === 'textarea') {
            $rules[] = 'max:2000';
        } elseif (! in_array($this->type, ['number', 'date', 'checkbox'], true)) {
            $rules[] = 'max:255';
        }

        return $rules;
    }
}
