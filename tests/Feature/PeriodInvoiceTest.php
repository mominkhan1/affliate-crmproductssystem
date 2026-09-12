<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private User $other;

    private ProductPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed Wednesday, so "this week" and "last week" never straddle the
        // moment the suite happens to run.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-09 12:00:00', 'UTC'));

        $this->partner = $this->makeUser('partner@example.com', 'Pat Partner');
        $this->other = $this->makeUser('other@example.com', 'Dana Other');

        $this->price = ProductPrice::create([
            'product_id' => Product::create(['name' => 'Basic Pendant', 'description' => 'x', 'is_active' => true])->id,
            'label' => 'MMR 1',
            'price' => 44.95,
            'user_commission' => 60,
            'admin_commission' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $email, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);
    }

    /**
     * An order for the partner, stamped into a chosen week.
     */
    private function makeOrder(string $status, string $when, float $commission = 60, ?User $user = null): Order
    {
        $order = Order::create([
            'user_id' => ($user ?? $this->partner)->id,
            'product_id' => $this->price->product_id,
            'product_price_id' => $this->price->id,
            'full_name' => 'Chris Customer',
            'email' => 'chris@example.com',
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => 150,
            'user_commission_total' => $commission,
            'admin_commission_total' => $commission / 2,
            'status' => $status,
        ]);

        // created_at is guarded, so the week is stamped on directly.
        $order->forceFill(['created_at' => $when, 'updated_at' => $when])->saveQuietly();

        return $order->fresh();
    }

    /**
     * Send a claim for the given orders, defaulting to everything billable.
     *
     * @param  array<int, int>|null  $orderIds
     */
    private function claim(?array $orderIds = null, array $payload = [])
    {
        $orderIds ??= $this->partner->orders()->billable()->pluck('id')->all();

        return $this->actingAs($this->partner)
            ->post(route('invoices.store'), ['orders' => $orderIds] + $payload);
    }

    public function test_only_earned_orders_can_be_claimed_for(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->makeOrder('active_account', '2026-09-10 10:00:00');
        $this->makeOrder('new', '2026-09-09 11:00:00');
        $this->makeOrder('cancelled', '2026-09-09 11:00:00');
        $this->makeOrder('going_to_return', '2026-09-09 11:00:00');

        $this->actingAs($this->partner)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 2)
            ->assertViewHas('earnings', 120.0);
    }

    public function test_the_date_range_narrows_the_orders_on_offer(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');   // this week
        $this->makeOrder('sale', '2026-09-10 10:00:00');   // this week
        $this->makeOrder('sale', '2026-09-02 10:00:00');   // last week

        $this->actingAs($this->partner)
            ->get(route('invoices.index', ['period' => 'this_week']))
            ->assertViewHas('earnings', 120.0);

        $this->actingAs($this->partner)
            ->get(route('invoices.index', ['period' => 'last_week']))
            ->assertViewHas('earnings', 60.0);
    }

    public function test_a_claim_bills_the_commission_not_the_order_value(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00', 60);
        $this->makeOrder('sale', '2026-09-10 10:00:00', 320);

        $this->claim()->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $this->assertSame('380.00', $invoice->amount);
        $this->assertSame(2, $invoice->orders()->count());
        $this->assertNull($invoice->order_id);
    }

    public function test_the_period_spans_the_orders_that_were_picked(): void
    {
        $this->makeOrder('sale', '2026-09-08 10:00:00');
        $this->makeOrder('sale', '2026-09-11 10:00:00');
        $this->makeOrder('sale', '2026-09-13 10:00:00');

        // Only the first two are ticked, so the period must stop at the 11th.
        $picked = $this->partner->orders()->billable()->orderBy('id')->limit(2)->pluck('id')->all();

        $this->claim($picked);

        $invoice = Invoice::firstOrFail();

        $this->assertSame('2026-09-08', $invoice->period_start->toDateString());
        $this->assertSame('2026-09-11', $invoice->period_end->toDateString());
        $this->assertTrue($invoice->coversPeriod());
    }

    public function test_the_commission_is_snapshotted_when_the_claim_is_raised(): void
    {
        $order = $this->makeOrder('sale', '2026-09-09 10:00:00', 60);

        $this->claim();

        // The order later goes back, taking its commission off the dashboard.
        $order->update(['status' => 'going_to_return', 'user_commission_total' => 0]);

        $invoice = Invoice::firstOrFail();

        // The pivot's own column isn't cast the way a model attribute is, so
        // its raw value differs by DB driver (a string on MySQL, a native
        // number on SQLite) — compare numerically instead of by type.
        $this->assertEquals(60.00, $invoice->orders()->first()->pivot->commission);
        $this->assertSame('60.00', $invoice->amount);
    }

    public function test_an_order_cannot_be_claimed_for_twice(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');

        $order = $this->partner->orders()->billable()->firstOrFail();

        $this->claim([$order->id])->assertRedirect();

        // The same id posted again is no longer billable, so nothing is raised.
        $this->claim([$order->id])->assertSessionHas('error');

        $this->assertSame(1, Invoice::count());
    }

    public function test_an_order_already_invoiced_on_its_own_is_left_out(): void
    {
        $order = $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->makeOrder('sale', '2026-09-10 10:00:00');

        $this->actingAs($this->partner)->post(route('order.invoice.store', $order));

        $this->actingAs($this->partner)
            ->get(route('invoices.index'))
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1);
    }

    public function test_ticking_nothing_raises_nothing(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');

        $this->claim([])->assertSessionHasErrors('orders');

        $this->assertSame(0, Invoice::count());
    }

    public function test_only_the_ticked_orders_are_billed(): void
    {
        $keep = $this->makeOrder('sale', '2026-09-09 10:00:00', 60);
        $this->makeOrder('sale', '2026-09-10 10:00:00', 320);

        $this->claim([$keep->id]);

        $invoice = Invoice::firstOrFail();

        $this->assertSame('60.00', $invoice->amount);
        $this->assertSame([$keep->id], $invoice->orders->pluck('id')->all());
    }

    public function test_a_posted_id_that_is_not_billable_is_ignored(): void
    {
        $good = $this->makeOrder('sale', '2026-09-09 10:00:00', 60);
        $cancelled = $this->makeOrder('cancelled', '2026-09-09 10:00:00', 60);
        $theirs = $this->makeOrder('sale', '2026-09-09 10:00:00', 60, $this->other);

        $this->claim([$good->id, $cancelled->id, $theirs->id]);

        $invoice = Invoice::firstOrFail();

        $this->assertSame('60.00', $invoice->amount);
        $this->assertSame([$good->id], $invoice->orders->pluck('id')->all());
    }

    public function test_a_claim_only_ever_covers_the_partners_own_orders(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->makeOrder('sale', '2026-09-09 10:00:00', 60, $this->other);

        $this->claim();

        $this->assertSame('60.00', Invoice::firstOrFail()->amount);
        $this->assertSame(1, Invoice::firstOrFail()->orders()->count());
    }

    public function test_the_amount_is_read_from_the_books_not_the_form(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00', 60);

        $this->claim(null, ['amount' => 99999]);

        $this->assertSame('60.00', Invoice::firstOrFail()->amount);
    }

    public function test_a_partner_cannot_read_someone_elses_invoice(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->other)
            ->get(route('invoices.show', $invoice))
            ->assertNotFound();
    }

    public function test_the_document_lists_every_order_it_bills(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->makeOrder('sale', '2026-09-10 10:00:00', 320);

        $this->claim(null, ['note' => 'Two good ones this week.']);

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->partner)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Sep 9 - Sep 10, 2026')
            ->assertSee('$380.00')
            ->assertSee('Two good ones this week.');
    }

    public function test_the_list_shows_what_is_still_unclaimed(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00', 60);
        $this->makeOrder('sale', '2026-09-10 10:00:00', 320);

        $this->actingAs($this->partner)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertViewHas('unbilled', 380.0)
            ->assertViewHas('unbilledCount', 2);

        $this->claim();

        $this->actingAs($this->partner)
            ->get(route('invoices.index'))
            ->assertViewHas('unbilled', 0.0)
            ->assertViewHas('unbilledCount', 0);
    }

    public function test_the_search_and_product_filters_narrow_the_list(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $picked = $this->makeOrder('sale', '2026-09-10 10:00:00');
        $picked->update(['full_name' => 'Wanda Whited']);

        $this->actingAs($this->partner)
            ->get(route('invoices.index', ['q' => 'Wanda']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1);

        $this->actingAs($this->partner)
            ->get(route('invoices.index', ['product_id' => $this->price->product_id]))
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 2);
    }

    public function test_a_shared_invoice_opens_without_signing_in(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->partner)
            ->post(route('invoices.share', $invoice))
            ->assertRedirect();

        $token = $invoice->fresh()->share_token;

        $this->assertNotNull($token);

        // No session at all: whoever holds the link can read it.
        $this->get(route('invoices.shared', $token))
            ->assertOk()
            ->assertSee($invoice->number);
    }

    public function test_closing_the_share_link_shuts_the_door(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->partner)->post(route('invoices.share', $invoice));
        $token = $invoice->fresh()->share_token;

        $this->actingAs($this->partner)->delete(route('invoices.unshare', $invoice));

        $this->get(route('invoices.shared', $token))->assertNotFound();
    }

    public function test_an_unshared_invoice_has_no_public_copy(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $this->assertNull(Invoice::firstOrFail()->share_token);

        $this->get(route('invoices.shared', 'made-up-token'))->assertNotFound();
    }

    public function test_only_the_owner_can_open_or_close_sharing(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->other)
            ->post(route('invoices.share', $invoice))
            ->assertNotFound();

        $this->assertNull($invoice->fresh()->share_token);
    }

    public function test_the_pdf_is_handed_over_as_a_download(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $invoice = Invoice::firstOrFail();

        $response = $this->actingAs($this->partner)
            ->get(route('invoices.download', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // An attachment saves a file rather than opening a print dialog.
        $this->assertStringContainsString(
            'attachment; filename='.$invoice->number.'.pdf',
            $response->headers->get('content-disposition'),
        );

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_partner_cannot_download_someone_elses_invoice(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $this->actingAs($this->other)
            ->get(route('invoices.download', Invoice::firstOrFail()))
            ->assertNotFound();
    }

    public function test_a_shared_invoice_can_be_downloaded_without_an_account(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->partner)->post(route('invoices.share', $invoice));
        $token = $invoice->fresh()->share_token;

        $this->get(route('invoices.shared.download', $token))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->partner)->delete(route('invoices.unshare', $invoice));

        $this->get(route('invoices.shared.download', $token))->assertNotFound();
    }

    public function test_the_invoice_page_closes_every_tag_it_opens(): void
    {
        $this->makeOrder('sale', '2026-09-09 10:00:00');
        $this->claim();

        $html = $this->actingAs($this->partner)
            ->get(route('invoices.show', Invoice::firstOrFail()))
            ->assertOk()
            ->getContent();

        // A link left open swallows the rest of the page, painting the
        // button's gradient behind everything after it. Neither tag can
        // self-close, so the counts must match exactly.
        foreach (['a', 'button'] as $tag) {
            $this->assertSame(
                preg_match_all('/<'.$tag.'[\s>]/i', $html),
                preg_match_all('/<\/'.$tag.'\s*>/i', $html),
                "Unbalanced <{$tag}> tags on the invoice page.",
            );
        }
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
    }
}
