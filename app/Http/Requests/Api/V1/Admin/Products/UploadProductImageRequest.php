<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

class UploadProductImageRequest extends FormRequest
{
    /** Largest accepted upload, in kilobytes. */
    public const int MAX_KILOBYTES = 8192;

    /** Images per product. */
    public const int MAX_IMAGES = 30;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $product = $this->route('product');

                    if ($product instanceof Product && $product->getMedia(Product::GALLERY_COLLECTION)->count() >= self::MAX_IMAGES) {
                        $fail(__('admin.products.errors.too_many_images', ['max' => self::MAX_IMAGES]));
                    }
                },
            ],
        ];
    }

    public function uploadedImage(): UploadedFile
    {
        $image = $this->file('image');

        if (! $image instanceof UploadedFile) {
            throw new LogicException('The image is validated as a single uploaded file.');
        }

        return $image;
    }
}
