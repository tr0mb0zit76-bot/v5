<?php

namespace App\Support;

enum AiInteractionOutcome: string
{
    case Success = 'success';
    case WeakAnswer = 'weak_answer';
    case Failed = 'failed';
    case Unavailable = 'unavailable';
}
