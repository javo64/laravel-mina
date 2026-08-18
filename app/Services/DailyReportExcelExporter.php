<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

class DailyReportExcelExporter
{
    public function create(Collection $reports): string
    {
        $dynamicColumns = [];
        foreach ($reports as $report) {
            foreach ($report->form->fields->where('type', '!=', 'section') as $field) {
                $key = Str::lower(trim(($field->section ?: 'General').'|'.$field->name));
                $dynamicColumns[$key] ??= trim(($field->section ?: 'General').' - '.$field->name);
            }
        }

        $headers = ['ID','Fecha y hora','Cartilla','Ámbito','Usuario','Estado','Latitud','Longitud','Google Maps', ...array_values($dynamicColumns)];
        $rows = [];
        foreach ($reports as $report) {
            $values = [];
            foreach ($report->form->fields->where('type', '!=', 'section') as $field) {
                $columnKey = Str::lower(trim(($field->section ?: 'General').'|'.$field->name));
                $value = $report->responses[$field->field_key] ?? null;
                if (is_array($value)) $value = implode(', ', $value);
                if ($field->type === 'photo' && $value) $value = url('storage/'.$value);
                if ($field->type === 'signature' && $value) $value = 'Firma capturada';
                $values[$columnKey] = $value;
            }
            $rows[] = [
                ['value'=>$report->id,'type'=>'number'],
                ['value'=>25569 + ($report->reported_at->timestamp / 86400),'type'=>'date'],
                ['value'=>$report->form->name],
                ['value'=>$report->form->scope ?: 'General'],
                ['value'=>$report->user->name],
                ['value'=>$report->status],
                ['value'=>$report->latitude,'type'=>$report->latitude !== null ? 'number' : 'text'],
                ['value'=>$report->longitude,'type'=>$report->longitude !== null ? 'number' : 'text'],
                ['value'=>$report->latitude !== null ? "https://www.google.com/maps?q={$report->latitude},{$report->longitude}" : ''],
                ...array_map(fn ($key) => ['value'=>$values[$key] ?? ''], array_keys($dynamicColumns)),
            ];
        }

        $directory = storage_path('app/temp');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $path = $directory.'/registros-cartillas-'.Str::uuid().'.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo Excel.');
        }

        $lastColumn = $this->columnLetter(count($headers));
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($headers, $rows, $lastColumn));
        $zip->close();

        return $path;
    }

    private function worksheet(array $headers, array $rows, string $lastColumn): string
    {
        $widths = array_map(fn ($header) => min(45, max(12, mb_strlen($header) + 3)), $headers);
        foreach ($rows as $row) foreach ($row as $index => $cell) {
            if (($cell['type'] ?? 'text') === 'text') $widths[$index] = min(45, max($widths[$index], mb_strlen((string)($cell['value'] ?? '')) + 2));
        }
        $columns = '';
        foreach ($widths as $index => $width) $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';

        $sheetRows = '<row r="1" ht="26" customHeight="1">'.$this->stringCell('A1', 'REGISTRO DE CARTILLAS', 1).'</row>';
        $sheetRows .= '<row r="2" ht="22" customHeight="1">';
        foreach ($headers as $index => $header) $sheetRows .= $this->stringCell($this->columnLetter($index + 1).'2', $header, 2);
        $sheetRows .= '</row>';
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 3;
            $sheetRows .= '<row r="'.$excelRow.'">';
            foreach ($row as $columnIndex => $cell) {
                $coordinate = $this->columnLetter($columnIndex + 1).$excelRow;
                $type = $cell['type'] ?? 'text';
                $value = $cell['value'] ?? '';
                if ($type === 'date') $sheetRows .= '<c r="'.$coordinate.'" s="3"><v>'.$value.'</v></c>';
                elseif ($type === 'number' && $value !== '') $sheetRows .= '<c r="'.$coordinate.'" s="4"><v>'.(float)$value.'</v></c>';
                else $sheetRows .= $this->stringCell($coordinate, (string)$value, 0);
            }
            $sheetRows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.max(2, count($rows) + 2).'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columns.'</cols><sheetData>'.$sheetRows.'</sheetData><autoFilter ref="A2:'.$lastColumn.max(2, count($rows) + 2).'"/>'
            .'<mergeCells count="1"><mergeCell ref="A1:'.$lastColumn.'1"/></mergeCells></worksheet>';
    }

    private function stringCell(string $coordinate, string $value, int $style): string
    {
        return '<c r="'.$coordinate.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->xml($value).'</t></is></c>';
    }

    private function columnLetter(int $number): string
    {
        $result = '';
        while ($number > 0) { $number--; $result = chr(65 + ($number % 26)).$result; $number = intdiv($number, 26); }
        return $result;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Registros" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/></numFmts><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="14"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF176B4D"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFDDE5E1"/></left><right style="thin"><color rgb="FFDDE5E1"/></right><top style="thin"><color rgb="FFDDE5E1"/></top><bottom style="thin"><color rgb="FFDDE5E1"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf></cellXfs></styleSheet>';
    }
}
