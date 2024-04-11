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
            $table->boolean('is_withdrawn')->default(false);
            $table->string('withdrawn_reason')->nullable();
            $table->string('niches'); // string of id's from the niches table
            $table->unsignedTinyInteger('semrush_AS');
            $table->unsignedBigInteger('semrush_traffic');
            $table->decimal('semrush_perc_english_traffic'); 
            $table->unsignedBigInteger('semrush_organic_kw');
            $table->unsignedTinyInteger('moz_da');
            $table->unsignedTinyInteger('moz_pa');
            $table->decimal('moz_perc_quality_bl');
            $table->unsignedTinyInteger('moz_spam_score');
            $table->unsignedTinyInteger('domain_age');
            $table->unsignedTinyInteger('majestic_trust_flow');
            $table->unsignedTinyInteger('majestic_citation_flow');
            $table->unsignedTinyInteger('ahrefs_domain_rank');
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
