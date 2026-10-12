<?php

namespace App\Http\Requests;

class UpdateMeasurementUnitRequest extends BaseFormRequest
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
        $id = $this->route('measurement_unit');

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:measurement_units,slug,' . $id,
            'short_code' => 'sometimes|string|max:50|unique:measurement_units,short_code,' . $id,
            'type' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
            'description' => 'nullable|string'
        ];
    }
}
