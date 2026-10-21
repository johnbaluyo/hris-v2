<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\AttendanceIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('atms_id')) {
            $this->merge(['atms_id' => AttendanceIdentity::normalize($this->input('atms_id'))]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $id = $employee instanceof User ? $employee->id : null;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:30'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'role' => ['sometimes', Rule::in(array_keys(config('hris.roles')))],
            'employee_type' => ['required', Rule::in(config('hris.employee_types'))],
            'division_id' => ['required_unless:employee_type,Applicant', 'nullable', 'integer', 'exists:divisions,id'],
            'position_id' => ['required_unless:employee_type,Applicant', 'nullable', 'integer', 'exists:positions,id'],
            'atms_id' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($id)],
            'date_hired' => ['required_unless:employee_type,Applicant', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'termination_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_hired'],
            'assume_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'workweek' => ['nullable', Rule::in([4, 5])],
            'step_increment' => ['nullable', 'integer', 'between:1,8'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'sex' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_person_no' => ['nullable', 'string', 'max:50'],
        ];
    }
}
