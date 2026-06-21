<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderInlineFieldCatalog;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderInlineFieldCatalogTest extends TestCase
{
    public function test_normalizes_numeric_rate_fields(): void
    {
        $payload = OrderInlineFieldCatalog::normalizePayload('customer_rate', '1234.567');

        $this->assertSame('customer_rate', $payload['field']);
        $this->assertSame(1234.57, $payload['value']);
    }

    public function test_rejects_unknown_field(): void
    {
        $user = new User;
        $user->forceFill(['id' => 1, 'is_admin' => true]);
        $order = new Order;
        $order->forceFill(['id' => 10, 'manager_id' => 1]);

        $this->expectException(ValidationException::class);

        OrderInlineFieldCatalog::validate($user, $order, 'status', 'closed');
    }

    public function test_manual_status_requires_supervisor_or_admin(): void
    {
        $user = new User;
        $user->forceFill(['id' => 2, 'is_admin' => false, 'role_id' => null]);
        $order = new Order;
        $order->forceFill(['id' => 10, 'manager_id' => 2]);

        $this->expectException(ValidationException::class);

        OrderInlineFieldCatalog::validate($user, $order, 'manual_status', 'closed');
    }

    #[DataProvider('allowedFieldProvider')]
    public function test_allowed_fields_include_inline_grid_fields(string $field): void
    {
        $this->assertContains($field, OrderInlineFieldCatalog::allowedFields());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedFieldProvider(): array
    {
        return [
            'customer_rate' => ['customer_rate'],
            'track_number_customer' => ['track_number_customer'],
            'manual_status' => ['manual_status'],
        ];
    }
}
