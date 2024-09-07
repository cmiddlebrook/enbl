<?php

use App\Models\LinkSite;
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
        Schema::create('link_site_health', function (Blueprint $table) 
        {
            $table->id();
            $table->foreignIdFor(LinkSite::class)->constrained()->cascadeOnDelete();
            $table->dateTime('check_date');
            $table->boolean('up')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_site_health');
    }
};
