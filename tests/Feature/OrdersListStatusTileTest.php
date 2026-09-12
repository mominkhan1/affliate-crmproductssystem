<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The All Orders page used to total commission under a "Commission" tile.
 * It now shows a count per status for whatever the current filter matches,
 * the same "By status" tile the admin orders list already has.
 */
class OrdersListStatusTileTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private ProductPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);

        $this->price = ProductPrice::create([
            'product_id' => Product::create(['name' => 'Basic Pendant', 'description' => 'x', 'is_active' => true])->id,
            'label' => 'MMR 1',
            'price' => 34.95,
            'user_commission' => 10,
            'admin_commission' => 5,
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
            'total_price' => 34.95,
            'user_commission_total' => 10,
            'admin_commission_total' => 5,
            'status' => $status,
        ]);
    }

    public function test_status_counts_cover_every_status_present(): void
    {
        $this->makeOrder('duplicate', 'Linda Morgan');
        $this->makeOrder('callback', 'James Carter');
        $this->makeOrder('sale', 'Beverly Fentress');

        $response = $this->actingAs($this->customer)
            ->get(route('order.list'))
            ->assertOk()
            ->assertViewHas('totalOrders', 3);

        $statusCounts = $response->viewData('statusCounts');

        $this->assertSame(1, (int) $statusCounts->get('duplicate'));
        $this->assertSame(1, (int) $statusCounts->get('callback'));
        $this->assertSame(1, (int) $statusCounts->get('sale'));
        $this->assertNull($statusCounts->get('cancelled'));
    }

    public function test_a_customer_only_sees_their_own_statuses(): void
    {
        $this->makeOrder('sale', 'Beverly Fentress');
        $this->makeOrder('going_to_return', 'Jonas Hardy');

        $response = $this->actingAs($this->customer)
            ->get(route('order.list'))
            ->assertOk()
            ->assertSee('Sale')
            ->assertSee('Chargeback');

        $statusCounts = $response->viewData('statusCounts');

        $this->assertSame(1, (int) $statusCounts->get('sale'));
        $this->assertSame(1, (int) $statusCounts->get('going_to_return'));
    }

    public function test_the_tile_follows_the_active_filters(): void
    {
        $this->makeOrder('sale', 'Beverly Fentress');
        $this->makeOrder('sale', 'Mary Carroll');

        $response = $this->actingAs($this->customer)
            ->get(route('order.list', ['q' => 'Beverly']))
            ->assertOk()
            ->assertViewHas('totalOrders', 1);

        $statusCounts = $response->viewData('statusCounts');

        $this->assertSame(1, (int) $statusCounts->get('sale'));
    }
}
