<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fallbackTimezone = (string) config('app.timezone', 'UTC');

        DB::table('broadcasts')
            ->whereNotNull('starts_at')
            ->where(function ($query): void {
                $query->whereNull('starts_at_timezone')
                    ->orWhere('starts_at_timezone', '');
            })
            ->update([
                'starts_at_timezone' => $fallbackTimezone,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
