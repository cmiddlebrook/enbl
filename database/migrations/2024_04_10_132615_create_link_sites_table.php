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
            $table->string('ip_address')->nullable();
            $table->date('last_checked')->nullable();
            $table->boolean('is_withdrawn')->nullable();
            $table->string('withdrawn_reason')->nullable();
            $table->unsignedTinyInteger('semrush_AS')->nullable();
            $table->unsignedBigInteger('semrush_traffic')->nullable();
            $table->unsignedTinyInteger('semrush_perc_english_traffic')->nullable();
            $table->unsignedBigInteger('semrush_organic_kw')->nullable();
            $table->unsignedTinyInteger('moz_da')->nullable();
            $table->unsignedTinyInteger('moz_pa')->nullable();
            $table->unsignedTinyInteger('moz_perc_quality_bl')->nullable();
            $table->unsignedTinyInteger('moz_spam_score')->nullable();
            $table->unsignedTinyInteger('domain_age')->nullable();
            $table->unsignedTinyInteger('majestic_trust_flow')->nullable();
            $table->unsignedTinyInteger('majestic_citation_flow')->nullable();
            $table->unsignedTinyInteger('ahrefs_domain_rank')->nullable();
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
