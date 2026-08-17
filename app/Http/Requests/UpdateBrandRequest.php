<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    
    protected function prepareForValidation()
    {
        if ($this->has('name')) {
            $this->merge([
                'slug' => $this->generateSlug($this->name),
            ]);
        }
    }

    /**
     * Str::slug() strips non-Latin scripts (Tamil, Sinhala, ...) down to an
     * empty string, so it can't be used alone here — fall back to a
     * unicode-safe slug that keeps the original text's letters/numbers.
     */
    private function generateSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        if ($slug !== '') {
            return $slug;
        }

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name)), '-');
    }

    public function rules(): array
    {
         $id = $this->route('brand');

        return [
            'name' => 'sometimes|string|max:255|unique:brands,name,' . $id,
            'slug' => 'nullable|string|max:255|unique:brands,slug,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();

        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return [
                'field' => $field,
                'messages' => $messages,
            ];
        })->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
