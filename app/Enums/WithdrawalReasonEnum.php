<?php

namespace App\Enums;
use Filament\Support\Contracts\HasLabel;

enum WithdrawalReasonEnum: string implements HasLabel
{
    case NOT_ENGLISH = 'language';
    case NOFOLLOW = 'nofollow';
    case DIFFICULT = 'difficult';
    case SUBDOMAIN = 'subdomain';
    case HIGH_SPAM_SCORE = 'spam';
    case DEADSITE = 'deadsite';
    case CHECKHEALTH = 'checkhealth';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NOT_ENGLISH => 'Not English',
            self::NOFOLLOW => 'No Follow or Poor Link',
            self::DIFFICULT => 'Difficult Posting Rules',
            self::SUBDOMAIN => 'Not a Root Domain',
            self::HIGH_SPAM_SCORE => 'Spam',
            self::DEADSITE => 'Dead Website',
            self::CHECKHEALTH => 'Check Site Health',
        };
    }
}