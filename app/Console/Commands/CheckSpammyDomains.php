<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class CheckSpammyDomains extends Command
{
    protected $signature = 'check-spammy-domains';
    protected $description = 'Withdraws domain names with spam words in them';

    protected $spamWords;
    protected $options;

    public function __construct()
    {
        parent::__construct();

        $this->options = ['Yes', 'No'];
        $this->spamWords = array(
            'porn',
            'xxx',
            'india',
            'hindi',
            'hindu',
            'dubai',
            'torrent',
            'cannabis',
            'cbd',
            'pharma',
            'cialis',
            'viagra',
            'casino',
            'poker',
            'roulette',
            'bingo',
            'gambling',
            'betting',
            'crypto',
            'bitcoin',
            'coinbase',
            'forex',
            'insurance',
            'outlet',
            'michaelkors',
            'vuitton',
            'burberry',
            'rayban',
            'my.id'
        );
    }


    public function handle()
    {
        $first = false;
        $sites = $this->getSitesToCheck();
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Parsing Domain {$domain}");

            // once I've withdrawn a new site, scroll through slowly so I can keep a look out for new words to add
            // if ($first) usleep(300000);

            foreach ($this->spamWords as $spamWord)
            {
                if (Str::contains($domain, $spamWord))
                {
                    $first = $this->withdrawDomain($domain);
                }
            }            
        }
    }

    private function withdrawDomain($domain)
    {
        $action = $this->choice("Withdraw {$domain}?", $this->options, $defaultIndex = 0);
        if ($action === 'Yes')
        {
            DB::table('link_sites')
            ->where('domain', '=', $domain)
            ->update([
                'is_withdrawn' => 1,
                'withdrawn_reason' => 'spam'
            ]);

            echo "{$domain} withdrawn\n";
            return true;
        }

        return false;
    }

    private function getSitesToCheck()
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            ->orderBy('semrush_AS', 'desc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->get();

        return $sites;
    }
}
