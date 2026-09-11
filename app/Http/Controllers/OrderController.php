<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\FormField;
use App\Models\Order;
use App\Models\Product;
use App\Support\DateRange;
use App\Support\OrderFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Show the public order form.
     */
    public function create(): View
    {
        return view('frontend.order', [
            'products' => Product::active()->orderBy('name')->get(),
            'fields' => FormField::visible()->get(),
        ]);
    }

    /**
     * Months covered by the earnings chart.
     */
    private const CHART_MONTHS = 6;

    /**
     * The signed in customer's own dashboard.
     *
     * Every figure reflects the current filter, and everything is scoped to
     * the signed in account. Only that customer's own commission is exposed -
     * the admin's cut stays in the admin panel.
     */
    public function history(Request $request): View
    {
        $user = $request->user();
        $filters = OrderFilters::parse($request, withAccounts: false);

        // One scope behind every number on the page.
        $scope = fn () => $user->orders()->tap(fn (Builder $q) => OrderFilters::apply($q, $filters));

        $orders = $scope()
            ->with(['product', 'productPrice', 'invoice'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $counts = $scope()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->reorder()
            ->pluck('total', 'status');

        $totalOrders = (int) $counts->sum();
        $earnedOrders = $this->sumStatuses($counts, Order::EARNING_STATUSES);

        // Commission only counts once a sale is done, and comes back off the
        // total if that order later goes back.
        $confirmed = (float) $scope()->whereIn('status', Order::EARNING_STATUSES)->sum('user_commission_total');
        $reversed = (float) $scope()->whereIn('status', Order::REVERSING_STATUSES)->sum('user_commission_total');
        $earned = $confirmed - $reversed;

        $pending = (float) $scope()->whereIn('status', Order::OPEN_STATUSES)->sum('user_commission_total');
        $revenue = (float) $scope()->whereIn('status', Order::EARNING_STATUSES)->sum('total_price');

        return view('frontend.history', [
            'orders' => $orders,
            'totalOrders' => $totalOrders,
            'newOrders' => $this->sumStatuses($counts, Order::OPEN_STATUSES),
            'paidOrders' => $earnedOrders,
            'cancelledOrders' => $this->sumStatuses($counts, Order::LOST_STATUSES),

            'earned' => $earned,
            'confirmed' => $confirmed,
            'reversed' => $reversed,
            'returningOrders' => $this->sumStatuses($counts, Order::REVERSING_STATUSES),
            'pending' => $pending,
            'lifetime' => $earned + $pending,
            'revenue' => $revenue,
            'averageEarning' => $earnedOrders > 0 ? $confirmed / $earnedOrders : 0.0,
            'conversionRate' => $totalOrders > 0 ? $earnedOrders / $totalOrders * 100 : 0.0,

            'series' => $this->earningsByMonth($scope),
            'statusBreakdown' => collect(Order::STATUS_META)
                ->map(fn ($meta, $key) => [
                    'label' => $meta['label'],
                    'tone' => $meta['tone'],
                    'value' => (int) $counts->get($key, 0),
                ])
                ->filter(fn ($row) => $row['value'] > 0)
                ->sortByDesc('value')
                ->values(),
            'topProducts' => $this->topEarningProducts($scope),

            'filters' => $filters,
            'periods' => DateRange::PERIODS,
            'statusMeta' => Order::STATUS_META,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'rangeLabel' => DateRange::label($filters['period'], $filters['from'], $filters['to']),
            'activeFilterCount' => OrderFilters::activeCount($filters),
        ]);
    }

    /**
     * Commission earned per month across the recent window.
     *
     * Grouped in PHP rather than SQL so the query stays driver independent.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function earningsByMonth(callable $scope): Collection
    {
        $tz = config('app.display_timezone');
        $start = CarbonImmutable::now($tz)->startOfMonth()->subMonths(self::CHART_MONTHS - 1);

        $rows = $scope()
            ->whereIn('status', Order::EARNING_STATUSES)
            ->where('created_at', '>=', $start->utc())
            ->reorder()
            ->get(['created_at', 'user_commission_total'])
            ->groupBy(fn ($order) => $order->created_at->timezone($tz)->format('Y-m'));

        return collect(range(0, self::CHART_MONTHS - 1))->map(function (int $offset) use ($start, $rows) {
            $month = $start->addMonths($offset);
            $bucket = $rows->get($month->format('Y-m'));

            return [
                'label' => $month->format('M Y'),
                'short' => $month->format('M'),
                'value' => (float) ($bucket?->sum('user_commission_total') ?? 0),
                'orders' => (int) ($bucket?->count() ?? 0),
            ];
        });
    }

    /**
     * The products this customer earns the most from.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function topEarningProducts(callable $scope): Collection
    {
        return $scope()
            ->whereIn('status', Order::EARNING_STATUSES)
            ->selectRaw('product_id, COUNT(*) as orders, SUM(user_commission_total) as earned')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->reorder()
            ->orderByDesc('earned')
            ->take(4)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->product?->name ?? 'Removed product',
                'orders' => (int) $row->orders,
                'earned' => (float) $row->earned,
            ]);
    }

    /**
     * Total the given statuses out of a status => count map.
     *
     * @param  array<int, string>  $statuses
     */
    private function sumStatuses(Collection $counts, array $statuses): int
    {
        return (int) collect($statuses)->sum(fn ($status) => (int) $counts->get($status, 0));
    }

    /**
     * Return a product's price options for the dependent dropdown.
     */
    public function prices(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json(
            $product->prices()
                ->orderBy('price')
                ->get(['id', 'label', 'price'])
                ->map(fn ($price) => [
                    'id' => $price->id,
                    'label' => $price->label,
                    'price' => (float) $price->price,
                ]),
        );
    }

    /**
     * Store a submitted order.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        Order::create([
            // Tie the order to the signed in account.
            'user_id' => $request->user()->id,
            ...$request->columnAnswers(),
            ...$request->safe()->only(['product_id', 'product_price_id']),
            'quantity' => $request->quantity(),
            'form_data' => $request->customAnswers(),
            // Never trust the figures that came from the browser.
            'total_price' => $request->total(),
            'user_commission_total' => $request->userCommission(),
            'admin_commission_total' => $request->adminCommission(),
            'status' => 'new',
        ]);

        return redirect()
            ->route('order.create')
            ->with('status', 'Your order has been submitted successfully. We will contact you soon.');
    }
}
