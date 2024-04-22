<?php

use App\Models\Seller;
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
        Schema::create('seller_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Seller::class)->cascadeOnDelete();
            $table->foreignIdFor(LinkSite::class);
            $table->decimal('price_guest_post');
            $table->decimal('price_link_insertion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_sites');
    }
};
