<?php

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Support\CommercialNudgeType;

final class CommercialNudgeMatch
{
    public function __construct(
        public readonly CommercialNudgeType $type,
        public readonly Lead $lead,
        public readonly string $subjectId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority = 'high',
    ) {}
}
