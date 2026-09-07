<?php

namespace App\Http\Requests;

class CreateUnitRequest extends BaseFormRequest
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
        if ($this->has('unit_name')) {
            $this->merge([
                'slug' => $this->generateSlug($this->unit_name),
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
        return [
            'unit_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:units,slug',
            'short_code' => 'required|string|max:50|unique:units,short_code',
            'is_base_unit' => 'boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500'
        ];
    }
}
