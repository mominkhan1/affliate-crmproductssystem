<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private User $other;

    private ProductPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@example.com', 'admin', 'Ada Admin');
        $this->customer = $this->makeUser('casey@example.com', 'user', 'Casey Customer');
        $this->other = $this->makeUser('dana@example.com', 'user', 'Dana Other');

        $this->price = ProductPrice::create([
            'product_id' => Product::create(['name' => 'Basic Pendant', 'description' => 'x', 'is_active' => true])->id,
            'label' => 'MMR 1',
            'price' => 44.95,
            'user_commission' => 150,
            'admin_commission' => 100,
        ]);
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

    private function makeOrder(User $user, string $status = 'new', float $total = 44.95): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'product_id' => $this->price->product_id,
            'product_price_id' => $this->price->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => $total,
            'user_commission_total' => 150,
            'admin_commission_total' => 100,
            'status' => $status,
        ]);
    }

    /**
     * Send an invoice as the customer and hand it back.
     */
    private function sendInvoice(Order $order, array $payload = []): Invoice
    {
        $this->actingAs($this->customer)->post(route('order.invoice.store', $order), $payload);

        return $order->fresh()->invoice;
    }

    public function test_a_customer_can_send_an_invoice_for_their_order(): void
    {
        $order = $this->makeOrder($this->customer);

        $this->actingAs($this->customer)
            ->post(route('order.invoice.store', $order), ['note' => 'Please settle this one.'])
            ->assertRedirect();

        $invoice = $order->fresh()->invoice;

        $this->assertNotNull($invoice);
        $this->assertSame('pending', $invoice->status);
        // The customer's commission, not the order's sale price.
        $this->assertSame('150.00', (string) $invoice->amount);
        $this->assertSame('Please settle this one.', $invoice->note);
        $this->assertSame('INV-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT), $invoice->number);
        $this->assertNotNull($invoice->status_changed_at);
    }

    public function test_any_order_can_be_invoiced_whatever_its_status(): void
    {
        foreach (['new', 'callback', 'sale', 'going_to_return', 'cancelled'] as $status) {
            $order = $this->makeOrder($this->customer, $status);

            $this->assertNotNull(
                $this->sendInvoice($order),
                'a '.$status.' order should be invoiceable',
            );
        }
    }

    public function test_the_amount_comes_from_the_order_not_the_request(): void
    {
        $order = $this->makeOrder($this->customer, 'sale', 44.95);

        $invoice = $this->sendInvoice($order, ['amount' => 99999, 'note' => 'nice try']);

        $this->assertSame('150.00', (string) $invoice->amount);
    }

    public function test_an_order_cannot_be_invoiced_twice(): void
    {
        $order = $this->makeOrder($this->customer);

        $this->sendInvoice($order);

        $this->actingAs($this->customer)
            ->post(route('order.invoice.store', $order))
            ->assertSessionHas('error');

        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_a_customer_cannot_invoice_someone_elses_order(): void
    {
        $order = $this->makeOrder($this->other);

        $this->actingAs($this->customer)
            ->post(route('order.invoice.store', $order))
            ->assertNotFound();

        $this->assertNull($order->fresh()->invoice);
    }

    public function test_the_admin_sees_the_invoice_on_the_user_profile(): void
    {
        $order = $this->makeOrder($this->customer);
        $invoice = $this->sendInvoice($order, ['note' => 'Hello there']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->customer))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Hello there')
            ->assertSee('Pending')
            ->assertSee('$150.00');
    }

    public function test_the_profile_shows_settings_alongside_the_invoices(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->customer))
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee('Invoices')
            ->assertSee('Edit account')
            ->assertSee($this->customer->email);
    }

    public function test_the_admin_can_move_an_invoice_to_paid(): void
    {
        $invoice = $this->sendInvoice($this->makeOrder($this->customer));

        $this->actingAs($this->admin)
            ->patchJson(route('admin.invoices.status', $invoice), ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('label', 'Paid');

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_a_rejection_can_carry_a_reply(): void
    {
        $invoice = $this->sendInvoice($this->makeOrder($this->customer));

        $this->actingAs($this->admin)->patchJson(route('admin.invoices.status', $invoice), [
            'status' => 'rejected',
            'admin_note' => 'Order was returned.',
        ])->assertOk();

        $this->assertSame('rejected', $invoice->fresh()->status);
        $this->assertSame('Order was returned.', $invoice->fresh()->admin_note);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $invoice = $this->sendInvoice($this->makeOrder($this->customer));

        $this->actingAs($this->admin)
            ->patchJson(route('admin.invoices.status', $invoice), ['status' => 'refunded'])
            ->assertStatus(422);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_a_customer_cannot_change_an_invoice_status(): void
    {
        $invoice = $this->sendInvoice($this->makeOrder($this->customer));

        $this->actingAs($this->customer)
            ->patch(route('admin.invoices.status', $invoice), ['status' => 'paid'])
            ->assertRedirect();

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_the_final_status_column_only_fills_in_once_the_order_is_resolved(): void
    {
        $order = $this->makeOrder($this->customer, 'post_date');

        // Still working its way through the pipeline, so the column reads blank.
        $this->actingAs($this->customer)
            ->get(route('order.list'))
            ->assertOk()
            ->assertSeeInOrder(['Final Status', '&mdash;'], false);

        $order->update(['status' => 'sale', 'sale_date' => now()]);

        // Reached an end state, so its own outcome now shows here.
        $this->actingAs($this->customer)
            ->get(route('order.list'))
            ->assertOk()
            ->assertSeeInOrder(['Final Status', 'Sale']);
    }

    public function test_the_order_page_offers_then_reports_the_invoice(): void
    {
        $order = $this->makeOrder($this->customer);

        $this->actingAs($this->customer)
            ->get(route('order.show', $order))
            ->assertOk()
            ->assertSee('Send Invoice')
            ->assertSee('$150.00');

        $invoice = $this->sendInvoice($order, ['note' => 'Thanks!']);

        $this->actingAs($this->admin)->patchJson(route('admin.invoices.status', $invoice), [
            'status' => 'rejected',
            'admin_note' => 'Duplicate of #99.',
        ]);

        $this->actingAs($this->customer)
            ->get(route('order.show', $order))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Rejected')
            ->assertSee('Thanks!')
            ->assertSee('Duplicate of #99.')
            // the form is gone once an invoice exists
            ->assertDontSee('Send Invoice');
    }

    public function test_the_status_change_is_stamped(): void
    {
        $invoice = $this->sendInvoice($this->makeOrder($this->customer));

        $this->travel(2)->hours();

        $this->actingAs($this->admin)->patchJson(route('admin.invoices.status', $invoice), ['status' => 'paid']);

        $this->assertTrue(
            $invoice->fresh()->status_changed_at->greaterThan($invoice->created_at),
            'the status change should be stamped when it happens',
        );
    }
}
