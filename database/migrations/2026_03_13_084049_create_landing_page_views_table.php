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
        Schema::create('landing_page_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('viewed_at')->index();
            $table->timestamps();

            $table->index(['landing_page_id', 'is_bot', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_views');
    }
};
