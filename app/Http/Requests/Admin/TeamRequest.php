<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the checkbox before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'user')],
            'name' => [
                'required', 'string', 'max:255',
                // Unique per customer, not globally — two accounts can each
                // have their own "Team A".
                Rule::unique('teams', 'name')
                    ->where('user_id', $this->input('user_id'))
                    ->ignore($team),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select which customer this team belongs to.',
            'name.unique' => 'This customer already has a team with that name.',
        ];
    }
}
