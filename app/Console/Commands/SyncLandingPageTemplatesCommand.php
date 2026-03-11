<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Support\LandingPages\LandingPageTemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
            $refreshed = $this->refreshLandingPageSnapshots();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            if ((bool) $this->option('fail-on-invalid')) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Synced %d template(s), deactivated %d template(s), refreshed %d landing page snapshot(s).',
            $result['synced'],
            $result['deactivated'],
            $refreshed,
        ));

        return self::SUCCESS;
    }

    protected function refreshLandingPageSnapshots(): int
    {
        $templates = LandingPageTemplate::query()
            ->where('is_active', true)
            ->get(['id', 'key', 'name', 'description', 'view_path', 'version', 'schema']);

        $updated = 0;

        foreach ($templates as $template) {
            $snapshot = [
                'key' => $template->key,
                'name' => $template->name,
                'description' => $template->description,
                'view_path' => $template->view_path,
                'version' => $template->version,
                'schema' => is_array($template->schema) ? $template->schema : ['fields' => []],
            ];

            $allowedKeys = collect(data_get($snapshot, 'schema.fields', []))
                ->map(fn (mixed $field): string => is_array($field) ? (string) ($field['key'] ?? '') : '')
                ->filter()
                ->values()
                ->all();

            LandingPage::query()
                ->where('landing_page_template_id', $template->id)
                ->orderBy('id')
                ->chunkById(200, function (Collection $pages) use ($snapshot, $allowedKeys, &$updated): void {
                    foreach ($pages as $page) {
                        $currentSnapshot = is_array($page->template_snapshot) ? $page->template_snapshot : [];
                        $currentFormData = is_array($page->form_data) ? $page->form_data : [];
                        $filteredFormData = collect($currentFormData)->only($allowedKeys)->all();

                        if ($currentSnapshot === $snapshot && $currentFormData === $filteredFormData) {
                            continue;
                        }

                        $page->forceFill([
                            'template_snapshot' => $snapshot,
                            'form_data' => $filteredFormData,
                        ])->save();

                        $updated++;
                    }
                });
        }

        return $updated;
    }
}
