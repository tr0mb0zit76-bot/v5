<?php

namespace App\Http\Requests;

use App\Models\Lead;

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
}
