<?php

namespace Tests\Feature;

use App\Models\Bill;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptUploadSecurityTest extends TestCase
{
    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->bill = Bill::factory()->create([
            'organizer_token' => hash('sha256', 'test-token'),
        ]);

        session(['organizer_token' => 'test-token']);
    }

    /** @test */
    public function valid_image_upload_succeeds()
    {
        $file = UploadedFile::fake()->image('receipt.jpg', 100, 100);

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertRedirect(route('bills.receipt', $this->bill));
        $response->assertSessionHas('success');

        $this->bill->refresh();
        $this->assertNotNull($this->bill->receipt_image_path);
        Storage::disk('local')->assertExists($this->bill->receipt_image_path);

        // Verify path is under receipts/ and not public
        $this->assertStringStartsWith('receipts/', $this->bill->receipt_image_path);

        // Verify filename is a UUID (36 chars) + extension
        $filename = basename($this->bill->receipt_image_path);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$/', $filename);
    }

    /** @test */
    public function php_file_is_rejected()
    {
        $file = UploadedFile::fake()->create('shell.php', 100, 'application/x-httpd-php');

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertSessionHasErrors('receipt');
        $this->assertStringContainsString('Only JPEG, PNG, and WebP images are allowed.', session('errors')->first('receipt'));
    }

    /** @test */
    public function exe_file_is_rejected()
    {
        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertSessionHasErrors('receipt');
    }

    /** @test */
    public function svg_xml_file_is_rejected()
    {
        $file = UploadedFile::fake()->create('image.svg', 100, 'image/svg+xml');

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertSessionHasErrors('receipt');
    }

    /** @test */
    public function oversized_file_is_rejected()
    {
        $file = UploadedFile::fake()->image('large.jpg')->size(6000); // 6 MB

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertSessionHasErrors('receipt');
        $this->assertStringContainsString('must not exceed 5 MB', session('errors')->first('receipt'));
    }

    /** @test */
    public function file_is_stored_outside_public_path()
    {
        $file = UploadedFile::fake()->image('receipt.png');

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertRedirect();
        $this->bill->refresh();

        $path = $this->bill->receipt_image_path;
        $this->assertStringStartsWith('receipts/', $path);
        $this->assertStringNotContainsString('public/', $path);
    }

    /** @test */
    public function random_filename_is_used()
    {
        $file = UploadedFile::fake()->image('original-name.jpg');

        $response = $this->post(route('bills.receipt.store', $this->bill), [
            'receipt' => $file,
        ]);

        $response->assertRedirect();
        $this->bill->refresh();

        $filename = basename($this->bill->receipt_image_path);
        $this->assertStringNotContainsString('original-name', $filename);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$/', $filename);
    }
}
