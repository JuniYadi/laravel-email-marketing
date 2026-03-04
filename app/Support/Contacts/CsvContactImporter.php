<?php

namespace App\Support\Contacts;

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Support\Str;

class CsvContactImporter
{
    /**
     * Import contacts from normalized CSV rows.
     *
     * @param  list<list<string|null>>  $rows
     * @param  list<int|string>  $selectedGroupIds
     */
    public function importRows(array $rows, array $selectedGroupIds = []): int
    {
        if ($rows === [] || ! isset($rows[0])) {
            return 0;
        }

        $headers = collect($rows[0])
            ->map(fn (?string $header): string => trim((string) $header))
            ->all();

        $importedCount = 0;

        foreach (array_slice($rows, 1) as $row) {
            if ($row === [] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rowData = $this->buildRowData($headers, $row);
            $email = trim((string) ($rowData['email'] ?? ''));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            [$firstName, $lastName] = $this->extractNames($rowData);

            if ($firstName === '') {
                continue;
            }

            $customFields = $this->extractCustomFields($headers, $row);
            $contact = Contact::query()->firstOrNew(['email' => $email]);
            $existingCustomFields = is_array($contact->custom_fields) ? $contact->custom_fields : [];

            $contact->email = $email;
            $contact->first_name = $firstName;
            $contact->last_name = $lastName;
            $contact->company = $this->nullableTrim($rowData['company'] ?? null);
            $contact->is_invalid = $this->toBoolean($rowData['isinvalid'] ?? null);
            $contact->custom_fields = $this->mergeCustomFields($existingCustomFields, $customFields);
            $contact->save();

            $csvGroupIds = $this->extractCsvGroupIds($rowData);
            $groupIds = collect($selectedGroupIds)
                ->merge($csvGroupIds)
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($groupIds !== []) {
                $existingGroupIds = ContactGroup::query()->whereIn('id', $groupIds)->pluck('id');
                $contact->groups()->syncWithoutDetaching($existingGroupIds);
            }

            $importedCount++;
        }

        return $importedCount;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    protected function buildRowData(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = Str::lower(str_replace([' ', '_'], '', $header));
            $mapped[$normalizedHeader] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  $rowData
     * @return array{0: string, 1: string}
     */
    protected function extractNames(array $rowData): array
    {
        $firstName = trim((string) ($rowData['firstname'] ?? ''));
        $lastName = trim((string) ($rowData['lastname'] ?? ''));

        if ($firstName !== '') {
            return [$firstName, $lastName];
        }

        $fullName = trim((string) ($rowData['fullname'] ?? ''));

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, string>  $rowData
     * @return list<int>
     */
    protected function extractCsvGroupIds(array $rowData): array
    {
        $groups = trim((string) ($rowData['groups'] ?? ''));

        if ($groups === '') {
            return [];
        }

        return collect(explode(',', $groups))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    protected function extractCustomFields(array $headers, array $row): array
    {
        $reservedKeys = ContactVariableRegistry::reservedCustomFieldKeys();
        $customFields = [];

        foreach ($headers as $index => $header) {
            $normalizedKey = ContactVariableRegistry::normalizeCustomFieldKey($header);

            if ($normalizedKey === '' || in_array($normalizedKey, $reservedKeys, true)) {
                continue;
            }

            $customFields[$normalizedKey] = trim((string) ($row[$index] ?? ''));
        }

        return $customFields;
    }

    /**
     * @param  array<string, mixed>  $existingCustomFields
     * @param  array<string, string>  $incomingCustomFields
     * @return array<string, mixed>
     */
    protected function mergeCustomFields(array $existingCustomFields, array $incomingCustomFields): array
    {
        foreach ($incomingCustomFields as $key => $value) {
            if (trim($value) === '') {
                continue;
            }

            $existingCustomFields[$key] = $value;
        }

        return $existingCustomFields;
    }
}
