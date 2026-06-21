<?php

namespace Tests\Unit\Support;

use App\Support\UserFacingDatabaseMessageResolver;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserFacingDatabaseMessageResolverTest extends TestCase
{
    #[Test]
    public function it_translates_foreign_key_violation_to_russian(): void
    {
        $exception = new QueryException(
            'mysql',
            'delete from `order_legs` where `order_id` = 2',
            [],
            new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete')
        );

        $message = (new UserFacingDatabaseMessageResolver)->resolve($exception);

        $this->assertNotNull($message);
        $this->assertStringContainsString('связаны другие данные', $message);
        $this->assertStringNotContainsString('SQLSTATE', $message);
    }

    #[Test]
    public function it_returns_null_for_unrelated_exceptions(): void
    {
        $message = (new UserFacingDatabaseMessageResolver)->resolve(new \RuntimeException('boom'));

        $this->assertNull($message);
    }
}
