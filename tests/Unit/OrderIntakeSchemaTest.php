<?php

namespace Tests\Unit;

use App\Support\OrderIntakeSchema;
use Tests\TestCase;

class OrderIntakeSchemaTest extends TestCase
{
    public function test_parses_llm_json_with_markdown_fence(): void
    {
        $parsed = OrderIntakeSchema::parseLlmJson(<<<'JSON'
```json
{"customer":{"name":"ООО Эксвилл"},"confidence":0.9,"field_confidence":{}}
```
JSON);

        $this->assertSame('ООО Эксвилл', $parsed['customer']['name']);
        $this->assertSame(0.9, $parsed['confidence']);
    }

    public function test_builds_wizard_patch_from_extracted_payload(): void
    {
        $result = OrderIntakeSchema::toWizardPatch([
            'customer' => ['name' => 'ООО Эксвилл', 'inn' => '7701234567'],
            'route' => [
                'loading' => ['address' => 'Москва, склад 1', 'planned_date' => '2026-05-20'],
                'unloading' => ['address' => 'СПб, терминал', 'planned_date' => '2026-05-22'],
            ],
            'cargo' => ['name' => 'Оборудование', 'weight_kg' => 1200],
            'commercial' => ['customer_rate' => 85000, 'order_date' => '2026-05-15'],
            'notes' => 'Срочно',
            'confidence' => 0.88,
            'field_confidence' => [],
        ], [
            ['id' => 5, 'name' => 'ООО «Эксвилл Индастриал»', 'inn' => '7701234567', 'score' => 0.95],
        ]);

        $this->assertSame(5, $result['patch']['client_id']);
        $this->assertSame('2026-05-20', $result['patch']['loading_date']);
        $this->assertSame(85000.0, $result['patch']['financial_term']['client_price']);
        $this->assertCount(2, $result['patch']['route_points']);
        $this->assertNotEmpty($result['preview']);
    }
}
