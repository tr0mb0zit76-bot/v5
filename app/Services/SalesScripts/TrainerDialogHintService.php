<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptNode;
use App\Models\SalesScriptTrainerMessage;
use Illuminate\Support\Collection;

/**
 * Подбор узлов сценария по лексике последних реплик чата тренажёра (MySQL/Eloquent, без векторов).
 *
 * Расширение «прошлый опыт»: завести таблицу вида (version_id, profile_key, term, node_id, weight)
 * и в этом сервисе объединять score_lexicon + weight_из_опыта (обновление по завершённым сессиям / фидбеку).
 */
class TrainerDialogHintService
{
    /**
     * @var list<string>
     */
    private const STOPWORDS = [
        'этот', 'этого', 'этом', 'эта', 'это', 'что', 'как', 'все', 'всё', 'вас', 'вам', 'нас', 'нам',
        'они', 'был', 'была', 'будет', 'есть', 'или', 'для', 'при', 'про', 'над', 'под',
        'так', 'ещё', 'уже', 'там', 'тут', 'кто', 'где', 'когда', 'почему', 'который', 'которая',
    ];

    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $messages
     * @return list<array{node_id:int, client_key:?string, kind:string, excerpt:string, hint:?string, matched_terms:list<string>, score:int, source:string}>
     */
    public function contextualNodeHints(
        int $salesScriptVersionId,
        ?int $currentNodeId,
        Collection $messages,
        int $limit = 5,
    ): array {
        $corpus = $this->buildCorpusFromMessages($messages);
        $terms = $this->extractTerms($corpus);
        if ($terms === []) {
            return [];
        }

        $nearbyNodeIds = $this->nearbyNodeIds($salesScriptVersionId, $currentNodeId);
        if ($nearbyNodeIds === []) {
            return [];
        }

        /** @var Collection<int, SalesScriptNode> $nodes */
        $nodes = SalesScriptNode::query()
            ->where('sales_script_version_id', $salesScriptVersionId)
            ->whereIn('id', $nearbyNodeIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'client_key', 'kind', 'body', 'hint', 'sort_order']);

        $scored = [];
        foreach ($nodes as $node) {
            if ($currentNodeId !== null && (int) $node->id === (int) $currentNodeId) {
                continue;
            }
            $haystack = mb_strtolower(
                (string) $node->body.' '.(string) ($node->hint ?? '').' '.(string) ($node->client_key ?? ''),
                'UTF-8',
            );
            $matched = [];
            $score = 0;
            foreach ($terms as $term) {
                if ($term === '' || mb_strlen($term, 'UTF-8') < 3) {
                    continue;
                }
                if (mb_strpos($haystack, $term, 0, 'UTF-8') !== false) {
                    $matched[] = $term;
                    $score++;
                }
            }
            if ($score > 0) {
                $scored[] = [
                    'node_id' => (int) $node->id,
                    'client_key' => $node->client_key,
                    'kind' => $node->kind->value,
                    'excerpt' => $this->excerpt((string) $node->body, 200),
                    'hint' => $node->hint ? $this->excerpt((string) $node->hint, 240) : null,
                    'matched_terms' => array_values(array_unique($matched)),
                    'score' => $score,
                    'source' => 'chat_lexicon',
                    'why' => 'Совпало с ближайшим шагом сценария: '.implode(', ', array_values(array_unique($matched))),
                ];
            }
        }

        usort($scored, function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['node_id'] <=> $b['node_id'];
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * @return list<int>
     */
    private function nearbyNodeIds(int $salesScriptVersionId, ?int $currentNodeId): array
    {
        if ($currentNodeId === null) {
            return [];
        }

        /** @var SalesScriptNode|null $current */
        $current = SalesScriptNode::query()
            ->where('sales_script_version_id', $salesScriptVersionId)
            ->find($currentNodeId);

        if ($current === null) {
            return [];
        }

        $frontier = [$current->id];
        $seen = [(int) $current->id => true];
        $nearby = [];

        for ($depth = 0; $depth < 2; $depth++) {
            /** @var Collection<int, SalesScriptNode> $nodes */
            $nodes = SalesScriptNode::query()
                ->whereIn('id', $frontier)
                ->with('outgoingTransitions.toNode')
                ->get();

            $nextFrontier = [];
            foreach ($nodes as $node) {
                foreach ($node->outgoingTransitions as $transition) {
                    $toNode = $transition->toNode;
                    if (! $toNode instanceof SalesScriptNode) {
                        continue;
                    }

                    if ($toNode->sales_script_version_id !== $salesScriptVersionId) {
                        continue;
                    }

                    if ((int) $toNode->id === (int) $current->id || isset($seen[(int) $toNode->id])) {
                        continue;
                    }

                    if ((int) $toNode->sort_order > ((int) $current->sort_order + 30)) {
                        continue;
                    }

                    $seen[(int) $toNode->id] = true;
                    $nearby[] = (int) $toNode->id;
                    $nextFrontier[] = (int) $toNode->id;
                }
            }

            if ($nextFrontier === []) {
                break;
            }

            $frontier = $nextFrontier;
        }

        return array_values(array_unique($nearby));
    }

    /**
     * Краткая подсказка из узла входа (из сидов/редактора), пока чат пуст.
     *
     * @return array{node_id:int, client_key:?string, kind:string, excerpt:string, hint:?string, source:string}|null
     */
    public function entryNodePreview(int $salesScriptVersionId, ?string $entryNodeKey): ?array
    {
        if ($entryNodeKey === null || $entryNodeKey === '') {
            return null;
        }

        $entry = SalesScriptNode::query()
            ->where('sales_script_version_id', $salesScriptVersionId)
            ->where('client_key', $entryNodeKey)
            ->first();

        if (! $entry instanceof SalesScriptNode) {
            return null;
        }

        return $this->nodeToSidebarRow($entry, 'graph_entry');
    }

    /**
     * @return list<string>
     */
    public function extractTermsForTests(string $corpus): array
    {
        return $this->extractTerms($corpus);
    }

    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $messages
     */
    private function buildCorpusFromMessages(Collection $messages): string
    {
        $tail = $messages->sortBy('id')->values()->slice(-8);
        $parts = [];
        foreach ($tail as $message) {
            if ($message instanceof SalesScriptTrainerMessage) {
                $parts[] = (string) $message->content;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function extractTerms(string $corpus): array
    {
        $lower = mb_strtolower($corpus, 'UTF-8');
        $pieces = preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($pieces)) {
            return [];
        }

        $out = [];
        foreach ($pieces as $piece) {
            $t = (string) $piece;
            if (mb_strlen($t, 'UTF-8') < 4) {
                continue;
            }
            if (in_array($t, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $t;
        }

        $out = array_values(array_unique($out));

        return array_slice($out, 0, 18);
    }

    /**
     * @return array{node_id:int, client_key:?string, kind:string, excerpt:string, hint:?string, source:string}
     */
    private function nodeToSidebarRow(SalesScriptNode $node, string $source): array
    {
        return [
            'node_id' => (int) $node->id,
            'client_key' => $node->client_key,
            'kind' => $node->kind->value,
            'excerpt' => $this->excerpt((string) $node->body, 220),
            'hint' => $node->hint ? $this->excerpt((string) $node->hint, 220) : null,
            'source' => $source,
        ];
    }

    private function excerpt(string $text, int $maxLen): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($t, 'UTF-8') <= $maxLen) {
            return $t;
        }

        return rtrim(mb_substr($t, 0, $maxLen - 1, 'UTF-8')).'…';
    }
}
