<?php

namespace App\Enums;
use Filament\Support\Contracts\HasLabel;

enum WithdrawalReasonEnum: string implements HasLabel
{
    case NOT_ENGLISH = 'language';
    case NOFOLLOW = 'nofollow';
    case MARKED = 'marked';
    case SELFPUBLISH = 'selfpublish';
    case NOEVIDENCE = 'noevidence';
    case DIFFICULT = 'difficult';
    case SUBDOMAIN = 'subdomain';
    case SPAM = 'spam';
    case DEADSITE = 'deadsite';
    case INVALID = 'invalid';
    case CHECKHEALTH = 'checkhealth';
    case CHECKHEALTHMANUAL = 'checkhealthmanual';
    case CHECKTRAFFIC = 'checktraffic';
    case CHECKAGE = 'checkage';
    case CHECKDR = 'checkdr';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NOT_ENGLISH => 'Not English',
            self::NOFOLLOW => 'No Follow Link',
            self::MARKED => 'Content is marked',
            self::SELFPUBLISH => 'Can self publish freely',
            self::NOEVIDENCE => 'No evidence of guest posts',
            self::DIFFICULT => 'Difficult Posting Rules',
            self::SUBDOMAIN => 'Not a Root Domain',
            self::SPAM => 'Spam',
            self::DEADSITE => 'Dead Website',
            self::INVALID => 'Invalid guest post website',
            self::CHECKHEALTH => 'Check Site Health',
            self::CHECKHEALTHMANUAL => 'Manually check site health',
            self::CHECKTRAFFIC => 'Check Site Traffic',
            self::CHECKAGE => 'Check Domain Age',
            self::CHECKDR => 'Check Domain Rank',
        };
    }
}