<?php

namespace Tests\Unit;

use App\Support\OrderDeleteAuthorization;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderDeleteAuthorizationTest extends TestCase
{
    public static function deleteMatrixProvider(): array
    {
        return [
            'admin documents' => ['admin', 1, 99, 'documents', 'documents', true],
            'admin disruption' => ['admin', 1, 99, 'disruption', 'in_progress', true],
            'supervisor new any manager' => ['supervisor', 10, 99, null, 'new', true],
            'supervisor in_progress' => ['supervisor', 10, 99, null, 'in_progress', true],
            'supervisor documents' => ['supervisor', 10, 99, null, 'documents', false],
            'manager own new' => ['manager', 5, 5, null, 'new', true],
            'manager other new' => ['manager', 5, 6, null, 'new', false],
            'manager own in_progress' => ['manager', 5, 5, null, 'in_progress', false],
            'manager own manual documents' => ['manager', 5, 5, 'documents', 'in_progress', false],
            'guest' => [null, null, 5, null, 'new', false],
        ];
    }

    #[DataProvider('deleteMatrixProvider')]
    public function test_user_may_delete_matrix(
        ?string $roleName,
        ?int $userId,
        int $orderManagerId,
        ?string $manualStatus,
        ?string $systemStatus,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            OrderDeleteAuthorization::userMayDelete($roleName, $userId, $orderManagerId, $manualStatus, $systemStatus)
        );
    }

    public function test_manual_status_overrides_system_for_effective_status(): void
    {
        $this->assertSame(
            false,
            OrderDeleteAuthorization::userMayDelete('supervisor', 1, 1, 'documents', 'in_progress')
        );
    }
}
