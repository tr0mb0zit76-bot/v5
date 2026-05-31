<?php

namespace App\Support;

enum AiChannel: string
{
    case LocalOnly = 'local_only';
    case ExternalLarge = 'external_large';
}
