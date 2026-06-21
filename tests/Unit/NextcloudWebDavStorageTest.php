<?php

namespace Tests\Unit;

use App\Services\NextcloudWebDavStorage;
use ReflectionMethod;
use Tests\TestCase;

class NextcloudWebDavStorageTest extends TestCase
{
    public function test_connection_error_message_explains_ssl_hostname_mismatch(): void
    {
        $storage = new NextcloudWebDavStorage(
            baseUrl: 'https://nc.avtoaliyans.ru',
            username: 'crm-bot',
            password: 'secret',
            webdavRoot: '/remote.php/dav/files/crm-bot/CRM',
        );

        $method = new ReflectionMethod(NextcloudWebDavStorage::class, 'connectionErrorMessage');
        $message = $method->invoke(
            $storage,
            new \Exception(
                "cURL error 60: SSL: no alternative certificate subject name matches target host name 'nc.avtoaliyans.ru'"
            ),
        );

        $this->assertStringContainsString('nc.avtoaliyans.ru', $message);
        $this->assertStringContainsString('SSL', $message);
        $this->assertStringContainsString('NEXTCLOUD_BASE_URL', $message);
    }

    public function test_root_directory_error_message_for_404_mentions_docker_and_proxy(): void
    {
        $storage = new NextcloudWebDavStorage(
            baseUrl: 'https://nc.avtoaliyans.ru',
            username: 'crm-bot',
            password: 'secret',
            webdavRoot: '/remote.php/dav/files/crm-bot/CRM',
        );

        $method = new ReflectionMethod(NextcloudWebDavStorage::class, 'rootDirectoryErrorMessage');
        $message = $method->invoke($storage, '/CRM', 404);

        $this->assertStringContainsString('HTTP 404', $message);
        $this->assertStringContainsString('18081', $message);
        $this->assertStringContainsString('crm-bot', $message);
    }
}
