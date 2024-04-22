<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LinkSite>
 */
class LinkSiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => $this->faker->unique()->domainName,
            'ip_address' => $this->faker->unique()->ipv4,
            'niches' => '',
            'semrush_AS' => $this->faker->numberBetween(0,50),
            'semrush_traffic' => $this->faker->numberBetween(0,10000000),
            'semrush_perc_english_traffic' => $this->faker->numberBetween(0,100),
            'semrush_organic_kw' => $this->faker->numberBetween(0,100000),
            'moz_da' => $this->faker->numberBetween(0,70),
            'moz_pa' => $this->faker->numberBetween(0,50),
            'moz_perc_quality_bl' => $this->faker->numberBetween(0,30),
            'moz_spam_score' => $this->faker->numberBetween(0,5),
            'domain_age' => $this->faker->numberBetween(0,30),
            'majestic_trust_flow' => $this->faker->numberBetween(0,40),
            'majestic_citation_flow' => $this->faker->numberBetween(0,70),
            'ahrefs_domain_rank' => $this->faker->numberBetween(0,70),
        ];
    }
}
