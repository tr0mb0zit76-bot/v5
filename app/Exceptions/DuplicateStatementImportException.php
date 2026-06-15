<?php

namespace App\Exceptions;

use App\Models\ManagementStatementImport;
use RuntimeException;

class DuplicateStatementImportException extends RuntimeException
{
    public function __construct(public readonly ManagementStatementImport $existingImport)
    {
        parent::__construct('Все операции из файла уже загружены ранее.');
    }
}
