<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Models\FormField;
use App\Models\Order;
use App\Models\OrderVoiceNote;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\DateRange;
use App\Support\OrderFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Date range presets offered in the filter bar.
     */
    public const PERIODS = DateRange::PERIODS;

    /**
     * Sort options.
     */
    public const SORTS = [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'total_desc' => 'Highest total',
        'total_asc' => 'Lowest total',
        'commission_desc' => 'Highest user commission',
    ];

    /**
     * Page sizes.
     */
    public const PER_PAGE = [15, 25, 50, 100];

    /**
     * List orders with search, status, date range, product and sort filters.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $query = Order::with(['product', 'productPrice', 'user'])
            ->tap(fn (Builder $q) => $this->applyFilters($q, $filters));

        // Status counts reflect the current filter, not the whole table.
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->reorder()
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = $query
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'teams' => Team::with('user:id,name')->orderBy('name')->get(['id', 'user_id', 'name']),
            'customers' => User::where('role', 'user')->orderBy('name')->get(['id', 'name', 'email']),
            'periods' => self::PERIODS,
            'sorts' => self::SORTS,
            'perPageOptions' => self::PER_PAGE,
            'statusMeta' => Order::STATUS_META,
            'totalOrders' => (int) $statusCounts->sum(),
            'statusCounts' => $statusCounts,
            'activeFilterCount' => $this->activeFilterCount($filters),
        ]);
    }

    /**
     * Show a single order.
     */
    public function show(Order $order): View
    {
        $order->load(['product', 'productPrice', 'team', 'user', 'invoice', 'voiceNotes', 'activities']);

        return view('admin.orders.show', [
            'order' => $order,
            // Keyed by field key so answers can be labelled properly.
            'customFields' => FormField::all()->keyBy('key'),
        ]);
    }

    /**
     * Update an order's status and internal notes.
     */
    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $oldStatus = $order->status;
        $oldNotes = $order->notes;
        $causer = $request->user()->name;

        $order->update($request->validated());

        if ($order->status !== $oldStatus) {
            $order->logActivity(
                'Status changed from '.$this->statusLabel($oldStatus).' to '.$order->statusLabel().'.',
                $causer,
            );
        }

        if ($order->notes !== $oldNotes && filled($order->notes)) {
            $order->logActivity('Note: '.$order->notes, $causer);
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order updated.');
    }

    /**
     * Show the edit form so a mistake on an order can be corrected.
     */
    public function edit(Order $order): View
    {
        $order->load(['product', 'productPrice']);

        return view('admin.orders.edit', [
            'order' => $order,
            'products' => Product::with('prices')->orderBy('name')->get(),
            'fields' => FormField::visible()->get(),
        ]);
    }

    /**
     * Save the corrected order, recalculating the money from the package.
     */
    public function updateDetails(EditOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update([
            ...$request->safe()->only([
                'full_name', 'email', 'phone', 'address',
                'product_id', 'product_price_id', 'quantity',
            ]),
            ...$request->money(),
            // Keep answers that are not editable here, such as uploads.
            'form_data' => array_merge(
                $order->form_data ?? [],
                array_filter((array) $request->input('form_data', []), fn ($v) => $v !== null),
            ),
        ]);

        $order->logActivity('Order details were corrected.', $request->user()->name);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order #'.$order->id.' updated.');
    }

    /**
     * Move an order to the trash.
     */
    public function destroy(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $order->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Order #'.$order->id.' moved to trash.',
                'trashed' => Order::onlyTrashed()->count(),
            ]);
        }

        return redirect()
            ->route('admin.orders.index')
            ->with('status', 'Order #'.$order->id.' moved to trash.');
    }

    /**
     * The trash: orders that were deleted but can still be brought back.
     */
    public function trash(Request $request): View
    {
        $orders = Order::onlyTrashed()
            ->with(['product', 'productPrice', 'user', 'invoice'])
            ->when($request->query('q'), function (Builder $query, $term) {
                $term = '%'.$term.'%';

                $query->where(fn (Builder $q) => $q
                    ->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.trash', [
            'orders' => $orders,
            'search' => (string) $request->query('q', ''),
        ]);
    }

    /**
     * Put a trashed order back.
     */
    public function restore(int $orderId): RedirectResponse
    {
        $order = Order::onlyTrashed()->findOrFail($orderId);
        $order->restore();

        return redirect()
            ->route('admin.orders.trash')
            ->with('status', 'Order #'.$order->id.' restored.');
    }

    /**
     * Delete a trashed order for good.
     */
    public function forceDelete(int $orderId): RedirectResponse
    {
        $order = Order::onlyTrashed()->with('voiceNotes')->findOrFail($orderId);

        // Take every voice note with it, so nothing is orphaned on disk.
        Storage::disk('public')->delete($order->voiceNotes->pluck('path')->all());

        $order->forceDelete();

        return redirect()
            ->route('admin.orders.trash')
            ->with('status', 'Order #'.$orderId.' deleted permanently.');
    }

    /**
     * Change an order's status from the list, without a page reload.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $rules = ['status' => ['required', Rule::in(Order::statuses())]];

        // The date-bearing statuses still demand their date here.
        foreach (Order::STATUS_DATES as $status => $meta) {
            $rules[$meta['column']] = [
                Rule::requiredIf(fn () => $request->input('status') === $status),
                'nullable',
                'date',
            ];
        }

        $data = $request->validate($rules, [
            'post_date.required' => 'Enter the date the customer will pay.',
            'sale_date.required' => 'Enter the date the sale was made.',
            'return_date.required' => 'Enter the date the chargeback happened.',
            'paid_date.required' => 'Enter the date payment was collected.',
        ]);

        $oldStatus = $order->status;

        $order->update($data);
        $order->refresh();

        if ($order->status !== $oldStatus) {
            $order->logActivity(
                'Status changed from '.$this->statusLabel($oldStatus).' to '.$order->statusLabel().'.',
                $request->user()->name,
            );
        }

        return response()->json([
            'status' => $order->status,
            'label' => $order->statusLabel(),
            'classes' => $order->statusClasses(),
            'date_label' => $order->statusDateLabel(),
            'date_value' => $order->statusDateValue(),
            'changed_at' => $order->statusChangedAt()?->format('M j, g:i A'),
            'message' => 'Order #'.$order->id.' set to '.$order->statusLabel().'.',
        ]);
    }

    /**
     * Remove one voice note from an order.
     *
     * Only an admin can do this — a customer's upload is kept as a record
     * once it lands, so they can no longer take it back.
     */
    public function destroyVoiceNote(Request $request, Order $order, OrderVoiceNote $voiceNote): RedirectResponse
    {
        abort_unless($voiceNote->order_id === $order->id, 404);

        $name = $voiceNote->name;

        Storage::disk('public')->delete($voiceNote->path);
        $voiceNote->delete();

        $order->logActivity('Voice note removed: '.$name, $request->user()->name);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Voice note removed.');
    }

    /**
     * Read and sanitise every filter off the request.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $sort = $request->query('sort');
        $perPage = (int) $request->query('per_page', 15);

        return OrderFilters::parse($request) + [
            'sort' => array_key_exists((string) $sort, self::SORTS) ? $sort : 'newest',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 15,
        ];
    }

    /**
     * Apply the filters to the query.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        OrderFilters::apply($query, $filters);

        match ($filters['sort']) {
            'oldest' => $query->oldest(),
            'total_desc' => $query->orderByDesc('total_price'),
            'total_asc' => $query->orderBy('total_price'),
            'commission_desc' => $query->orderByDesc('user_commission_total'),
            default => $query->latest('id'),
        };
    }

    /**
     * Turn the chosen period into a concrete UTC range.
     *
     * Boundaries are worked out in the display timezone so "today" means
     * today for whoever is reading the screen, then converted for the query.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function dateRange(array $filters): array
    {
        return OrderFilters::range($filters);
    }

    /**
     * How many filters are currently narrowing the list.
     *
     * @param  array<string, mixed>  $filters
     */
    private function activeFilterCount(array $filters): int
    {
        return OrderFilters::activeCount($filters)
            + ($filters['sort'] !== 'newest' ? 1 : 0);
    }

    /**
     * A status key's admin-facing label, for describing a transition even
     * after the order has already moved on to the next status.
     */
    private function statusLabel(string $status): string
    {
        return Order::STATUS_META[$status]['label'] ?? $status;
    }
}
