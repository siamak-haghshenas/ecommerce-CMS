<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Brands;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

class UpdateBrandLogoRequest extends FormRequest
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
        // No SVG: it can carry scripts. The `image` rule excludes it as well.
        return [
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    public function logo(): UploadedFile
    {
        $logo = $this->file('logo');

        if (! $logo instanceof UploadedFile) {
            throw new LogicException('The logo is validated as a single uploaded file.');
        }

        return $logo;
    }
}
