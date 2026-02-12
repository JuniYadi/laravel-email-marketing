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
        Schema::create('sns_webhook_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_type', 120)->index();
            $table->string('message_id')->nullable()->index();
            $table->string('topic_arn')->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('token')->nullable();
            $table->text('subscribe_url')->nullable();
            $table->text('unsubscribe_url')->nullable();
            $table->string('signature_version')->nullable();
            $table->text('signature')->nullable();
            $table->text('signing_cert_url')->nullable();
            $table->timestampTz('sns_timestamp')->nullable();
            $table->json('payload');
            $table->json('headers');
            $table->longText('raw_body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sns_webhook_messages');
    }
};
