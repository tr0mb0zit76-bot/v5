<?php

namespace Tests\Unit;

use JsonException;
use Tests\TestCase;

class PublicSiteRussianLocaleTest extends TestCase
{
    /**
     * Каноническая ru-локаль витрины хранится в resources, чтобы не терялась при чистке public/.
     *
     * @throws JsonException
     */
    public function test_canonical_russian_public_site_locale_exists_and_is_valid(): void
    {
        $path = resource_path('locales/public/ru.json');
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('home', $data);
        $this->assertArrayHasKey('welcome_title', $data);
        $this->assertNotSame('', trim((string) $data['welcome_title']));
    }
}
