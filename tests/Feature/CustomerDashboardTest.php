<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private ProductPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = $this->makeCustomer('casey@example.com');

        $product = Product::create([
            'name' => 'Basic Pendant',
            'description' => 'For the test suite.',
            'is_active' => true,
        ]);

        $this->price = ProductPrice::create([
            'product_id' => $product->id,
            'label' => 'MMR 1',
            'price' => 44.95,
            'user_commission' => 150,
            'admin_commission' => 100,
        ]);
    }

    private function makeCustomer(string $email): User
    {
        return User::create([
            'name' => 'Casey Customer',
            'email' => $email,
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);
    }

    private function makeOrder(User $user, string $status, float $user_commission, float $admin_commission = 999.99): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'product_id' => $this->price->product_id,
            'product_price_id' => $this->price->id,
            'full_name' => 'Casey Customer',
            'email' => $user->email,
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => 44.95,
            'user_commission_total' => $user_commission,
            'admin_commission_total' => $admin_commission,
            'status' => $status,
        ]);
    }

    public function test_it_totals_earned_pending_and_revenue(): void
    {
        $this->makeOrder($this->customer, 'sale', 150);
        $this->makeOrder($this->customer, 'active_account', 110);
        $this->makeOrder($this->customer, 'new', 90);
        $this->makeOrder($this->customer, 'cancelled', 70);

        $response = $this->actingAs($this->customer)->get(route('order.history'))->assertOk();

        $response->assertViewHas('earned', 260.0);
        $response->assertViewHas('pending', 90.0);
        // None of these orders are in "Paid" specifically (sale/active_account
        // only), so the paid-orders figure stays at zero.
        $response->assertViewHas('paidCommission', 0.0);
        $response->assertViewHas('revenue', 89.9);
        $response->assertViewHas('paidOrders', 2);
        $response->assertViewHas('newOrders', 1);
        $response->assertViewHas('cancelledOrders', 1);
        $response->assertViewHas('totalOrders', 4);
    }

    public function test_the_admin_commission_never_reaches_the_customer(): void
    {
        $this->makeOrder($this->customer, 'sale', 150, 4242.42);

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertOk()
            ->assertSee('$150.00')
            ->assertDontSee('4,242.42')
            ->assertDontSee('admin_commission');
    }

    public function test_it_only_counts_the_signed_in_customer(): void
    {
        $other = $this->makeCustomer('other@example.com');

        $this->makeOrder($this->customer, 'sale', 150);
        $this->makeOrder($other, 'sale', 999);

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertOk()
            ->assertViewHas('earned', 150.0)
            ->assertDontSee('999.00');
    }

    public function test_a_fresh_order_reads_as_new_to_the_customer(): void
    {
        $order = $this->makeOrder($this->customer, 'new', 90);

        $this->assertSame('New', $order->customerStatusLabel());

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertOk()
            ->assertSee('New')
            ->assertDontSee('Received');
    }

    public function test_the_customer_label_follows_the_admin(): void
    {
        $order = $this->makeOrder($this->customer, 'new', 90);

        $order->update(['status' => 'sale']);

        $this->assertSame('Sale', $order->fresh()->customerStatusLabel());

        $this->actingAs($this->customer)
            ->get(route('order.history'))
            ->assertOk()
            ->assertSee('Sale');
    }

    public function test_the_chart_covers_six_months_and_survives_an_empty_history(): void
    {
        $response = $this->actingAs($this->customer)->get(route('order.history'))->assertOk();

        $this->assertCount(6, $response->viewData('series'));

        $response->assertSee('No confirmed earnings yet')
            ->assertViewHas('earned', 0.0);
    }
}
