<?php

use App\Models\LinkSite;
use App\Models\Niche;
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
        Schema::create('link_site_niches', function (Blueprint $table) {
            $table->id();            
            $table->foreignIdFor(LinkSite::class);
            $table->foreignIdFor(Niche::class)->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_site_niches');
    }
};
