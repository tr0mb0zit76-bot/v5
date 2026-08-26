<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleTableColumns;
use App\Support\TableColumnsPreset;
use PHPUnit\Framework\TestCase;

class TableColumnsPresetTest extends TestCase
{
    public function test_union_presets_by_col_id_allows_column_when_any_role_allows_it(): void
    {
        $merged = TableColumnsPreset::unionPresetsByColId([
            [
                ['colId' => 'id', 'hide' => false, 'width' => 56, 'order' => 0],
                ['colId' => 'track_number_customer', 'hide' => true, 'width' => 160, 'order' => 10],
            ],
            [
                ['colId' => 'id', 'hide' => false, 'width' => 56, 'order' => 0],
                ['colId' => 'track_number_customer', 'hide' => false, 'width' => 180, 'order' => 12],
            ],
        ]);

        $trackColumn = collect($merged)->firstWhere('colId', 'track_number_customer');

        $this->assertIsArray($trackColumn);
        $this->assertFalse($trackColumn['hide']);
        $this->assertSame(180, $trackColumn['width']);
    }

    public function test_union_presets_by_col_id_keeps_column_hidden_only_when_all_roles_hide_it(): void
    {
        $merged = TableColumnsPreset::unionPresetsByColId([
            [
                ['colId' => 'track_number_carrier', 'hide' => true, 'width' => 170, 'order' => 5],
            ],
            [
                ['colId' => 'track_number_carrier', 'hide' => true, 'width' => 170, 'order' => 6],
            ],
        ]);

        $trackColumn = collect($merged)->firstWhere('colId', 'track_number_carrier');

        $this->assertIsArray($trackColumn);
        $this->assertTrue($trackColumn['hide']);
    }

    public function test_payment_schedule_merge_shows_new_payment_form_column(): void
    {
        $presetWithoutForm = [
            ['colId' => 'id', 'hide' => false, 'width' => 56, 'order' => 0],
            ['colId' => 'payment_type', 'hide' => false, 'width' => 130, 'order' => 1],
        ];

        $merged = PaymentScheduleTableColumns::mergePresetWithCatalog($presetWithoutForm);
        $paymentForm = collect($merged)->firstWhere('colId', 'payment_form');

        $this->assertIsArray($paymentForm);
        $this->assertFalse($paymentForm['hide']);
        $this->assertContains('payment_form', PaymentScheduleTableColumns::fields());

        $paymentTypeIndex = collect($merged)->search(fn (array $column): bool => ($column['colId'] ?? null) === 'payment_type');
        $paymentFormIndex = collect($merged)->search(fn (array $column): bool => ($column['colId'] ?? null) === 'payment_form');

        $this->assertNotFalse($paymentTypeIndex);
        $this->assertNotFalse($paymentFormIndex);
        $this->assertSame($paymentTypeIndex + 1, $paymentFormIndex);
    }

    public function test_payment_schedule_merge_keeps_explicit_hidden_payment_form(): void
    {
        $preset = [
            ['colId' => 'payment_form', 'hide' => true, 'width' => 130, 'order' => 5],
        ];

        $merged = PaymentScheduleTableColumns::mergePresetWithCatalog($preset);
        $paymentForm = collect($merged)->firstWhere('colId', 'payment_form');

        $this->assertIsArray($paymentForm);
        $this->assertTrue($paymentForm['hide']);
    }
}
