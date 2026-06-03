<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UploadSalesBookCoverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($file === null || ! $file->isValid()) {
                    return;
                }

                $size = @getimagesize($file->getRealPath());

                if (! is_array($size) || (int) ($size[1] ?? 0) === 0) {
                    return;
                }

                $ratio = (int) $size[0] / (int) $size[1];

                if ($ratio < 8 || $ratio > 10) {
                    $validator->errors()->add(
                        'file',
                        'Обложка должна быть узкой: примерное соотношение сторон от 8:1 до 10:1.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите изображение для обложки.',
            'file.image' => 'Обложка должна быть изображением JPG, PNG или WebP.',
            'file.max' => 'Размер обложки не должен превышать 5 МБ.',
        ];
    }
}
