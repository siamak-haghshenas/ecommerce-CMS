<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use App\Data\Media\MediaAssetData;
use App\Enums\MediaType;
use App\Models\MediaAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A file's title, alt text and (videos only) cover picture: an image of the library.
 */
class UpdateMediaAssetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'cover_id' => [
                'nullable',
                'integer',
                Rule::exists('media_assets', 'id')->where('type', MediaType::Image->value)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('cover_id') && $this->asset()->type !== MediaType::Video) {
                $validator->errors()->add('cover_id', __('admin.media.errors.cover_only_for_videos'));
            }
        }];
    }

    public function asset(): MediaAsset
    {
        /** @var MediaAsset */
        return $this->route('mediaAsset');
    }

    public function toData(): MediaAssetData
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->toString()) : null;

        return new MediaAssetData(
            title: $text('title'),
            alt: $text('alt'),
            coverId: $this->filled('cover_id') ? $this->integer('cover_id') : null,
        );
    }
}
