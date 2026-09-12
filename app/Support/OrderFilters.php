<?php

namespace App\Support;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The filters shared by the orders list and the dashboard.
 *
 * Both screens narrow the same set of orders, so the parsing and the query
 * live here rather than in each controller, where they would drift apart.
 */
final class OrderFilters
{
    /**
     * Read and sanitise the filters from the query string.
     *
     * Customer screens pass $withAccounts false: a customer only ever sees
     * their own orders, so there is no account filter to honour.
     *
     * @return array<string, mixed>
     */
    public static function parse(Request $request, bool $withAccounts = true): array
    {
        $status = $request->query('status');
        $period = $request->query('period');

        return array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => in_array($status, Order::statuses(), true) ? $status : 'all',
            'period' => array_key_exists((string) $period, DateRange::PERIODS) ? $period : 'all',
            'from' => DateRange::parseDate($request->query('from')),
            'to' => DateRange::parseDate($request->query('to')),
            'product_id' => $request->query('product_id') ? (int) $request->query('product_id') : null,
            'team_id' => $request->query('team_id') ? (int) $request->query('team_id') : null,
            // Several accounts can be inspected side by side.
            'user_ids' => $withAccounts
                ? collect((array) $request->query('user_ids', []))
                    ->filter(fn ($id) => ctype_digit((string) $id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all()
                : null,
        ], fn ($value, $key) => $key !== 'user_ids' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Search terms a customer may use against their own orders.
     *
     * Narrower than the admin search on purpose: there is no point letting
     * someone search the account column when every row is already theirs.
     */
    private const CUSTOMER_SEARCH = ['full_name', 'address', 'phone'];

    /**
     * Narrow a query to the given filters.
     *
     * Ordering is left to the caller, since a list and a dashboard want
     * different things from the same rows.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function apply(Builder $query, array $filters): void
    {
        // Absent user_ids marks a customer screen, where the rows are already
        // scoped to one account.
        $isAdmin = array_key_exists('user_ids', $filters);

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';

            $query->where(function (Builder $q) use ($term, $filters, $isAdmin) {
                foreach ($isAdmin ? ['full_name', 'email', 'phone', 'address'] : self::CUSTOMER_SEARCH as $column) {
                    $q->orWhere($column, 'like', $term);
                }

                // Let a bare number match the order id too.
                if (ctype_digit($filters['q'])) {
                    $q->orWhere('id', (int) $filters['q']);
                }

                // Only an admin has other accounts to search across.
                if ($isAdmin) {
                    $q->orWhereHas('user', function (Builder $u) use ($term) {
                        $u->where('name', 'like', $term)->orWhere('email', 'like', $term);
                    });
                }
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['product_id'] ?? null) {
            $query->where('product_id', $filters['product_id']);
        }

        if ($filters['team_id'] ?? null) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['user_ids'])) {
            $query->whereIn('user_id', $filters['user_ids']);
        }

        [$from, $to] = self::range($filters);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }
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
    public static function range(array $filters): array
    {
        return DateRange::resolve($filters['period'], $filters['from'], $filters['to']);
    }

    /**
     * How many filters are currently narrowing the results.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function activeCount(array $filters): int
    {
        return collect([
            ($filters['q'] ?? '') !== '',
            ($filters['status'] ?? 'all') !== 'all',
            ($filters['period'] ?? 'all') !== 'all',
            ($filters['product_id'] ?? null) !== null,
            ($filters['team_id'] ?? null) !== null,
            ! empty($filters['user_ids']),
        ])->filter()->count();
    }
}
