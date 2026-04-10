<?php

namespace App\Enums;

enum SalesPlayEventType: string
{
    case EnteredNode = 'entered_node';
    case RecordedReaction = 'recorded_reaction';
    case Comment = 'comment';
    case Completed = 'completed';
}
