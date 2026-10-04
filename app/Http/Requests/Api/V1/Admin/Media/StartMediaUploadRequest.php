<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use App\Data\Media\MediaUploadData;
use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Announces a file before its chunks are sent. The announced type and size are checked here, so a
 * wrong file is refused before it is uploaded; the real content is checked again on completion.
 */
class StartMediaUploadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1'],
            'folder_id' => ['nullable', 'integer', Rule::exists('media_folders', 'id')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = MediaType::fromMimeType($this->string('mime_type')->toString());

            if ($type === null) {
                $validator->errors()->add('file', __('admin.errors.media_type_not_allowed'));
            } elseif ($this->integer('size') > $type->maxBytes()) {
                $validator->errors()->add('file', __('admin.errors.media_too_large', ['max' => (int) round($type->maxBytes() / 1024 / 1024)]));
            }
        }];
    }

    public function toData(): MediaUploadData
    {
        return new MediaUploadData(
            name: trim($this->string('name')->toString()),
            mimeType: $this->string('mime_type')->toString(),
            size: $this->integer('size'),
            folderId: $this->filled('folder_id') ? $this->integer('folder_id') : null,
        );
    }
}
