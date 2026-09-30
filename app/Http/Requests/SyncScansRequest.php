<?php

namespace App\Http\Requests;

use App\Enums\CheckinDecision;
use App\Enums\CheckinDirection;
use App\Enums\HandoutDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A batch of scans from a volunteer's offline queue. The volunteer session middleware guards the route. */
class SyncScansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'scans' => ['required', 'array', 'max:500'],
            'scans.*.client_id' => ['required', 'uuid'],
            'scans.*.token' => ['required', 'string', 'max:64'],
            'scans.*.gate_id' => ['nullable', 'integer'],
            // Goodies-counter scans ride the same queue; they carry kind=goodies and no direction.
            'scans.*.kind' => ['nullable', 'in:checkin,goodies'],
            'scans.*.direction' => ['required_unless:scans.*.kind,goodies', 'nullable', Rule::enum(CheckinDirection::class)->only([CheckinDirection::In, CheckinDirection::Out])],
            'scans.*.scanned_at' => ['required', 'date'],
            // Set when the phone warned ("already inside", "already collected") and the volunteer chose.
            'scans.*.decision' => ['nullable', Rule::in([...array_column(CheckinDecision::cases(), 'value'), ...array_column(HandoutDecision::cases(), 'value')])],
        ];
    }
}
