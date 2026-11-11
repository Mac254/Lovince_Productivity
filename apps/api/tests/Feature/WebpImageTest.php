<?php

namespace Tests\Feature;

use App\Modules\Users\Models\User;
use App\Shared\Support\WebpImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebpImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** A PNG that declares its size but carries no pixel data: it passes `image` and `dimensions`, yet can't be decoded. */
    private function headerOnlyPng(int $width, int $height): UploadedFile
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));

        return UploadedFile::fake()->createWithContent('photo.png', $png);
    }

    /** A JPEG whose EXIF says "rotate 90° clockwise", as phone cameras write it. */
    private function rotatedJpeg(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $tiff = "II*\x00".pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, 6, 0).pack('V', 0);
        $app1 = "Exif\x00\x00".$tiff;
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);

        return UploadedFile::fake()->createWithContent('phone.jpg', $jpeg);
    }

    public function test_an_image_over_the_pixel_cap_is_rejected_without_decoding(): void
    {
        try {
            WebpImage::store($this->headerOnlyPng(6000, 5000), 'covers', 'big', 'local', 1600, 'image');
            $this->fail('A 30-megapixel image should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('image', $e->errors());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_an_image_that_cannot_be_decoded_is_rejected_not_stored_as_uploaded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/v1/profile/avatar', ['avatar' => $this->headerOnlyPng(400, 400)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_phone_photo_is_scaled_and_turned_upright(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Needs the PHP exif extension.');
        }

        $path = WebpImage::store($this->rotatedJpeg(2000, 1000), 'covers', 'phone', 'local', 500, 'image');

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([250, 500, IMAGETYPE_WEBP], [$width, $height, $type]);
    }
}
