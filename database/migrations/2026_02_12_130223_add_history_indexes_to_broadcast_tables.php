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
        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->index(
                ['broadcast_id', 'status', 'sent_at'],
                'broadcast_recipients_broadcast_status_sent_idx',
            );
            $table->index(
                ['email', 'status'],
                'broadcast_recipients_email_status_idx',
            );
            $table->index(
                ['created_at'],
                'broadcast_recipients_created_at_idx',
            );
        });

        Schema::table('broadcast_recipient_events', function (Blueprint $table) {
            $table->index(
                ['broadcast_recipient_id', 'occurred_at'],
                'broadcast_recipient_events_recipient_occurred_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->dropIndex('broadcast_recipients_broadcast_status_sent_idx');
            $table->dropIndex('broadcast_recipients_email_status_idx');
            $table->dropIndex('broadcast_recipients_created_at_idx');
        });

        Schema::table('broadcast_recipient_events', function (Blueprint $table) {
            $table->dropIndex('broadcast_recipient_events_recipient_occurred_idx');
        });
    }
};
