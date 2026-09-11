<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerOrderController;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceNoteUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->customer = User::create([
            'name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);

        $product = Product::create([
            'name' => 'Test Product',
            'description' => 'For the test suite.',
            'is_active' => true,
        ]);

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

    private function upload(UploadedFile $file)
    {
        return $this->actingAs($this->customer)
            ->post(route('order.voice-note.store', $this->order), ['voice_note' => $file]);
    }

    public function test_the_ceiling_is_one_hundred_megabytes(): void
    {
        $this->assertSame(102400, CustomerOrderController::MAX_UPLOAD_KB);
    }

    public function test_no_video_container_is_accepted(): void
    {
        foreach (['mp4', 'webm', 'mov', '3gp', '3gpp', 'avi', 'mkv'] as $extension) {
            $this->assertNotContains(
                $extension,
                CustomerOrderController::VOICE_EXTENSIONS,
                $extension.' is a video container and must not be allowed',
            );
        }
    }

    public function test_every_allowed_extension_uploads(): void
    {
        foreach (CustomerOrderController::VOICE_EXTENSIONS as $extension) {
            $file = UploadedFile::fake()->create("note.{$extension}", 64, 'audio/mpeg');

            $this->upload($file)->assertRedirect(route('order.show', $this->order));

            $latest = $this->order->voiceNotes()->first();

            $this->assertSame(
                $extension,
                pathinfo($latest->path, PATHINFO_EXTENSION),
                "the .{$extension} upload did not keep its extension",
            );
            Storage::disk('public')->assertExists($latest->path);
        }
    }

    public function test_a_video_upload_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'))
            ->assertSessionHasErrors('voice_note');

        $this->assertSame(0, $this->order->voiceNotes()->count());
    }

    public function test_a_video_renamed_to_an_audio_extension_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->create('sneaky.mp3', 64, 'video/mp4'))
            ->assertSessionHasErrors('voice_note');

        $this->assertSame(0, $this->order->voiceNotes()->count());
    }

    public function test_a_file_over_the_limit_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->create('huge.mp3', 102401, 'audio/mpeg'))
            ->assertSessionHasErrors('voice_note');

        $this->assertSame(0, $this->order->voiceNotes()->count());
    }

    public function test_the_limit_is_the_smaller_of_our_ceiling_and_php(): void
    {
        $toKb = fn (string $value) => match (strtolower(substr(trim($value), -1))) {
            'g' => (int) $value * 1024 * 1024,
            'm' => (int) $value * 1024,
            'k' => (int) $value,
            default => (int) ((int) $value / 1024),
        };

        $method = new \ReflectionMethod(CustomerOrderController::class, 'maxUploadKb');
        $method->setAccessible(true);

        $this->assertSame(
            min(array_filter([
                $toKb((string) ini_get('upload_max_filesize')),
                $toKb((string) ini_get('post_max_size')),
                CustomerOrderController::MAX_UPLOAD_KB,
            ])),
            $method->invoke(app(CustomerOrderController::class)),
            'the app should allow 100 MB unless PHP is configured lower',
        );
    }

    public function test_uploading_a_second_note_keeps_the_first(): void
    {
        $this->upload(UploadedFile::fake()->create('first.mp3', 32, 'audio/mpeg'));
        $first = $this->order->voiceNotes()->first()->path;

        $this->upload(UploadedFile::fake()->create('second.wav', 32, 'audio/wav'));

        $this->assertSame(2, $this->order->voiceNotes()->count());
        Storage::disk('public')->assertExists($first);
    }

    public function test_removing_one_note_leaves_the_other_alone(): void
    {
        $this->upload(UploadedFile::fake()->create('first.mp3', 32, 'audio/mpeg'));
        $first = $this->order->voiceNotes()->first();

        $this->upload(UploadedFile::fake()->create('second.wav', 32, 'audio/wav'));
        $second = $this->order->voiceNotes()->first();

        $this->actingAs($this->customer)
            ->delete(route('order.voice-note.destroy', [$this->order, $first]))
            ->assertRedirect(route('order.show', $this->order));

        $this->assertSame(1, $this->order->voiceNotes()->count());
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_another_customer_cannot_delete_a_note_from_this_order(): void
    {
        $this->upload(UploadedFile::fake()->create('first.mp3', 32, 'audio/mpeg'));
        $note = $this->order->voiceNotes()->first();

        $other = User::create([
            'name' => 'Other',
            'email' => 'other-delete@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);

        $this->actingAs($other)
            ->delete(route('order.voice-note.destroy', [$this->order, $note]))
            ->assertNotFound();

        Storage::disk('public')->assertExists($note->path);
    }

    public function test_the_uploader_gets_json_back(): void
    {
        $response = $this->actingAs($this->customer)->postJson(
            route('order.voice-note.store', $this->order),
            ['voice_note' => UploadedFile::fake()->create('note.m4a', 32, 'audio/mp4')],
        );

        $response->assertOk()
            ->assertJsonStructure(['message', 'name', 'url', 'added'])
            ->assertJsonPath('name', 'note.m4a');
    }

    public function test_json_validation_failures_come_back_as_json(): void
    {
        $this->actingAs($this->customer)
            ->postJson(route('order.voice-note.store', $this->order), [
                'voice_note' => UploadedFile::fake()->create('clip.mp4', 32, 'video/mp4'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('voice_note');
    }

    public function test_the_order_page_renders_the_uploader(): void
    {
        $this->actingAs($this->customer)
            ->get(route('order.show', $this->order))
            ->assertOk()
            ->assertSee('drop-zone')
            ->assertSee('progress-bar')
            ->assertSee('Audio only, up to')
            ->assertDontSee('video/*');
    }

    public function test_another_customer_cannot_upload_to_this_order(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'user',
        ]);

        $this->actingAs($other)
            ->post(route('order.voice-note.store', $this->order), [
                'voice_note' => UploadedFile::fake()->create('note.mp3', 32, 'audio/mpeg'),
            ])
            ->assertNotFound();
    }
}
