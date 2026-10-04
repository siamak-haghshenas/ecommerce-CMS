<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One chunk of an upload (multipart `chunk`) and the byte position it starts at.
 */
class MediaChunkRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chunk' => ['required', 'file', 'max:'.(int) config('media.chunk_kb')],
            'offset' => ['required', 'integer', 'min:0'],
        ];
    }
}
