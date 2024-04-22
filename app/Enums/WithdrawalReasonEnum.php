<?php

namespace App\Enums;
use Filament\Support\Contracts\HasLabel;

enum WithdrawalReasonEnum: string implements HasLabel
{
    case HIGH_SPAM_SCORE = 'spam';
    case NOT_ENGLISH = 'language';
    case PENALTY = 'penalty';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::HIGH_SPAM_SCORE => 'High Spam Score',
            self::NOT_ENGLISH => 'Not English',
            self::PENALTY => 'Penalty',
        };
    }
}