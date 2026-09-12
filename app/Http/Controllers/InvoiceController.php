<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Support\DateRange;
use App\Support\OrderFilters;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * The ranges a partner claims for. Weekly is the normal rhythm.
     */
    public const PERIODS = [
        'all' => 'All time',
        'this_week' => 'This week',
        'last_week' => 'Last week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'custom' => 'Custom range',
    ];

    /**
     * How the orders waiting to be claimed are ordered.
     */
    public const SORTS = [
        'oldest' => 'Oldest first',
        'newest' => 'Newest first',
        'commission_desc' => 'Highest commission',
        'commission_asc' => 'Lowest commission',
    ];

    /**
     * The invoicing page: what is waiting to be claimed, and what has been.
     *
     * Filtering and picking happen here rather than on a separate screen, so
     * a partner narrows to a week and ticks its orders in one place.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        // Only orders that earned and have not been claimed for can be ticked.
        $orders = $this->billable($request)
            ->tap(fn ($q) => OrderFilters::apply($q, $filters))
            ->with(['product', 'productPrice'])
            ->tap(fn ($q) => $this->sort($q, $filters['sort']))
            ->get();

        $invoices = $request->user()->invoices()
            ->with('order')
            ->withCount('orders')
            ->latest()
            ->paginate(10);

        $totals = $request->user()->invoices()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('frontend.invoices.index', [
            // Picking
            'orders' => $orders,
            'filters' => $filters,
            'periods' => self::PERIODS,
            'sorts' => self::SORTS,
            'statusMeta' => collect(Order::STATUS_META)
                ->only(Order::EARNING_STATUSES)
                ->all(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'rangeLabel' => DateRange::label($filters['period'], $filters['from'], $filters['to']),
            'earnings' => (float) $orders->sum('user_commission_total'),
            'orderValue' => (float) $orders->sum('total_price'),
            'activeFilterCount' => $this->activeFilterCount($filters),

            // Everything already claimed
            'invoices' => $invoices,
            'totals' => collect(Invoice::STATUS_META)
                ->map(fn ($meta, $key) => [
                    'label' => $meta['label'],
                    'tone' => $meta['tone'],
                    'help' => $meta['help'],
                    'count' => (int) ($totals->get($key)->count ?? 0),
                    'amount' => (float) ($totals->get($key)->amount ?? 0),
                ])
                ->values(),
            'unbilled' => (float) $this->billable($request)->sum('user_commission_total'),
            'unbilledCount' => $this->billable($request)->count(),
        ]);
    }

    /**
     * Raise the claim for the orders the partner ticked.
     *
     * The ids are only ever a selection: every one is looked up again against
     * this partner's billable orders, so a posted id cannot reach someone
     * else's work, an order already claimed for, or one that never earned.
     */
    public function storePeriod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'orders.required' => 'Tick at least one order to invoice.',
            'orders.min' => 'Tick at least one order to invoice.',
            'note.max' => 'Keep the note under 1000 characters.',
        ]);

        $orders = $this->billable($request)
            ->whereIn('id', $data['orders'])
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'Those orders are no longer available to claim for.');
        }

        $invoice = DB::transaction(function () use ($request, $orders, $data) {
            // The period is read off the orders themselves, so the document
            // can never claim a span its lines do not cover.
            $invoice = Invoice::create([
                'user_id' => $request->user()->id,
                'period_start' => $orders->min('created_at')->toDateString(),
                'period_end' => $orders->max('created_at')->toDateString(),
                'amount' => $orders->sum('user_commission_total'),
                'status' => 'pending',
                'note' => $data['note'] ?? null,
            ]);

            $invoice->orders()->attach(
                $orders->mapWithKeys(fn (Order $order) => [
                    $order->id => [
                        'commission' => $order->user_commission_total,
                        'order_value' => $order->total_price,
                    ],
                ])->all()
            );

            return $invoice;
        });

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'Invoice '.$invoice->number.' is ready.');
    }

    /**
     * The invoice itself, laid out as a document.
     */
    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        $invoice->load(['orders.product', 'order.product', 'user']);

        return view('frontend.invoices.show', ['invoice' => $invoice]);
    }

    /**
     * The invoice as a file, handed straight to the browser.
     *
     * Rendered server side rather than through the browser's print dialog,
     * so "Download PDF" saves a file instead of opening a printer.
     */
    public function download(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        return $this->pdf($invoice);
    }

    /**
     * The same file, for whoever holds the share link.
     */
    public function downloadShared(string $token): Response
    {
        return $this->pdf(Invoice::where('share_token', $token)->firstOrFail());
    }

    /**
     * Build the file.
     */
    private function pdf(Invoice $invoice): Response
    {
        $invoice->load(['orders.product', 'order.product', 'user']);

        return Pdf::loadView('frontend.invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4')
            // Embed only the glyphs used, rather than the whole font file.
            ->setOption('isFontSubsettingEnabled', true)
            ->download($invoice->number.'.pdf');
    }

    /**
     * Mint a link that opens this invoice without signing in.
     */
    public function share(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        if (! $invoice->share_token) {
            $invoice->forceFill(['share_token' => Str::random(48)])->save();
        }

        return back()->with('status', 'Share link ready. Anyone with it can view this invoice.');
    }

    /**
     * Close the link again.
     */
    public function unshare(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        $invoice->forceFill(['share_token' => null])->save();

        return back()->with('status', 'Share link closed. The old link no longer opens.');
    }

    /**
     * The shared copy, for whoever holds the link.
     */
    public function shared(string $token): View
    {
        $invoice = Invoice::where('share_token', $token)
            ->with(['orders.product', 'order.product', 'user'])
            ->firstOrFail();

        return view('frontend.invoices.shared', ['invoice' => $invoice]);
    }

    /**
     * Raise an invoice against one of the customer's own orders.
     *
     * The amount is read from the order rather than the request, so a posted
     * total can never decide what gets billed.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if ($order->invoice || $order->invoices()->exists()) {
            return back()->with('error', 'An invoice has already been sent for this order.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'note.max' => 'Keep the note under 1000 characters.',
        ]);

        DB::transaction(function () use ($request, $order, $data) {
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                // What the customer earns, not the order's sale price —
                // matches how a period invoice sums the same column.
                'amount' => $order->user_commission_total,
                'status' => 'pending',
                'note' => $data['note'] ?? null,
            ]);

            // Recorded on the pivot too, so one query answers whether an
            // order has been claimed for however the claim was raised.
            $invoice->orders()->attach($order->id, [
                'commission' => $order->user_commission_total,
                'order_value' => $order->total_price,
            ]);
        });

        return back()->with('status', 'Invoice sent. You will see the status here once it is reviewed.');
    }

    /**
     * This partner's orders that have earned and have not been claimed for.
     *
     * Stays a relation rather than a bare builder, so it is always scoped to
     * the signed in partner however it is chained onto.
     */
    private function billable(Request $request): HasMany
    {
        return $request->user()->orders()->billable();
    }

    /**
     * Read and sanitise the filters narrowing the orders to pick from.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $sort = $request->query('sort');

        $filters = OrderFilters::parse($request, withAccounts: false) + [
            'sort' => array_key_exists((string) $sort, self::SORTS) ? $sort : 'oldest',
        ];

        // A period this screen does not offer falls back to all time.
        if (! array_key_exists($filters['period'], self::PERIODS)) {
            $filters['period'] = 'all';
        }

        return $filters;
    }

    /**
     * Order the pickable list.
     */
    private function sort($query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->latest(),
            'commission_desc' => $query->orderByDesc('user_commission_total'),
            'commission_asc' => $query->orderBy('user_commission_total'),
            default => $query->oldest(),
        };
    }

    /**
     * How many filters are narrowing the list.
     *
     * @param  array<string, mixed>  $filters
     */
    private function activeFilterCount(array $filters): int
    {
        return OrderFilters::activeCount($filters)
            + ($filters['sort'] !== 'oldest' ? 1 : 0);
    }
}
