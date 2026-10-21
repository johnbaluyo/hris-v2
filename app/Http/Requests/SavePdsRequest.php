<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof User && Gate::allows('writePds', $employee);
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('section') === 'experience') {
            $data = $this->input('data', []);
            if (is_array($data) && in_array(strtolower(trim((string) ($data['date_to'] ?? ''))), ['present', '0000-00-02'], true)) {
                $data['original_date_to'] = $data['date_to'];
                $data['is_ongoing'] = true;
                $data['date_to'] = null;
                $this->merge(['data' => $data]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $section = config('pds.'.$this->route('section'));
        abort_unless(is_array($section), 404);
        $fields = $section['fields'];
        $keys = array_filter(array_keys($fields), fn ($key) => is_string($key) && ! str_contains($key, '.'));
        $rules = ['data' => ['required', 'array:'.implode(',', $keys)], 'file_id' => 'nullable|integer|exists:private_files,id', 'remove_file' => 'sometimes|boolean'];
        foreach ($fields as $key => $rule) {
            $rules['data.'.$key] = explode('|', $rule);
        }
        if ($this->route('section') === 'experience' && $this->boolean('data.is_ongoing')) {
            $rules['data.date_to'] = ['nullable', 'prohibited'];
        }

        return $rules;
    }
}
