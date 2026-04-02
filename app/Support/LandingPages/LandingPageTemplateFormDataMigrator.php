<?php

namespace App\Support\LandingPages;

class LandingPageTemplateFormDataMigrator
{
    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function migrate(string $templateKey, array $formData): array
    {
        if ($templateKey !== 'template-event') {
            return $formData;
        }

        $formData = $this->migrateTemplateEventCards($formData);

        return $this->migrateTemplateEventCtaButtons($formData);
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    protected function migrateTemplateEventCards(array $formData): array
    {
        $hasCards = isset($formData['cards']) && is_array($formData['cards']) && count($formData['cards']) > 0;

        if ($hasCards) {
            return $formData;
        }

        $cards = [];

        for ($index = 1; $index <= 6; $index++) {
            $title = trim((string) ($formData['card_'.$index.'_title'] ?? ''));
            $content = (string) ($formData['card_'.$index.'_content'] ?? '');
            $hasContent = trim(strip_tags($content)) !== '';

            if ($title === '' && ! $hasContent) {
                continue;
            }

            $cards[] = [
                'order' => $index,
                'title' => $title === '' ? 'Section '.$index : $title,
                'content' => $content,
            ];
        }

        if ($cards === []) {
            $legacyCards = [
                ['title' => 'Program Description', 'content' => (string) ($formData['program_description'] ?? '')],
                ['title' => "Event's Format", 'content' => (string) ($formData['event_format_details'] ?? '')],
                ['title' => 'Modules', 'content' => (string) ($formData['modules_list'] ?? '')],
            ];

            foreach ($legacyCards as $legacyIndex => $legacyCard) {
                if (trim(strip_tags($legacyCard['content'])) === '') {
                    continue;
                }

                $cards[] = [
                    'order' => $legacyIndex + 1,
                    'title' => $legacyCard['title'],
                    'content' => $legacyCard['content'],
                ];
            }
        }

        if ($cards !== []) {
            $formData['cards'] = $cards;
        }

        return $formData;
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    protected function migrateTemplateEventCtaButtons(array $formData): array
    {
        $hasCtaButtons = isset($formData['cta_buttons']) && is_array($formData['cta_buttons']) && count($formData['cta_buttons']) > 0;

        if ($hasCtaButtons) {
            return $formData;
        }

        $legacyCtaLabel = trim((string) ($formData['cta_label'] ?? ''));
        $legacyCtaUrl = trim((string) ($formData['cta_url'] ?? ''));

        if ($legacyCtaLabel !== '' || $legacyCtaUrl !== '') {
            $formData['cta_buttons'] = [[
                'label' => $legacyCtaLabel === '' ? 'Register Now' : $legacyCtaLabel,
                'url' => $legacyCtaUrl === '' ? '#' : $legacyCtaUrl,
            ]];
        }

        return $formData;
    }
}
