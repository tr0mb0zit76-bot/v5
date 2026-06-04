<?php

namespace App\Services;

use App\Enums\OrderIntakePhraseField;
use App\Models\OrderIntakePhraseLearning;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class OrderIntakeLearnedPhrasesService
{
    /**
     * @return array{ok: bool, id: int, message: string}
     */
    public function remember(User $user, string $sourcePhrase, string $canonicalValue, string $field): array
    {
        if (! Schema::hasTable('order_intake_phrase_learnings')) {
            return ['ok' => false, 'id' => 0, 'message' => 'Таблица обучения фраз недоступна.'];
        }

        $source = $this->normalizePhrase($sourcePhrase);
        $canonical = trim($canonicalValue);

        if ($source === '' || $canonical === '') {
            return ['ok' => false, 'id' => 0, 'message' => 'Укажите исходную фразу и её значение в CRM.'];
        }

        $fieldEnum = OrderIntakePhraseField::tryFrom($field) ?? OrderIntakePhraseField::General;

        $row = OrderIntakePhraseLearning::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'field' => $fieldEnum->value,
                'source_phrase' => $source,
            ],
            [
                'canonical_value' => $canonical,
            ],
        );

        return [
            'ok' => true,
            'id' => $row->id,
            'message' => 'Запомнил: «'.$source.'» → «'.$canonical.'» ('.$fieldEnum->label().'). Буду использовать при распознавании заявок.',
        ];
    }

    public function applyLearnedPhrases(User $user, string $text): string
    {
        if (! Schema::hasTable('order_intake_phrase_learnings')) {
            return $text;
        }

        $normalized = $text;

        foreach ($this->learningsForUser($user) as $learning) {
            $source = $learning->source_phrase;
            $canonical = $learning->canonical_value;

            if ($source === '' || $canonical === '') {
                continue;
            }

            $before = $normalized;
            $normalized = (string) preg_replace(
                '/'.preg_quote($source, '/').'/iu',
                $canonical,
                $normalized,
            );

            if ($before !== $normalized) {
                $learning->increment('use_count');
            }
        }

        return $normalized;
    }

    public function contextBlockForUser(User $user): string
    {
        if (! Schema::hasTable('order_intake_phrase_learnings')) {
            return '';
        }

        $rows = $this->learningsForUser($user);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['Выученные формулировки этого пользователя (из прошлых уточнений в чате):'];

        foreach ($rows as $row) {
            $lines[] = '- ['.$row->field->label().'] «'.$row->source_phrase.'» → «'.$row->canonical_value.'»';
        }

        return implode("\n", $lines);
    }

    /**
     * @return Collection<int, OrderIntakePhraseLearning>
     */
    private function learningsForUser(User $user)
    {
        return OrderIntakePhraseLearning::query()
            ->where('user_id', $user->id)
            ->orderByDesc('use_count')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    private function normalizePhrase(string $phrase): string
    {
        $trimmed = trim($phrase);

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }
}
