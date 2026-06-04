<?php

namespace App\Enums;

enum OrderIntakeGoldenRecordStatus: string
{
    case Pending = 'pending';
    case Committed = 'committed';
}
