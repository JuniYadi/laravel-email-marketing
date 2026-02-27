<?php

namespace App\Console\Commands;

use App\Support\LandingPages\LandingPageTemplateRegistry;
use Illuminate\Console\Command;
use Throwable;

class SyncLandingPageTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:sync-templates {--fail-on-invalid : Exit with non-zero status when metadata is invalid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync landing page template metadata from resources/landing-page-templates into the database.';

    /**
     * Execute the console command.
     */
    public function handle(LandingPageTemplateRegistry $registry): int
    {
        try {
            $result = $registry->sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            if ((bool) $this->option('fail-on-invalid')) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->info(sprintf('Synced %d template(s), deactivated %d template(s).', $result['synced'], $result['deactivated']));

        return self::SUCCESS;
    }
}
