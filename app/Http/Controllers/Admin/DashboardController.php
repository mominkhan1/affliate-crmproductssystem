<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\DateRange;
use App\Support\OrderFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Days covered when no explicit range is chosen.
     */
    private const DEFAULT_DAYS = 14;

    /**
     * Beyond this many days the activity chart groups by month.
     */
    private const DAILY_LIMIT = 62;

    /**
     * Show the admin dashboard for the selected range.
     */
    public function index(Request $request): View
    {
        $filters = OrderFilters::parse($request);
        [$from, $to] = OrderFilters::range($filters);

        // Every figure on the page is built from this same scope, and it is
        // the same filter set the orders list uses.
        $scope = fn () => Order::query()->tap(fn (Builder $q) => OrderFilters::apply($q, $filters));

        $counts = $scope()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sumFor = fn (array $statuses) => (int) collect($statuses)
            ->sum(fn ($status) => (int) $counts->get($status, 0));

        $totalOrders = (int) $counts->sum();
        $completedOrders = $sumFor(Order::EARNING_STATUSES);

        $revenue = (float) $scope()->whereIn('status', Order::EARNING_STATUSES)->sum('total_price');
        $userCommission = (float) $scope()->whereIn('status', Order::EARNING_STATUSES)->sum('user_commission_total');
        $adminCommission = (float) $scope()->whereIn('status', Order::EARNING_STATUSES)->sum('admin_commission_total');
        $totalCommission = $userCommission + $adminCommission;

        // Only orders currently sitting in "Sale" — not active_account/paid — count here.
        $saleOrders = (int) $counts->get('sale', 0);
        $saleAdminCommission = (float) $scope()->where('status', 'sale')->sum('admin_commission_total');

        $series = $this->series($scope, $from, $to);

        return view('admin.dashboard', [
            'totalOrders' => $totalOrders,
            'openOrders' => $sumFor(Order::OPEN_STATUSES),
            'completedOrders' => $completedOrders,
            // Cancelled and Chargeback read as two different outcomes: one
            // never converted, the other did and then came back.
            'cancelledOrders' => $sumFor(Order::CANCELLED_STATUSES),
            'chargebackOrders' => $sumFor(Order::REVERSING_STATUSES),

            'statusCounts' => collect(Order::STATUS_META)
                ->map(fn ($meta, $key) => [
                    'label' => $meta['label'],
                    'token' => $meta['tone'],
                    'value' => (int) $counts->get($key, 0),
                ])
                ->values(),

            'revenue' => $revenue,
            'userCommission' => $userCommission,
            'adminCommission' => $adminCommission,
            'totalCommission' => $totalCommission,
            'averageSaleCommission' => $saleOrders > 0 ? $saleAdminCommission / $saleOrders : 0.0,
            'conversionRate' => $totalOrders > 0 ? $completedOrders / $totalOrders * 100 : 0.0,

            'series' => $series,
            'trend' => $this->trendPercentage($series),
            'topProducts' => $this->topProducts($scope),

            'recentOrders' => $scope()
                ->with(['product', 'productPrice'])
                ->latest()
                ->take(5)
                ->get(),

            'filters' => $filters,
            'periods' => DateRange::PERIODS,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'customers' => User::where('role', 'user')->orderBy('name')->get(['id', 'name', 'email']),
            'statusMeta' => Order::STATUS_META,
            'rangeLabel' => DateRange::label($filters['period'], $filters['from'], $filters['to']),
            'activeFilterCount' => OrderFilters::activeCount($filters),
        ]);
    }

    /**
     * Orders and revenue over the selected range, with empty buckets filled.
     *
     * Short ranges group by day; anything longer than two months groups by
     * month so the chart stays readable.
     */
    private function series(callable $scope, ?CarbonImmutable $from, ?CarbonImmutable $to): Collection
    {
        $tz = config('app.display_timezone');

        $start = $from
            ? $from->timezone($tz)->startOfDay()
            : CarbonImmutable::now($tz)->subDays(self::DEFAULT_DAYS - 1)->startOfDay();

        $end = $to ? $to->timezone($tz)->endOfDay() : CarbonImmutable::now($tz)->endOfDay();

        if ($end->lessThan($start)) {
            $end = $start->endOfDay();
        }

        $byMonth = $start->diffInDays($end) > self::DAILY_LIMIT;

        // DATE_FORMAT is MySQL only; SQLite spells the same thing strftime.
        $bucket = match (true) {
            ! $byMonth => 'DATE(created_at)',
            DB::getDriverName() === 'mysql' => "DATE_FORMAT(created_at, '%Y-%m-01')",
            default => "strftime('%Y-%m-01', created_at)",
        };

        $rows = $scope()
            ->where('created_at', '>=', $start->utc())
            ->where('created_at', '<=', $end->utc())
            ->selectRaw($bucket.' as bucket, COUNT(*) as orders, SUM(total_price) as revenue')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $points = collect();
        $cursor = $byMonth ? $start->startOfMonth() : $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $row = $rows->get($cursor->format($byMonth ? 'Y-m-01' : 'Y-m-d'));

            $points->push([
                'label' => $cursor->format($byMonth ? 'M Y' : 'M j'),
                'short' => $cursor->format($byMonth ? 'M' : 'j'),
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ]);

            $cursor = $byMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        // A single point breaks the chart maths, so always give it two.
        if ($points->count() < 2) {
            $only = $points->first() ?? ['label' => $start->format('M j'), 'short' => $start->format('j'), 'orders' => 0, 'revenue' => 0.0];
            $points = collect([$only, $only]);
        }

        return $points;
    }

    /**
     * Percentage change between the first and second half of the range.
     */
    private function trendPercentage(Collection $series): float
    {
        $half = (int) floor($series->count() / 2);

        $previous = $series->take($half)->sum('orders');
        $current = $series->slice($half)->sum('orders');

        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return ($current - $previous) / $previous * 100;
    }

    /**
     * The best selling products within the range.
     */
    private function topProducts(callable $scope): Collection
    {
        return $scope()
            ->selectRaw('product_id, COUNT(*) as orders, SUM(total_price) as revenue')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->product?->name ?? 'Deleted product',
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ]);
    }
}
