<?php

namespace App\Support;

enum AiInteractionEventType: string
{
    case ConversationTurn = 'conversation_turn';
    case ToolInvoked = 'tool_invoked';
    case IntakeExtracted = 'intake_extracted';
    case UserFeedback = 'user_feedback';
}
