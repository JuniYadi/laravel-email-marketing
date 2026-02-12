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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_template_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->unsignedInteger('messages_per_minute')->default(1);
            $table->string('reply_to');
            $table->string('from_name');
            $table->string('from_prefix');
            $table->string('from_domain');
            $table->string('from_email')->nullable();
            $table->string('snapshot_subject')->nullable();
            $table->longText('snapshot_html_content')->nullable();
            $table->json('snapshot_builder_schema')->nullable();
            $table->unsignedInteger('snapshot_template_version')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
