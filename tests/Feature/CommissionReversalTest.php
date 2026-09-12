<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionReversalTest extends TestCase
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
            'price' => 100,
            'user_commission' => 150,
            'admin_commission' => 100,
        ]);
    }

    private function makeOrder(string $status, float $commission): Order
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

    private function dashboard()
    {
        return $this->actingAs($this->customer)->get(route('order.history'));
    }

    public function test_only_a_finished_sale_pays_commission(): void
    {
        $this->makeOrder('new', 150);
        $this->makeOrder('callback', 150);
        $this->makeOrder('awaiting_payment', 150);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('confirmed', 0.0)
            ->assertViewHas('earned', 0.0)
            ->assertViewHas('pending', 450.0);
    }

    public function test_a_sale_and_an_active_account_both_pay(): void
    {
        $this->makeOrder('sale', 150);
        $this->makeOrder('active_account', 110);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('confirmed', 260.0)
            ->assertViewHas('earned', 260.0)
            ->assertViewHas('reversed', 0.0);
    }

    public function test_a_returning_order_is_taken_back_off_the_box(): void
    {
        $this->makeOrder('sale', 150);
        $this->makeOrder('sale', 110);
        $this->makeOrder('going_to_return', 60);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('confirmed', 260.0)
            ->assertViewHas('reversed', 60.0)
            ->assertViewHas('earned', 200.0)
            ->assertViewHas('returningOrders', 1)
            ->assertSee('Taken back');
    }

    public function test_the_box_can_go_negative(): void
    {
        $this->makeOrder('going_to_return', 150);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('earned', -150.0)
            ->assertSee('-$150.00');
    }

    public function test_earned_nets_the_return_out_while_pending_stays_apart(): void
    {
        $this->makeOrder('sale', 150);
        $this->makeOrder('new', 90);
        $this->makeOrder('going_to_return', 50);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('earned', 100.0)
            ->assertViewHas('pending', 90.0);
    }

    public function test_paid_commission_only_counts_the_paid_status(): void
    {
        $this->makeOrder('sale', 150);
        $this->makeOrder('paid', 200);
        $this->makeOrder('going_to_return', 50);

        $this->dashboard()
            ->assertOk()
            ->assertViewHas('paidCommission', 200.0);
    }

    public function test_other_lost_statuses_are_not_taken_back(): void
    {
        $this->makeOrder('sale', 150);
        $this->makeOrder('cancelled', 90);
        $this->makeOrder('card_declined', 90);
        $this->makeOrder('duplicate', 90);

        // Those never became a sale, so there is nothing to claw back.
        $this->dashboard()
            ->assertOk()
            ->assertViewHas('reversed', 0.0)
            ->assertViewHas('earned', 150.0);
    }

    public function test_moving_a_sale_to_returning_flips_the_total(): void
    {
        $order = $this->makeOrder('sale', 150);

        $this->dashboard()->assertOk()->assertViewHas('earned', 150.0);

        $order->update(['status' => 'going_to_return']);

        $this->dashboard()->assertOk()->assertViewHas('earned', -150.0);
    }

    public function test_the_deduction_is_shown_not_hidden(): void
    {
        $this->makeOrder('sale', 150);
        $this->makeOrder('going_to_return', 60);

        $this->dashboard()
            ->assertOk()
            ->assertSee('$150.00')   // what was confirmed
            ->assertSee('$60.00')    // what is coming back off
            ->assertSee('$90.00');   // what is left
    }
}
