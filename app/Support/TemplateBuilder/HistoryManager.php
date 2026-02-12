<?php

namespace App\Support\TemplateBuilder;

class HistoryManager
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<string, mixed>  $snapshot
     * @return array{history: array<int, array<string, mixed>>, cursor: int}
     */
    public function record(array $history, int $cursor, array $snapshot, int $limit = 100): array
    {
        if ($cursor >= 0 && isset($history[$cursor]) && $history[$cursor] === $snapshot) {
            return ['history' => $history, 'cursor' => $cursor];
        }

        $trimmedHistory = array_slice($history, 0, $cursor + 1);
        $trimmedHistory[] = $snapshot;

        if (count($trimmedHistory) > $limit) {
            $trimmedHistory = array_slice($trimmedHistory, -$limit);
        }

        return [
            'history' => array_values($trimmedHistory),
            'cursor' => count($trimmedHistory) - 1,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{snapshot: array<string, mixed>, cursor: int}|null
     */
    public function undo(array $history, int $cursor): ?array
    {
        if ($cursor <= 0 || ! isset($history[$cursor - 1])) {
            return null;
        }

        return [
            'snapshot' => $history[$cursor - 1],
            'cursor' => $cursor - 1,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{snapshot: array<string, mixed>, cursor: int}|null
     */
    public function redo(array $history, int $cursor): ?array
    {
        if (! isset($history[$cursor + 1])) {
            return null;
        }

        return [
            'snapshot' => $history[$cursor + 1],
            'cursor' => $cursor + 1,
        ];
    }
}
