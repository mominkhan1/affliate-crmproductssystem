<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Models\FormField;
use App\Models\Order;
use App\Models\Product;
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

        // Totals reflect the current filter, not the whole table.
        $totals = (clone $query)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue')
            ->reorder()
            ->first();

        // Commission is only real once an order has converted — the rest is
        // still pipeline, not money earned, so it is summed separately from
        // the count and revenue above rather than folded into the same row.
        $commission = (clone $query)
            ->whereIn('status', Order::EARNING_STATUSES)
            ->selectRaw('COALESCE(SUM(user_commission_total), 0) as user_commission, COALESCE(SUM(admin_commission_total), 0) as admin_commission')
            ->reorder()
            ->first();

        $orders = $query
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'customers' => User::where('role', 'user')->orderBy('name')->get(['id', 'name', 'email']),
            'periods' => self::PERIODS,
            'sorts' => self::SORTS,
            'perPageOptions' => self::PER_PAGE,
            'statusMeta' => Order::STATUS_META,
            'totalOrders' => (int) ($totals->orders ?? 0),
            'totalRevenue' => (float) ($totals->revenue ?? 0),
            'totalUserCommission' => (float) ($commission->user_commission ?? 0),
            'totalAdminCommission' => (float) ($commission->admin_commission ?? 0),
            'activeFilterCount' => $this->activeFilterCount($filters),
        ]);
    }

    /**
     * Show a single order.
     */
    public function show(Order $order): View
    {
        $order->load(['product', 'productPrice', 'user', 'invoice', 'voiceNotes']);

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
        $order->update($request->validated());

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

        $order->update($data);
        $order->refresh();

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
            default => $query->latest(),
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
}
