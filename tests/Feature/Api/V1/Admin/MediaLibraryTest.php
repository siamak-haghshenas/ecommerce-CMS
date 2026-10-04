<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Enums\MediaUsageRole;
use App\Enums\UserRole;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\MediaUpload;
use App\Models\MediaUsage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function mediaUser(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * Uploads $contents the way the admin panel does: start, chunks in order, complete.
 */
function uploadMedia(User $user, string $name, string $contents, string $announcedType, ?int $folderId = null, int $chunkSize = 1024 * 1024): TestResponse
{
    $upload = actingAs($user)->postJson(route('api.v1.admin.media.uploads.store'), [
        'name' => $name, 'mime_type' => $announcedType, 'size' => strlen($contents), 'folder_id' => $folderId,
    ])->assertCreated()->json('data');

    foreach (str_split($contents, $chunkSize) as $index => $chunk) {
        actingAs($user)->post(route('api.v1.admin.media.uploads.chunk', $upload['id']), [
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
            'offset' => $index * $chunkSize,
        ], ['Accept' => 'application/json'])->assertOk();
    }

    return actingAs($user)->postJson(route('api.v1.admin.media.uploads.complete', $upload['id']));
}

function jpegContents(int $width, int $height): string
{
    // Keep the fake file alive while reading it: its temporary file goes away with the object.
    $image = UploadedFile::fake()->image('photo.jpg', $width, $height);

    return (string) file_get_contents($image->getRealPath());
}

/**
 * A 40×20 JPEG like a phone photo: EXIF orientation "rotate 90°" and a GPS block.
 */
function phonePhotoContents(): string
{
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // TIFF (little endian): IFD0 = Orientation 6 + a GPS IFD pointer; GPS IFD = GPSLatitudeRef "N".
    $tiff = 'II'.pack('v', 42).pack('V', 8)
        .pack('v', 2)
        .pack('vvV', 0x0112, 3, 1).pack('vv', 6, 0)
        .pack('vvVV', 0x8825, 4, 1, 38)
        .pack('V', 0)
        .pack('v', 1)
        .pack('vvV', 0x0001, 2, 2)."N\0\0\0"
        .pack('V', 0);
    $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
}

const MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

describe('access', function () {
    it('lets authors view and upload but not delete, move or manage folders', function () {
        $author = mediaUser(UserRole::Author);
        $asset = MediaAsset::factory()->create();

        actingAs($author)->getJson(route('api.v1.admin.media.index'))->assertOk();
        actingAs($author)->putJson(route('api.v1.admin.media.update', $asset), ['alt' => 'Red shirt'])->assertOk();
        actingAs($author)->deleteJson(route('api.v1.admin.media.destroy', $asset))->assertForbidden();
        actingAs($author)->postJson(route('api.v1.admin.media.move'), ['ids' => [$asset->id], 'folder_id' => null])->assertForbidden();
        actingAs($author)->postJson(route('api.v1.admin.media.folders.store'), ['name' => 'Banners'])->assertForbidden();
        actingAs(mediaUser(UserRole::Customer))->getJson(route('api.v1.admin.media.index'))->assertForbidden();
    });
});

describe('uploading', function () {
    it('uploads an image in chunks into a folder, with its versions and size', function () {
        $folder = MediaFolder::factory()->create();
        $admin = mediaUser();

        $file = uploadMedia($admin, 'Red shirt.jpg', jpegContents(400, 300), 'image/jpeg', $folder->id, chunkSize: 2048)
            ->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.width', 400)
            ->assertJsonPath('data.folder.id', $folder->id)
            ->assertJsonPath('data.uploaded_by.id', $admin->id)
            ->json('data');

        expect($file['urls']['thumb'])->toContain('conversions')
            ->and(MediaUpload::query()->count())->toBe(0)
            ->and(Activity::query()->where('event', 'media.uploaded')->sole()->properties['name'])->toBe('Red shirt.jpg');
    });

    it('scales very large images down to the maximum size', function () {
        uploadMedia(mediaUser(), 'big.jpg', jpegContents(3000, 1500), 'image/jpeg')
            ->assertCreated()
            ->assertJsonPath('data.width', 2560)
            ->assertJsonPath('data.height', 1280);
    });

    it('turns phone photos upright and removes their location and camera details', function () {
        $photo = phonePhotoContents();
        expect(exif_read_data('data://image/jpeg;base64,'.base64_encode($photo))['Orientation'] ?? null)->toBe(6);

        $file = uploadMedia(mediaUser(), 'phone.jpg', $photo, 'image/jpeg')
            ->assertCreated()
            ->assertJsonPath('data.width', 20)
            ->assertJsonPath('data.height', 40)
            ->json('data');

        $stored = (string) Storage::disk('public')->get(MediaAsset::query()->findOrFail($file['id'])->file()?->getPathRelativeToRoot());

        expect($stored)->not->toContain('Exif')
            ->and(@exif_read_data('data://image/jpeg;base64,'.base64_encode($stored)) ?: [])->not->toHaveKey('GPSLatitudeRef');
    });

    it('accepts PDFs and videos as they are, without versions', function (string $name, string $contents, string $type, string $expected) {
        $file = uploadMedia(mediaUser(), $name, $contents, $type)->assertCreated()->json('data');

        expect($file['type'])->toBe($expected)
            ->and($file['urls']['thumb'])->toBeNull()
            ->and($file['urls']['original'])->not->toBeNull();
    })->with([
        'pdf' => ['guide.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'application/pdf', 'document'],
        'mp4' => ['clip.mp4', MP4, 'video/mp4', 'video'],
    ]);

    it('checks the real content, not the name or announced type', function (string $contents) {
        uploadMedia(mediaUser(), 'innocent.png', $contents, 'image/png')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'media_type_not_allowed');

        expect(MediaAsset::query()->count())->toBe(0)
            ->and(MediaUpload::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles('media-uploads'))->toBe([]);
    })->with([
        'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
        'text' => ['just some text'],
    ]);

    it('refuses an unaccepted type or a file over its type limit before uploading', function (string $type, int $size) {
        actingAs(mediaUser())->postJson(route('api.v1.admin.media.uploads.store'), ['name' => 'x', 'mime_type' => $type, 'size' => $size])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    })->with([
        'svg' => ['image/svg+xml', 100],
        'image over 10 MB' => ['image/jpeg', 10 * 1024 * 1024 + 1],
        'video over 200 MB' => ['video/mp4', 200 * 1024 * 1024 + 1],
    ]);

    it('keeps chunks in order and within the announced size', function () {
        $admin = mediaUser();
        $upload = actingAs($admin)->postJson(route('api.v1.admin.media.uploads.store'), ['name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10])->json('data');
        $send = fn (string $bytes, int $offset) => actingAs($admin)->post(route('api.v1.admin.media.uploads.chunk', $upload['id']), [
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $bytes), 'offset' => $offset,
        ], ['Accept' => 'application/json']);

        $send('12345', 0)->assertOk()->assertJsonPath('data.received', 5);
        $send('12345', 0)->assertConflict()->assertJsonPath('code', 'media_chunk_out_of_order')->assertJsonPath('received', 5);
        $send('123456', 5)->assertUnprocessable()->assertJsonPath('code', 'media_chunk_too_large');
        actingAs($admin)->postJson(route('api.v1.admin.media.uploads.complete', $upload['id']))->assertUnprocessable()->assertJsonPath('code', 'media_upload_incomplete');
    });

    it('keeps uploads private to the staff member who started them', function () {
        $upload = actingAs(mediaUser())->postJson(route('api.v1.admin.media.uploads.store'), ['name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10])->json('data');

        actingAs(mediaUser())->postJson(route('api.v1.admin.media.uploads.complete', $upload['id']))->assertNotFound();
    });

    it('removes unfinished uploads and their files once expired', function () {
        $upload = actingAs(mediaUser())->postJson(route('api.v1.admin.media.uploads.store'), ['name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10])->json('data');
        $path = MediaUpload::query()->findOrFail($upload['id'])->path();

        travel(25)->hours();
        artisan('model:prune', ['--model' => [MediaUpload::class]])->assertSuccessful();

        expect(MediaUpload::query()->count())->toBe(0)
            ->and(Storage::disk('local')->exists($path))->toBeFalse();
    });
});

describe('browsing and editing', function () {
    it('filters by type, folder, use, search and the trash', function () {
        $folder = MediaFolder::factory()->create();
        $photo = MediaAsset::factory()->create(['name' => 'summer-banner.jpg', 'folder_id' => $folder->id]);
        $video = MediaAsset::factory()->video()->create(['alt' => 'Summer promo']);
        $used = MediaAsset::factory()->create();
        MediaUsage::query()->create(['media_asset_id' => $used->id, 'usable_type' => (new Product)->getMorphClass(), 'usable_id' => Product::factory()->create()->id, 'role' => MediaUsageRole::Gallery]);
        $trashed = MediaAsset::factory()->create();
        $trashed->delete();
        $ids = fn (array $query) => actingAs(mediaUser())->getJson(route('api.v1.admin.media.index', $query))->assertOk()->json('data.*.id');

        expect($ids(['type' => 'video']))->toBe([$video->id])
            ->and($ids(['folder' => $folder->id]))->toBe([$photo->id])
            ->and($ids(['folder' => 'none']))->toBe([$used->id, $video->id])
            ->and($ids(['used' => 1]))->toBe([$used->id])
            ->and($ids(['q' => 'summer', 'sort' => 'oldest']))->toBe([$photo->id, $video->id])
            ->and($ids(['trashed' => 1]))->toBe([$trashed->id]);
    });

    it('saves title, alt text and a video cover picture (an image only)', function () {
        $video = MediaAsset::factory()->video()->create();
        $cover = MediaAsset::factory()->create();
        $pdf = MediaAsset::factory()->document()->create();
        $admin = mediaUser();

        actingAs($admin)->putJson(route('api.v1.admin.media.update', $video), ['title' => 'Promo', 'alt' => 'Our summer promo', 'cover_id' => $cover->id])
            ->assertOk()
            ->assertJsonPath('data.cover.id', $cover->id);
        actingAs($admin)->putJson(route('api.v1.admin.media.update', $video), ['cover_id' => $pdf->id])->assertJsonValidationErrors(['cover_id']);
        actingAs($admin)->putJson(route('api.v1.admin.media.update', $cover), ['cover_id' => $cover->id])->assertJsonValidationErrors(['cover_id']);

        expect(Activity::query()->where('event', 'media.updated')->sole()->properties['fields'])->toEqualCanonicalizing(['title', 'alt', 'cover_id']);
    });

    it('lists where a file is used', function () {
        $asset = MediaAsset::factory()->create();
        $product = Product::factory()->create(['name' => 'Cotton shirt']);
        MediaUsage::query()->create(['media_asset_id' => $asset->id, 'usable_type' => $product->getMorphClass(), 'usable_id' => $product->id, 'role' => MediaUsageRole::Gallery]);

        actingAs(mediaUser())->getJson(route('api.v1.admin.media.show', $asset))
            ->assertOk()
            ->assertJsonPath('data.usages', [['type' => 'product', 'id' => $product->id, 'name' => 'Cotton shirt', 'role' => 'gallery']]);
    });
});

describe('folders', function () {
    it('creates, renames and deletes folders; deleting keeps the files in no folder', function () {
        $admin = mediaUser();
        $folder = actingAs($admin)->postJson(route('api.v1.admin.media.folders.store'), ['name' => '  Banners  '])->assertCreated()->assertJsonPath('data.name', 'Banners')->json('data');
        actingAs($admin)->postJson(route('api.v1.admin.media.folders.store'), ['name' => 'Banners'])->assertJsonValidationErrors(['name']);
        actingAs($admin)->putJson(route('api.v1.admin.media.folders.update', $folder['id']), ['name' => 'Home banners'])->assertJsonPath('data.name', 'Home banners');

        $file = MediaAsset::factory()->create();
        actingAs($admin)->postJson(route('api.v1.admin.media.move'), ['ids' => [$file->id], 'folder_id' => $folder['id']])->assertJsonPath('data.moved', 1);
        actingAs($admin)->getJson(route('api.v1.admin.media.folders.index'))->assertJsonPath('data.0.files_count', 1);

        actingAs($admin)->deleteJson(route('api.v1.admin.media.folders.destroy', $folder['id']))->assertNoContent();

        expect($file->fresh()->folder_id)->toBeNull()
            ->and(Activity::query()->pluck('event')->all())->toBe(['media_folder.created', 'media_folder.renamed', 'media.moved', 'media_folder.deleted']);
    });
});

describe('deleting', function () {
    it('refuses to delete a file in use, including a video cover', function () {
        $used = MediaAsset::factory()->create();
        MediaUsage::query()->create(['media_asset_id' => $used->id, 'usable_type' => (new Product)->getMorphClass(), 'usable_id' => Product::factory()->create()->id, 'role' => MediaUsageRole::Gallery]);
        $cover = MediaAsset::factory()->create();
        MediaAsset::factory()->video()->create(['cover_id' => $cover->id]);

        foreach ([$used, $cover] as $asset) {
            actingAs(mediaUser())->deleteJson(route('api.v1.admin.media.destroy', $asset))
                ->assertConflict()
                ->assertJsonPath('code', 'media_in_use');
        }
    });

    it('moves files to the trash, restores them, and empties the trash after 30 days', function () {
        $admin = mediaUser();
        $file = uploadMedia($admin, 'old.pdf', "%PDF-1.4\n%%EOF", 'application/pdf')->json('data');

        actingAs($admin)->deleteJson(route('api.v1.admin.media.destroy', $file['id']))->assertNoContent();
        actingAs($admin)->getJson(route('api.v1.admin.media.show', $file['id']))->assertOk()->assertJsonPath('data.deleted_at', fn ($value) => $value !== null);
        actingAs($admin)->postJson(route('api.v1.admin.media.restore', $file['id']))->assertOk()->assertJsonPath('data.deleted_at', null);

        actingAs($admin)->deleteJson(route('api.v1.admin.media.destroy', $file['id']))->assertNoContent();
        travel(29)->days();
        artisan('model:prune', ['--model' => [MediaAsset::class]]);
        expect(MediaAsset::withTrashed()->find($file['id']))->not->toBeNull();

        travel(2)->days();
        artisan('model:prune', ['--model' => [MediaAsset::class]]);
        expect(MediaAsset::withTrashed()->find($file['id']))->toBeNull()
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });
});

it('knows which content types it accepts', function () {
    expect(MediaType::fromMimeType('image/avif'))->toBe(MediaType::Image)
        ->and(MediaType::fromMimeType('video/webm'))->toBe(MediaType::Video)
        ->and(MediaType::fromMimeType('image/svg+xml'))->toBeNull();
});
