<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor Excel (.xlsx) sederhana: satu atau beberapa sheet berisi judul kolom + baris. Angka uang ditulis sebagai angka
 * (bukan teks berformat) agar bisa dijumlahkan di Excel.
 */
final class Spreadsheet
{
    /**
     * @param  array<string, array{headers: list<string>, rows: iterable<array<int, scalar|null>>}>  $sheets
     */
    public static function download(string $filename, array $sheets): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-');
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->setFontBold();
        $first = true;

        foreach ($sheets as $name => $sheet) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }

            $writer->getCurrentSheet()->setName(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $name) ?? 'Sheet', 0, 31));
            $writer->addRow(Row::fromValues($sheet['headers'], $bold));

            foreach ($sheet['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }

            $first = false;
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
