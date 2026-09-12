<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin Orders list shows a count per status for whatever the current
 * filter matches, in place of the old revenue/commission tiles.
 */
class AdminOrdersStatusBreakdownTest extends TestCase
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
            'price' => 44.95,
            'user_commission' => 150,
            'admin_commission' => 100,
        ]);
    }

    private function makeOrder(string $status, string $name): Order
    {
        return Order::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->price->product_id,
            'product_price_id' => $this->price->id,
            'full_name' => $name,
            'email' => 'c@example.com',
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => 44.95,
            'user_commission_total' => 150,
            'admin_commission_total' => 100,
            'status' => $status,
        ]);
    }

    public function test_status_counts_cover_every_status_present(): void
    {
        $this->makeOrder('new', 'Bryan K Gower');
        $this->makeOrder('new', 'Alice J Williams');
        $this->makeOrder('sale', 'William T Cogburn');
        $this->makeOrder('paid', 'Real Paid Lead');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertViewHas('totalOrders', 4);

        $statusCounts = $response->viewData('statusCounts');

        $this->assertSame(2, (int) $statusCounts->get('new'));
        $this->assertSame(1, (int) $statusCounts->get('sale'));
        $this->assertSame(1, (int) $statusCounts->get('paid'));
        $this->assertNull($statusCounts->get('cancelled'));
    }

    public function test_status_counts_follow_active_filters(): void
    {
        $this->makeOrder('sale', 'William T Cogburn')->update(['full_name' => 'Match Me']);
        $this->makeOrder('sale', 'No Match');
        $this->makeOrder('new', 'Also No Match');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['q' => 'Match Me']))
            ->assertOk()
            ->assertViewHas('totalOrders', 1);

        $statusCounts = $response->viewData('statusCounts');

        $this->assertSame(1, (int) $statusCounts->get('sale'));
        $this->assertNull($statusCounts->get('new'));
    }
}
