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
        Schema::table('contact_groups', function (Blueprint $table) {
            $table->dropColumn([
                'reply_to',
                'from_email_prefix',
                'template_id',
                'start_broadcast',
                'message_per_minutes',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_groups', function (Blueprint $table) {
            $table->string('reply_to')->default('reply@example.com');
            $table->string('from_email_prefix')->default('sender');
            $table->string('template_id')->default('template-default');
            $table->boolean('start_broadcast')->default(false);
            $table->unsignedInteger('message_per_minutes')->default(1);
        });
    }
};
