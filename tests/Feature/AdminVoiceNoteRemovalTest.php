<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Only an admin can remove a voice note once a customer has uploaded it —
 * the customer's own upload becomes a kept record, not something they can
 * take back.
 */
class AdminVoiceNoteRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

        $product = Product::create(['name' => 'Test Product', 'description' => 'x', 'is_active' => true]);
        $price = ProductPrice::create([
            'product_id' => $product->id,
            'label' => '1 bottle',
            'price' => 10,
            'user_commission' => 1,
            'admin_commission' => 1,
        ]);

        $this->order = Order::create([
            'user_id' => $this->customer->id,
            'product_id' => $product->id,
            'product_price_id' => $price->id,
            'full_name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'phone' => '5551234',
            'address' => '1 Test Street',
            'quantity' => 1,
            'total_price' => 10,
            'user_commission_total' => 1,
            'admin_commission_total' => 1,
            'status' => 'new',
        ]);
    }

    private function upload(Order $order, string $name = 'first.mp3'): void
    {
        $this->actingAs($this->customer)->post(route('order.voice-note.store', $order), [
            'voice_note' => UploadedFile::fake()->create($name, 32, 'audio/mpeg'),
        ]);
    }

    public function test_an_admin_can_remove_a_voice_note(): void
    {
        $this->upload($this->order);
        $note = $this->order->voiceNotes()->first();

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.voice-note.destroy', [$this->order, $note]))
            ->assertRedirect(route('admin.orders.show', $this->order));

        $this->assertSame(0, $this->order->voiceNotes()->count());
        Storage::disk('public')->assertMissing($note->path);
    }

    public function test_removing_one_note_leaves_the_other_alone(): void
    {
        $this->upload($this->order, 'first.mp3');
        $first = $this->order->voiceNotes()->first();

        $this->upload($this->order, 'second.wav');
        $second = $this->order->voiceNotes()->first();

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.voice-note.destroy', [$this->order, $first]))
            ->assertRedirect(route('admin.orders.show', $this->order));

        $this->assertSame(1, $this->order->voiceNotes()->count());
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_a_note_from_another_order_cannot_be_deleted_through_this_one(): void
    {
        $otherOrder = Order::create([...$this->order->only([
            'user_id', 'product_id', 'product_price_id', 'full_name', 'email', 'phone',
            'address', 'quantity', 'total_price', 'user_commission_total', 'admin_commission_total', 'status',
        ])]);

        $this->upload($otherOrder);
        $note = $otherOrder->voiceNotes()->first();

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.voice-note.destroy', [$this->order, $note]))
            ->assertNotFound();

        Storage::disk('public')->assertExists($note->path);
    }

    public function test_removing_a_note_is_logged_on_the_order(): void
    {
        $this->upload($this->order, 'note.mp3');
        $note = $this->order->voiceNotes()->first();

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.voice-note.destroy', [$this->order, $note]));

        $this->assertTrue(
            $this->order->activities()->where('description', 'like', '%Voice note removed: note.mp3%')->exists(),
        );
    }
}
