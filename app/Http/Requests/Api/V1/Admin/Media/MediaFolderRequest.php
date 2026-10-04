<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use App\Models\MediaFolder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A folder's name: unique among folders.
 */
class MediaFolderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('name')))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $folder = $this->route('mediaFolder');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('media_folders', 'name')->ignore($folder instanceof MediaFolder ? $folder->id : null),
            ],
        ];
    }
}
