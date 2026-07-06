<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends StoreLeadRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        /** @var Lead|null $lead */
        $lead = $this->route('lead');

        if ($lead !== null && ! $this->filled('business_process_id') && $lead->business_process_id !== null) {
            $this->merge(['business_process_id' => $lead->business_process_id]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var Lead $lead */
        $lead = $this->route('lead');

        $unique = Rule::unique('leads', 'title')->ignore($lead->id);
        if (Schema::hasColumn('leads', 'deleted_at')) {
            $unique = $unique->whereNull('deleted_at');
        }

        $rules['title'] = ['required', 'string', 'max:255', $unique];

        return $rules;
    }
}
