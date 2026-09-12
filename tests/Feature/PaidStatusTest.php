<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paid is the final stage of a converted order — payment collected and
 * settled, one step past Sale or Active Account rather than a separate
 * outcome. It must earn commission exactly as they do, carry its own date,
 * and read the same word on both the partner and admin screens.
 */
class PaidStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private ProductPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Ada Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'admin',
        ]);

        $this->customer = User::create([
            'name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);

        $this->price = ProductPrice::create([
            'product_id' => Product::create(['name' => 'Basic Pendant', 'description' => 'x', 'is_active' => true])->id,
            'label' => 'MMR 1',
            'price' => 100,
            'user_commission' => 150,
            'admin_commission' => 100,
        ]);
    }

    private function makeOrder(string $status, float $commission = 150): Order
    {
        return Order::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->price->product_id,
            'product_price_id' => $this->price->id,
            'full_name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => 100,
            'user_commission_total' => $commission,
            'admin_commission_total' => 100,
            'status' => $status,
        ]);
    }

    public function test_paid_reads_the_same_word_on_both_sides(): void
    {
        $order = $this->makeOrder('paid');

        $this->assertSame('Paid', $order->statusLabel());
        $this->assertSame('Paid', $order->customerStatusLabel());
    }

    public function test_a_paid_order_earns_commission_like_a_sale(): void
    {
        $this->makeOrder('paid', 150);

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertOk()
            ->assertViewHas('earned', 150.0)
            ->assertViewHas('paidOrders', 1);
    }

    public function test_a_paid_order_counts_as_revenue_on_the_admin_dashboard(): void
    {
        $this->makeOrder('paid', 150);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('userCommission', 150.0)
            ->assertViewHas('revenue', 100.0);
    }

    public function test_a_paid_order_shows_on_the_all_orders_status_tile(): void
    {
        $this->makeOrder('paid', 150);

        $response = $this->actingAs($this->customer)
            ->get(route('order.list'))
            ->assertOk()
            ->assertSee('Paid');

        $this->assertSame(1, (int) $response->viewData('statusCounts')->get('paid'));
    }

    public function test_a_paid_order_can_still_be_charged_back(): void
    {
        $order = $this->makeOrder('paid', 150);

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertViewHas('earned', 150.0);

        // Same flip the dashboard already applies to a returning Sale: once
        // the order is no longer in an earning status, its commission comes
        // off the total rather than merely dropping out of the sum.
        $order->update(['status' => 'going_to_return']);

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertOk()
            ->assertViewHas('earned', -150.0)
            ->assertViewHas('reversed', 150.0);
    }

    public function test_marking_an_order_paid_requires_the_paid_date(): void
    {
        $order = $this->makeOrder('sale', 150);

        $this->actingAs($this->admin)
            ->from(route('admin.orders.show', $order))
            ->put(route('admin.orders.update', $order), [
                'status' => 'paid',
            ])
            ->assertSessionHasErrors('paid_date');

        $this->assertSame('sale', $order->fresh()->status);
    }

    public function test_marking_an_order_paid_with_a_date_succeeds(): void
    {
        $order = $this->makeOrder('sale', 150);

        $this->actingAs($this->admin)
            ->put(route('admin.orders.update', $order), [
                'status' => 'paid',
                'paid_date' => '2026-09-09',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertSame('2026-09-09', $order->paid_date->toDateString());
    }

    public function test_a_paid_order_can_be_billed_on_an_invoice(): void
    {
        $order = $this->makeOrder('paid', 150);

        $this->actingAs($this->customer)
            ->post(route('invoices.store'), ['orders' => [$order->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'user_id' => $this->customer->id,
            'amount' => 150,
        ]);
    }
}
