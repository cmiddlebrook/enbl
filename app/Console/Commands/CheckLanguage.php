<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;


class CheckLanguage extends Command
{
    protected $signature = 'check-language';
    protected $description = 'Checks the language and country code of some link sites';

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->withdrawNonEnglishTLDs();

        $sites = $this->getSitesToCheck();
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Checking language of {$domain}");
            $data = $this->makeAPICall($domain);
            if (!$data) continue;

            $linkSite->country_code = $data['country'] ?? null;

            echo "{$domain} updated! Country Code: $linkSite->country_code \n";
            $linkSite->save();
        }

        $this->withdrawNonEnglishSites();
    }

    private function getSitesToCheck($num = 900)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where(function ($query)
            {
                $query->whereNull('country_code')
                    ->orWhere('country_code', '');
            })
            ->where('is_withdrawn', 0)
            ->orderBy('avg_low_price', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }


    private function makeAPICall($domain)
    {
        try
        {
            $response = $this->client->request('GET', "https://domain-validation1.p.rapidapi.com/getDomainCountry?domain={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'domain-validation1.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($response);

            return $data;
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            echo $e->getMessage();
            return false;
        }
    }

    private function withdrawNonEnglishTLDs()
    {
        $this->info('Withdrawing any Non English TLDs...');

        $countryExtensions =
            [
                '.ac', // Ascension Island
                '.ad', // Andorra
                '.ae', // United Arab Emirates
                '.af', // Afghanistan
                '.ag', // Antigua and Barbuda
                '.ai', // Anguilla
                '.al', // Albania
                '.am', // Armenia
                '.ao', // Angola
                '.aq', // Antarctica
                '.ar', // Argentina
                '.as', // American Samoa
                '.at', // Austria
                '.aw', // Aruba
                '.ax', // Åland Islands
                '.az', // Azerbaijan
                '.ba', // Bosnia and Herzegovina
                '.bb', // Barbados
                '.bd', // Bangladesh
                '.be', // Belgium
                '.bf', // Burkina Faso
                '.bg', // Bulgaria
                '.bh', // Bahrain
                '.bi', // Burundi
                '.bj', // Benin
                '.bl', // Saint Barthélemy
                '.bm', // Bermuda
                '.bn', // Brunei
                '.bo', // Bolivia
                '.bq', // Bonaire, Sint Eustatius, and Saba
                '.br', // Brazil
                '.bs', // Bahamas
                '.bt', // Bhutan
                '.bv', // Bouvet Island
                '.bw', // Botswana
                '.by', // Belarus
                '.bz', // Belize
                '.cc', // Cocos (Keeling) Islands
                '.cd', // Democratic Republic of the Congo
                '.cf', // Central African Republic
                '.cg', // Republic of the Congo
                '.ch', // Switzerland
                '.ci', // Côte d'Ivoire
                '.ck', // Cook Islands
                '.cl', // Chile
                '.cm', // Cameroon
                '.cn', // China
                '.co', // Colombia
                '.cr', // Costa Rica
                '.cu', // Cuba
                '.cv', // Cape Verde
                '.cw', // Curaçao
                '.cx', // Christmas Island
                '.cy', // Cyprus
                '.cz', // Czech Republic
                '.de', // Germany
                '.dj', // Djibouti
                '.dk', // Denmark
                '.dm', // Dominica
                '.do', // Dominican Republic
                '.dz', // Algeria
                '.ec', // Ecuador
                '.ee', // Estonia
                '.eg', // Egypt
                '.er', // Eritrea
                '.es', // Spain
                '.et', // Ethiopia
                '.eu', // European Union
                '.fi', // Finland
                '.fj', // Fiji
                '.fk', // Falkland Islands
                '.fm', // Micronesia
                '.fo', // Faroe Islands
                '.fr', // France
                '.ga', // Gabon
                '.gd', // Grenada
                '.ge', // Georgia
                '.gf', // French Guiana
                '.gg', // Guernsey
                '.gh', // Ghana
                '.gi', // Gibraltar
                '.gl', // Greenland
                '.gm', // Gambia
                '.gn', // Guinea
                '.gp', // Guadeloupe
                '.gq', // Equatorial Guinea
                '.gr', // Greece
                '.gt', // Guatemala
                '.gu', // Guam
                '.gw', // Guinea-Bissau
                '.gy', // Guyana
                '.hk', // Hong Kong
                '.hm', // Heard Island and McDonald Islands
                '.hn', // Honduras
                '.hr', // Croatia
                '.ht', // Haiti
                '.hu', // Hungary
                '.id', // Indonesia
                '.il', // Israel
                '.im', // Isle of Man
                '.in', // India
                '.io', // British Indian Ocean Territory
                '.iq', // Iraq
                '.ir', // Iran
                '.is', // Iceland
                '.it', // Italy
                '.je', // Jersey
                '.jm', // Jamaica
                '.jo', // Jordan
                '.jp', // Japan
                '.ke', // Kenya
                '.kg', // Kyrgyzstan
                '.kh', // Cambodia
                '.ki', // Kiribati
                '.km', // Comoros
                '.kn', // Saint Kitts and Nevis
                '.kp', // North Korea
                '.kr', // South Korea
                '.kw', // Kuwait
                '.ky', // Cayman Islands
                '.kz', // Kazakhstan
                '.la', // Laos
                '.lb', // Lebanon
                '.lc', // Saint Lucia
                '.li', // Liechtenstein
                '.lk', // Sri Lanka
                '.lr', // Liberia
                '.ls', // Lesotho
                '.lt', // Lithuania
                '.lu', // Luxembourg
                '.lv', // Latvia
                '.ly', // Libya
                '.ma', // Morocco
                '.mc', // Monaco
                '.md', // Moldova
                '.me', // Montenegro
                '.mf', // Saint Martin
                '.mg', // Madagascar
                '.mh', // Marshall Islands
                '.mk', // North Macedonia
                '.ml', // Mali
                '.mm', // Myanmar
                '.mn', // Mongolia
                '.mo', // Macau
                '.mp', // Northern Mariana Islands
                '.mq', // Martinique
                '.mr', // Mauritania
                '.ms', // Montserrat
                '.mt', // Malta
                '.mu', // Mauritius
                '.mv', // Maldives
                '.mw', // Malawi
                '.mx', // Mexico
                '.my', // Malaysia
                '.mz', // Mozambique
                '.na', // Namibia
                '.nc', // New Caledonia
                '.ne', // Niger
                '.nf', // Norfolk Island
                '.ng', // Nigeria
                '.ni', // Nicaragua
                '.nl', // Netherlands
                '.no', // Norway
                '.np', // Nepal
                '.nr', // Nauru
                '.nu', // Niue
                '.om', // Oman
                '.pa', // Panama
                '.pe', // Peru
                '.pf', // French Polynesia
                '.pg', // Papua New Guinea
                '.ph', // Philippines
                '.pk', // Pakistan
                '.pl', // Poland
                '.pm', // Saint Pierre and Miquelon
                '.pn', // Pitcairn Islands
                '.pr', // Puerto Rico
                '.ps', // Palestine
                '.pt', // Portugal
                '.pw', // Palau
                '.py', // Paraguay
                '.qa', // Qatar
                '.re', // Réunion
                '.ro', // Romania
                '.rs', // Serbia
                '.ru', // Russia
                '.rw', // Rwanda
                '.sa', // Saudi Arabia
                '.sb', // Solomon Islands
                '.sc', // Seychelles
                '.sd', // Sudan
                '.se', // Sweden
                '.sg', // Singapore
                '.sh', // Saint Helena
                '.si', // Slovenia
                '.sj', // Svalbard and Jan Mayen
                '.sk', // Slovakia
                '.sl', // Sierra Leone
                '.sm', // San Marino
                '.sn', // Senegal
                '.so', // Somalia
                '.sr', // Suriname
                '.ss', // South Sudan
                '.st', // São Tomé and Príncipe
                '.sv', // El Salvador
                '.sx', // Sint Maarten
                '.sy', // Syria
                '.sz', // Eswatini (Swaziland)
                '.tc', // Turks and Caicos Islands
                '.td', // Chad
                '.tf', // French Southern Territories
                '.tg', // Togo
                '.th', // Thailand
                '.tj', // Tajikistan
                '.tk', // Tokelau
                '.tl', // Timor-Leste
                '.tm', // Turkmenistan
                '.tn', // Tunisia
                '.to', // Tonga
                '.tr', // Turkey
                '.tt', // Trinidad and Tobago
                '.tv', // Tuvalu
                '.tz', // Tanzania
                '.ua', // Ukraine
                '.ug', // Uganda
                '.uy', // Uruguay
                '.uz', // Uzbekistan
                '.va', // Vatican City
                '.vc', // Saint Vincent and the Grenadines
                '.ve', // Venezuela
                '.vg', // British Virgin Islands
                '.vi', // U.S. Virgin Islands
                '.vn', // Vietnam
                '.vu', // Vanuatu
                '.wf', // Wallis and Futuna
                '.ws', // Samoa
                '.ye', // Yemen
                '.yt', // Mayotte
                '.za', // South Africa
                '.zm', // Zambia
                '.zw', // Zimbabwe
            ];


        DB::table('link_sites')
            ->where(function ($query) use ($countryExtensions)
            {
                foreach ($countryExtensions as $extension)
                {
                    $query->orWhere('domain', 'LIKE', '%' . $extension);
                }
            })
            ->where('is_withdrawn', 0)
            ->update([
                'is_withdrawn' => 1,
                'withdrawn_reason' => 'language'
            ]);

    }

    private function withdrawNonEnglishSites()
    {
        $this->info('Withdrawing any new Non English sites...');

        DB::table('link_sites')
            ->whereNotNull('country_code')
            ->whereNotIn('country_code', ['US', 'CA', 'GB', 'AU', 'NZ'])
            ->update([
                'is_withdrawn' => 1,
                'withdrawn_reason' => 'language'
            ]);
    }
}
