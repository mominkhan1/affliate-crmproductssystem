<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    private ProductPrice $pendant;

    private ProductPrice $bracelet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@example.com', 'admin', 'Ada Admin');
        $this->alice = $this->makeUser('alice@example.com', 'user', 'Alice Agent');
        $this->bob = $this->makeUser('bob@example.com', 'user', 'Bob Agent');

        $this->pendant = $this->makePrice('Basic Pendant', 'MMR 1');
        $this->bracelet = $this->makePrice('Alert Bracelet', 'MMR 2');
    }

    private function makeUser(string $email, string $role, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret1234'),
            'role' => $role,
        ]);
    }

    private function makePrice(string $product, string $label): ProductPrice
    {
        return ProductPrice::create([
            'product_id' => Product::create(['name' => $product, 'description' => 'x', 'is_active' => true])->id,
            'label' => $label,
            'price' => 100,
            'user_commission' => 10,
            'admin_commission' => 5,
        ]);
    }

    private function makeOrder(User $user, ProductPrice $price, string $status, float $total): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'product_id' => $price->product_id,
            'product_price_id' => $price->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => $total,
            'user_commission_total' => 10,
            'admin_commission_total' => 5,
            'status' => $status,
        ]);
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard', $query));
    }

    public function test_it_shows_every_order_without_filters(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->bob, $this->bracelet, 'sale', 200);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('revenue', 300.0)
            ->assertViewHas('activeFilterCount', 0);
    }

    public function test_it_filters_by_a_single_account(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->bob, $this->bracelet, 'sale', 200);

        $this->dashboard(['user_ids' => [$this->alice->id]])
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('revenue', 100.0)
            ->assertViewHas('activeFilterCount', 1);
    }

    public function test_it_filters_by_several_accounts_at_once(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->bob, $this->bracelet, 'sale', 200);
        $this->makeOrder($this->makeUser('carol@example.com', 'user', 'Carol'), $this->pendant, 'sale', 400);

        $this->dashboard(['user_ids' => [$this->alice->id, $this->bob->id]])
            ->assertOk()
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('revenue', 300.0);
    }

    public function test_it_filters_by_status(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->bob, $this->pendant, 'cancelled', 200);

        $this->dashboard(['status' => 'cancelled'])
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('chargebackOrders', 0)
            ->assertViewHas('revenue', 0.0);
    }

    public function test_cancelled_and_chargeback_are_counted_apart(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'cancelled', 100);
        $this->makeOrder($this->alice, $this->pendant, 'card_declined', 100);
        $this->makeOrder($this->bob, $this->pendant, 'going_to_return', 200);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('totalOrders', 3)
            ->assertViewHas('chargebackOrders', 1);
    }

    public function test_it_filters_by_search_term(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->bob, $this->bracelet, 'sale', 200);

        $this->dashboard(['q' => 'Alice'])
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('revenue', 100.0);
    }

    public function test_it_filters_by_product(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->bob, $this->bracelet, 'sale', 200);

        $this->dashboard(['product_id' => $this->bracelet->product_id])
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('revenue', 200.0);
    }

    public function test_filters_stack(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->alice, $this->bracelet, 'sale', 200);
        $this->makeOrder($this->bob, $this->pendant, 'sale', 400);

        $this->dashboard([
            'user_ids' => [$this->alice->id],
            'product_id' => $this->pendant->product_id,
            'status' => 'sale',
        ])
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('revenue', 100.0)
            ->assertViewHas('activeFilterCount', 3);
    }

    public function test_the_dashboard_and_the_orders_list_agree(): void
    {
        $this->makeOrder($this->alice, $this->pendant, 'sale', 100);
        $this->makeOrder($this->alice, $this->bracelet, 'cancelled', 250);
        $this->makeOrder($this->bob, $this->pendant, 'sale', 400);

        $query = ['user_ids' => [$this->alice->id]];

        $dashboard = $this->dashboard($query)->assertOk();
        $list = $this->actingAs($this->admin)->get(route('admin.orders.index', $query))->assertOk();

        $this->assertSame(
            $dashboard->viewData('totalOrders'),
            $list->viewData('totalOrders'),
            'the two screens must count the same orders for the same filters',
        );
    }

    public function test_the_filter_bar_renders_all_controls(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('name="period"', false)
            ->assertSee('name="product_id"', false)
            ->assertSee('name="user_ids[]"', false)
            ->assertSee('name="status"', false)
            ->assertSee('Alice Agent')
            ->assertSee('Bob Agent');
    }
}
