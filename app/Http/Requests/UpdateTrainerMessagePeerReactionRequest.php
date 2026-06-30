<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainerMessagePeerReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $feedbackTags = [
            'useful_next_step',
            'useful_objection',
            'useful_question',
            'useful_wording',
            'bad_too_generic',
            'bad_wrong_stage',
            'bad_missed_objection',
            'bad_not_actionable',
            'bad_too_long',
        ];

        return [
            'peer_reaction' => ['nullable', 'string', Rule::in(['positive', 'neutral', 'negative'])],
            'feedback_tags' => ['sometimes', 'array', 'max:5'],
            'feedback_tags.*' => ['string', Rule::in($feedbackTags)],
        ];
    }
}
