<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

// Minimal .xlsx writer (inline strings, numbers, multiple sheets) so reports
// can be exported to Excel without a composer dependency — composer can't run
// on the production host, so a package would mean shipping vendor/ by hand.
class SimpleXlsx
{
    /**
     * @param  array<string, array<int, array<int, string|int|float|null>>>  $sheets  sheet name => rows
     */
    public static function build(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for Excel export.');
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $names = array_keys($sheets);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', array_keys($names))).'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .implode('', array_map(fn ($i, $n) => '<sheet name="'.self::esc(mb_substr($n, 0, 31)).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>', array_keys($names), $names)).'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', array_map(fn ($i) => '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>', array_keys($names))).'</Relationships>');

        foreach (array_values($sheets) as $i => $rows) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', self::sheet($rows));
        }

        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /** @param array<int, array<int, string|int|float|null>> $rows */
    private static function sheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $value) {
                $ref = self::column($c).($r + 1);
                if ($value === null || $value === '') {
                    continue;
                }
                $xml .= is_int($value) || is_float($value)
                    ? '<c r="'.$ref.'"><v>'.$value.'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26).$name;
        }

        return $name;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
