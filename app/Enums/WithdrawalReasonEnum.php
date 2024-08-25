<?php

namespace App\Enums;
use Filament\Support\Contracts\HasLabel;

enum WithdrawalReasonEnum: string implements HasLabel
{
    case NOT_ENGLISH = 'language';
    case NOFOLLOW = 'nofollow';
    case DEADSITE = 'deadsite';
    case DIFFICULT = 'difficult';
    case SUBDOMAIN = 'subdomain';
    case PENALTY = 'penalty';
    case HIGH_SPAM_SCORE = 'spam';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NOT_ENGLISH => 'Not English',
            self::NOFOLLOW => 'No Follow or Poor Link',
            self::DEADSITE => 'Dead Website',
            self::DIFFICULT => 'Difficult Posting Rules',
            self::SUBDOMAIN => 'Not a Root Domain',
            self::PENALTY => 'Penalty',
            self::HIGH_SPAM_SCORE => 'Spam',
        };
    }
}