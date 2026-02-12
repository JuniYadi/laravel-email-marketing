<?php

namespace App\Support\TemplateBuilder;

class CanvasStateManager
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function addRowPreset(array $rows, int $columns, ?int $position = null): array
    {
        $row = [
            'id' => (string) str()->ulid(),
            'style' => [],
            'columns' => [],
        ];

        $columnCount = $columns === 2 ? 2 : 1;

        for ($index = 0; $index < $columnCount; $index++) {
            $row['columns'][] = [
                'id' => (string) str()->ulid(),
                'width' => $columnCount === 2 ? '50%' : '100%',
                'elements' => [],
            ];
        }

        $insertPosition = $position ?? count($rows);
        $normalizedPosition = max(0, min($insertPosition, count($rows)));

        array_splice($rows, $normalizedPosition, 0, [$row]);

        return array_values($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function moveRow(array $rows, string $rowId, int $position): array
    {
        $currentIndex = $this->findRowIndex($rows, $rowId);

        if ($currentIndex === null) {
            return $rows;
        }

        $normalizedPosition = max(0, min($position, count($rows) - 1));

        if ($currentIndex === $normalizedPosition) {
            return $rows;
        }

        $moved = $rows[$currentIndex];
        array_splice($rows, $currentIndex, 1);
        array_splice($rows, $normalizedPosition, 0, [$moved]);

        return array_values($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function removeRow(array $rows, string $rowId): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => ($row['id'] ?? '') !== $rowId,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $element
     * @return array<int, array<string, mixed>>
     */
    public function insertElement(array $rows, string $rowId, string $columnId, array $element, int $position): array
    {
        foreach ($rows as $rowIndex => $row) {
            foreach (($row['columns'] ?? []) as $columnIndex => $column) {
                if (($column['id'] ?? '') !== $columnId || ($row['id'] ?? '') !== $rowId) {
                    continue;
                }

                $elements = is_array($column['elements'] ?? null) ? $column['elements'] : [];
                $normalizedPosition = max(0, min($position, count($elements)));
                array_splice($elements, $normalizedPosition, 0, [$element]);
                $rows[$rowIndex]['columns'][$columnIndex]['elements'] = array_values($elements);

                return $rows;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function removeElement(array $rows, string $elementId): array
    {
        foreach ($rows as $rowIndex => $row) {
            foreach (($row['columns'] ?? []) as $columnIndex => $column) {
                $elements = is_array($column['elements'] ?? null) ? $column['elements'] : [];
                $filtered = array_values(array_filter(
                    $elements,
                    fn (array $element): bool => ($element['id'] ?? '') !== $elementId,
                ));

                if (count($filtered) !== count($elements)) {
                    $rows[$rowIndex]['columns'][$columnIndex]['elements'] = $filtered;

                    return $rows;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function moveElement(array $rows, string $elementId, string $targetRowId, string $targetColumnId, int $targetPosition): array
    {
        $movedElement = null;
        $rowsWithoutElement = $rows;

        foreach ($rows as $rowIndex => $row) {
            foreach (($row['columns'] ?? []) as $columnIndex => $column) {
                foreach (($column['elements'] ?? []) as $elementIndex => $element) {
                    if (($element['id'] ?? '') !== $elementId) {
                        continue;
                    }

                    $movedElement = $element;
                    array_splice($rowsWithoutElement[$rowIndex]['columns'][$columnIndex]['elements'], $elementIndex, 1);

                    break 3;
                }
            }
        }

        if (! is_array($movedElement)) {
            return $rows;
        }

        foreach ($rowsWithoutElement as $rowIndex => $row) {
            if (($row['id'] ?? '') !== $targetRowId) {
                continue;
            }

            foreach (($row['columns'] ?? []) as $columnIndex => $column) {
                if (($column['id'] ?? '') !== $targetColumnId) {
                    continue;
                }

                $elements = is_array($column['elements'] ?? null) ? $column['elements'] : [];
                $normalizedPosition = max(0, min($targetPosition, count($elements)));
                array_splice($elements, $normalizedPosition, 0, [$movedElement]);
                $rowsWithoutElement[$rowIndex]['columns'][$columnIndex]['elements'] = array_values($elements);

                return $rowsWithoutElement;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function findRowIndex(array $rows, string $rowId): ?int
    {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? '') === $rowId) {
                return $index;
            }
        }

        return null;
    }
}
