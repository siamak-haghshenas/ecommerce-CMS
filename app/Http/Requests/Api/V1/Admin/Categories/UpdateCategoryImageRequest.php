<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

class UpdateCategoryImageRequest extends FormRequest
{
    /** Largest accepted upload, in kilobytes. */
    public const int MAX_KILOBYTES = 5120;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
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
