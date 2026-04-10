<?php

namespace App\Enums;

enum SalesPlaySessionOutcome: string
{
    case NoContact = 'no_contact';
    case Progress = 'progress';
    case QuoteSent = 'quote_sent';
    case Won = 'won';
    case Lost = 'lost';
    case Postponed = 'postponed';
}
