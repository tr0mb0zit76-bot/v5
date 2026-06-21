<?php

namespace Tests\Feature;

use Tests\TestCase;

class BaselineSchemaMigrationTest extends TestCase
{
    public function test_mysql_baseline_schema_dump_is_present(): void
    {
        $path = database_path('schema/mysql-schema.sql');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);
        $this->assertStringContainsString('CREATE TABLE `orders`', $contents);
        $this->assertStringContainsString('CREATE TABLE `order_legs`', $contents);
    }
}
