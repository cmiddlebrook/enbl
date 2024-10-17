<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('link_sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->boolean('is_withdrawn')->default(0);
            $table->string('withdrawn_reason')->nullable();
            $table->unsignedTinyInteger('semrush_AS')->nullable();
            $table->unsignedBigInteger('semrush_traffic')->nullable();
            $table->unsignedTinyInteger('semrush_perc_english_traffic')->nullable();
            $table->unsignedBigInteger('semrush_organic_kw')->nullable();
            $table->date('last_checked_semrush')->nullable();
            $table->date('last_checked_traffic')->nullable();
            $table->unsignedTinyInteger('moz_da')->nullable();
            $table->unsignedTinyInteger('moz_pa')->nullable();
            $table->decimal('moz_rank', 3, 1)->nullable();
            $table->unsignedBigInteger('moz_links')->nullable();
            $table->unsignedTinyInteger('majestic_trust_flow')->nullable();
            $table->unsignedTinyInteger('majestic_citation_flow')->nullable();
            $table->unsignedBigInteger('majestic_ref_domains')->nullable();
            $table->unsignedBigInteger('majestic_ref_edu')->nullable();
            $table->unsignedBigInteger('majestic_ref_gov')->nullable();
            $table->string('majestic_TTF0_name')->nullable();
            $table->unsignedTinyInteger('majestic_TTF0_value')->nullable();
            $table->string('majestic_TTF1_name')->nullable();
            $table->unsignedTinyInteger('majestic_TTF1_value')->nullable();
            $table->string('majestic_TTF2_name')->nullable();
            $table->unsignedTinyInteger('majestic_TTF2_value')->nullable();
            $table->unsignedBigInteger('facebook_shares')->nullable();
            $table->date('last_checked_mozmaj')->nullable();
            $table->unsignedTinyInteger('ahrefs_domain_rank')->nullable();
            $table->date('last_checked_dr')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country_code')->nullable();
            $table->date('domain_creation_date')->nullable();
            $table->date('last_checked')->nullable();
            $table->dateTime('last_checked_health')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_sites');
    }
};
