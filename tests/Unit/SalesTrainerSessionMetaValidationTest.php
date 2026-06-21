<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SalesTrainerDialogQuality;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class SalesTrainerSessionMetaValidationTest extends TestCase
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    private function trainerMetaRules(): array
    {
        return [
            'trainer_assistant_instructions' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'trainer_dialog_quality' => ['sometimes', 'nullable', Rule::enum(SalesTrainerDialogQuality::class)],
        ];
    }

    public function test_accepts_valid_dialog_quality_values(): void
    {
        foreach (SalesTrainerDialogQuality::cases() as $case) {
            $validator = Validator::make(
                ['trainer_dialog_quality' => $case->value],
                $this->trainerMetaRules(),
            );
            $this->assertTrue($validator->passes(), 'Failed for '.$case->value);
        }
    }

    public function test_rejects_invalid_dialog_quality(): void
    {
        $validator = Validator::make(
            ['trainer_dialog_quality' => 'not_a_quality'],
            $this->trainerMetaRules(),
        );

        $this->assertFalse($validator->passes());
    }

    public function test_accepts_null_dialog_quality(): void
    {
        $validator = Validator::make(
            ['trainer_dialog_quality' => null],
            $this->trainerMetaRules(),
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rejects_instructions_over_max_length(): void
    {
        $validator = Validator::make(
            ['trainer_assistant_instructions' => str_repeat('a', 8001)],
            $this->trainerMetaRules(),
        );

        $this->assertFalse($validator->passes());
    }
}
