<?php

namespace App\Http\Services;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExcelDataService
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $headings
     */
    public function storeRows(string $path, array $rows, array $headings = [], ?string $disk = null): bool
    {
        $payload = array_map(fn (array $row) => array_values($row), $rows);

        try {
            $export = empty($headings)
                ? new class($payload) implements FromArray
                {
                    /**
                     * @param array<int, array<int, mixed>> $rows
                     */
                    public function __construct(private array $rows) {}

                    public function array(): array
                    {
                        return $this->rows;
                    }
                }
                : new class($payload, $headings) implements FromArray, WithHeadings
                {
                    /**
                     * @param array<int, array<int, mixed>> $rows
                     * @param array<int, string> $headings
                     */
                    public function __construct(private array $rows, private array $headings) {}

                    public function array(): array
                    {
                        return $this->rows;
                    }

                    public function headings(): array
                    {
                        return $this->headings;
                    }
                };

            return (bool) Excel::store($export, $path, $disk);
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readRows(string $path, ?string $disk = null, bool $hasHeaderRow = true): array
    {
        try {
            $import = new class implements ToArray
            {
                /**
                 * @param mixed $sheet
                 * @return array<int, array<int, mixed>>
                 */
                public function array($sheet): array
                {
                    return $sheet->toArray();
                }
            };

            $sheets = Excel::toArray($import, $path, $disk);
            $rows = $sheets[0] ?? [];
        } catch (Throwable $exception) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        if (!$hasHeaderRow) {
            return array_values(array_map(
                fn (array $row) => ['row' => $row],
                $rows
            ));
        }

        $header = array_map(fn ($value) => (string) $value, array_shift($rows));
        $mapped = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];
            foreach ($header as $index => $column) {
                $key = trim($column) !== '' ? trim($column) : 'col_' . $index;
                $item[$key] = $row[$index] ?? null;
            }
            $mapped[] = $item;
        }

        return $mapped;
    }
}
