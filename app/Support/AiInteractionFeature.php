<?php

namespace App\Support;

enum AiInteractionFeature: string
{
    case CommandBar = 'command_bar';
    case OrderIntake = 'order_intake';
    case Mcp = 'mcp';
    case Trainer = 'trainer';
}
