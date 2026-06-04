<?php

namespace App\Support\MailSync;

use IMAP\Connection;

final class MailImapConnection
{
    /**
     * PHP 8.3+ imap_open() returns IMAP\Connection; older versions return resource.
     */
    public static function isActive(mixed $connection): bool
    {
        if ($connection === null || $connection === false) {
            return false;
        }

        if (is_resource($connection)) {
            return true;
        }

        return class_exists(Connection::class, false) && $connection instanceof Connection;
    }
}
