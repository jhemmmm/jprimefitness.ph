<?php

namespace App\Support;

use App\Models\BusinessProfile;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One description of a panel report (filters, summary figures, tables) that
 * downloads as a styled workbook: an Overview sheet plus one filterable
 * sheet per table.
 *
 * Cells are int (count), float (amount, two decimals), string, null or a
 * date-time; the writer formats them accordingly.
 */
class ReportExport
{
    /**
     * @param  array<string, string>  $filters  label => value
     * @param  array<string, int|float>  $summary  label => value
     * @param  array<int, array{0: string, 1: array<int, string>, 2: iterable<int, array<int, mixed>>}>  $tables  [title, columns, rows]
     */
    public function __construct(
        public readonly string $title,
        public readonly array $filters,
        public readonly array $summary,
        public readonly array $tables,
    ) {}

    public function download(): StreamedResponse
    {
        $fileName = str($this->title)->slug().'-'.now()->format('Ymd_His').'.xlsx';

        // ponytail: the whole workbook is built in memory (~1 KB per cell); switch to a streaming
        // writer or cap the export period if all-time exports outgrow this.
        ini_set('memory_limit', '512M');

        $book = new Spreadsheet;
        $overview = $book->getActiveSheet()->setTitle('Overview');
        $by = auth()->user()?->name;

        foreach ([
            [BusinessProfile::current()->name, 14, '111827'],
            [$this->title, 11, '111827'],
            ['Generated '.now()->format('M d, Y h:i A').($by ? " by {$by}" : ''), 9, '6B7280'],
        ] as $index => [$text, $size, $color]) {
            $overview->mergeCells('A'.($index + 1).':B'.($index + 1));
            $overview->setCellValue('A'.($index + 1), $text)
                ->getStyle('A'.($index + 1))->applyFromArray(['font' => ['size' => $size, 'color' => ['rgb' => $color]]]);
        }
        $overview->getStyle('A1:A2')->getFont()->setBold(true);

        $pairs = fn (array $map) => array_map(null, array_keys($map), array_values($map));
        $lastRow = $this->table($overview, 5, 'Filters', ['Filter', 'Value'], $pairs($this->filters));
        $this->table($overview, $lastRow + 2, 'Summary', ['Metric', 'Value'], $pairs($this->summary));

        foreach ($this->tables as [$title, $columns, $rows]) {
            $sheet = $book->createSheet()->setTitle(mb_substr(str_replace(Worksheet::getInvalidCharacters(), '', $title), 0, Worksheet::SHEET_TITLE_MAXIMUM_LENGTH));
            $lastRow = $this->table($sheet, 1, $title, $columns, $rows);
            $sheet->setAutoFilter('A2:'.Coordinate::stringFromColumnIndex(count($columns)).$lastRow);
            $sheet->freezePane('A3');
        }

        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * A date-range filter as one line: both ends, one end, or "All dates".
     */
    public static function period(?string $from, ?string $to): string
    {
        $format = fn (string $date) => Date::parse($date)->format('M d, Y');

        return match (true) {
            (bool) $from && (bool) $to => $format($from).' – '.$format($to),
            (bool) $from => 'From '.$format($from),
            (bool) $to => 'Until '.$format($to),
            default => 'All dates',
        };
    }

    /**
     * Red title, dark header and bordered rows (or an empty-state line)
     * starting at $row; returns the last row written.
     */
    private function table(Worksheet $sheet, int $row, string $title, array $columns, iterable $rows): int
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("A{$row}", $title)->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'D72638']]]);

        $head = ++$row;
        $this->cells($sheet, $row, $columns);
        $sheet->getStyle("A{$head}:{$lastColumn}{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
        ]);

        $formats = [];
        foreach ($rows as $cells) {
            $this->cells($sheet, ++$row, $cells, $formats);
        }

        if ($row === $head) {
            $sheet->setCellValue('A'.(++$row), 'No records for the selected filters.')
                ->getStyle("A{$row}")->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '6B7280']]]);
        }

        // One style apply per format per column: per-cell styling makes PhpSpreadsheet clone+hash a style for every cell.
        foreach ($formats as $column => $codes) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $unique = array_unique($codes);
            foreach ($unique as $code) {
                $range = count($unique) === 1
                    ? "{$letter}".($head + 1).":{$letter}{$row}"
                    : implode(',', array_map(fn (int $r) => "{$letter}{$r}", array_keys($codes, $code, true)));
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode($code);
            }
        }
        $sheet->getStyle("A{$head}:{$lastColumn}{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (range(1, count($columns)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        return $row;
    }

    /**
     * Write one row; records the Excel number format of each typed cell in $formats[column][row].
     */
    private function cells(Worksheet $sheet, int $row, array $cells, array &$formats = []): void
    {
        foreach (array_values($cells) as $index => $value) {
            $cell = $sheet->getCell([$index + 1, $row]);

            if ($value instanceof DateTimeInterface) {
                $cell->setValue(ExcelDate::PHPToExcel($value));
                $formats[$index + 1][$row] = 'mmm dd, yyyy hh:mm AM/PM';
            } elseif (is_float($value)) {
                $cell->setValue($value);
                $formats[$index + 1][$row] = '#,##0.00';
            } elseif (is_int($value)) {
                $cell->setValue($value);
                $formats[$index + 1][$row] = '#,##0';
            } else {
                $cell->setValueExplicit($value === null || $value === '' ? '—' : (string) $value, DataType::TYPE_STRING);
            }
        }
    }
}
