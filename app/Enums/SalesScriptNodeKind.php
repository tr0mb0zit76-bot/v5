<?php

namespace App\Enums;

enum SalesScriptNodeKind: string
{
    case Say = 'say';
    case Ask = 'ask';
    case Branch = 'branch';
}
