<?php

namespace App\Services;

use App\Models\Requirement;

class RequirementPdfGenerator
{
    public function render(Requirement $requirement): string
    {
        $requirement->loadMissing(['items', 'decisionMaker']);
        $pages = [];
        $content = $this->pageHeader($requirement);
        $y = 744;

        $content .= $this->sectionTitle('INFORMACION GENERAL', $y);
        $y -= 30;
        $general = [
            ['Codigo', $requirement->code, 'Fecha', $requirement->requested_at->format('d/m/Y')],
            ['Responsable', $requirement->responsible, 'Proyecto', $requirement->project],
            ['Area solicitante', $requirement->area ?: 'No indicada', 'Prioridad', $requirement->priority],
            ['Estado', $requirement->status, 'Decision por', $requirement->decisionMaker?->name ?: 'Pendiente'],
        ];
        foreach ($general as [$leftLabel, $leftValue, $rightLabel, $rightValue]) {
            $content .= $this->labelValue($leftLabel, $leftValue, 40, $y, 238);
            $content .= $this->labelValue($rightLabel, $rightValue, 310, $y, 238);
            $y -= 43;
        }

        $y -= 2;
        $content .= $this->sectionTitle('ITEMS SOLICITADOS', $y);
        $y -= 30;
        $content .= $this->itemHeader($y);
        $y -= 25;

        foreach ($requirement->items as $index => $item) {
            $productLines = $this->wrap(($item->product_name ?: 'Producto').' '.($item->description ?: ''), 47);
            $rowHeight = max(32, 18 + (count($productLines) * 12));
            if ($y - $rowHeight < 68) {
                $pages[] = $content;
                $content = $this->pageHeader($requirement);
                $y = 744;
                $content .= $this->sectionTitle('ITEMS SOLICITADOS (CONTINUACION)', $y);
                $y -= 30;
                $content .= $this->itemHeader($y);
                $y -= 25;
            }
            $content .= $this->rect(36, $y - $rowHeight + 8, 523, $rowHeight, false, [0.86,0.89,0.88]);
            $content .= $this->text((string) ($index + 1), 48, $y - 8, 9);
            $content .= $this->text($this->quantity($item->quantity), 75, $y - 8, 9);
            $content .= $this->text($item->unit, 135, $y - 8, 9);
            foreach ($productLines as $lineIndex => $line) $content .= $this->text($line, 195, $y - 8 - ($lineIndex * 12), 9, $lineIndex === 0);
            $content .= $this->text($item->priority, 500, $y - 8, 9, true, $this->priorityColor($item->priority));
            $y -= $rowHeight;
        }

        if ($y < 145) {
            $pages[] = $content;
            $content = $this->pageHeader($requirement);
            $y = 744;
        }
        $y -= 12;
        $content .= $this->sectionTitle('TRAZABILIDAD DE APROBACION', $y);
        $y -= 33;
        $decision = $requirement->decision_at
            ? $requirement->status.' el '.$requirement->decision_at->format('d/m/Y H:i').' por '.($requirement->decisionMaker?->name ?: 'Usuario retirado')
            : 'El requerimiento se encuentra pendiente de decision.';
        foreach ($this->wrap($decision, 92) as $line) {
            $content .= $this->text($line, 45, $y, 10, false, [0.25,0.31,0.29]);
            $y -= 14;
        }
        $pages[] = $content;

        return $this->document($pages, $requirement->code);
    }

    private function pageHeader(Requirement $requirement): string
    {
        return $this->rect(0, 780, 595, 62, true, [0.09,0.42,0.30])
            .$this->text('FABULOSA', 36, 811, 17, true, [1,1,1])
            .$this->text('REQUERIMIENTO DE COMPRA', 350, 811, 12, true, [1,1,1])
            .$this->text($requirement->code, 438, 792, 9, false, [0.84,0.94,0.90])
            .$this->text('Documento generado desde el modulo de Aprobaciones', 36, 764, 8, false, [0.42,0.48,0.45]);
    }

    private function sectionTitle(string $title, float $y): string
    {
        return $this->rect(36, $y - 8, 523, 25, true, [0.94,0.97,0.95])
            .$this->text($title, 45, $y, 10, true, [0.09,0.42,0.30]);
    }

    private function labelValue(string $label, string $value, float $x, float $y, float $width): string
    {
        $value = $this->wrap($value, 38)[0] ?? '';
        return $this->rect($x - 4, $y - 22, $width, 37, false, [0.88,0.90,0.89])
            .$this->text(mb_strtoupper($label), $x + 3, $y + 2, 7, true, [0.43,0.49,0.46])
            .$this->text($value, $x + 3, $y - 13, 10, true, [0.15,0.20,0.18]);
    }

    private function itemHeader(float $y): string
    {
        return $this->rect(36, $y - 15, 523, 25, true, [0.09,0.42,0.30])
            .$this->text('N', 48, $y - 6, 8, true, [1,1,1])
            .$this->text('CANT.', 75, $y - 6, 8, true, [1,1,1])
            .$this->text('UNIDAD', 135, $y - 6, 8, true, [1,1,1])
            .$this->text('PRODUCTO / DESCRIPCION', 195, $y - 6, 8, true, [1,1,1])
            .$this->text('PRIORIDAD', 495, $y - 6, 8, true, [1,1,1]);
    }

    private function text(string $value, float $x, float $y, float $size = 10, bool $bold = false, array $color = [0.15,0.20,0.18]): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;
        $escaped = str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $encoded);
        return sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $color[0], $color[1], $color[2], $x, $y, $escaped);
    }

    private function rect(float $x, float $y, float $width, float $height, bool $fill, array $color): string
    {
        return sprintf("%.3F %.3F %.3F %s %.2F %.2F %.2F %.2F re %s\n", $color[0], $color[1], $color[2], $fill ? 'rg' : 'RG', $x, $y, $width, $height, $fill ? 'f' : 'S');
    }

    private function wrap(string $value, int $limit): array
    {
        $words = preg_split('/\s+/u', trim($value)) ?: [];
        $lines = []; $line = '';
        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);
            if ($line !== '' && mb_strlen($candidate) > $limit) { $lines[] = $line; $line = $word; }
            else $line = $candidate;
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    private function quantity($quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.');
    }

    private function priorityColor(string $priority): array
    {
        return match ($priority) { 'Alta'=>[0.71,0.14,0.09], 'Baja'=>[0.04,0.46,0.32], default=>[0.70,0.39,0.03] };
    }

    private function document(array $pages, string $title): string
    {
        $pageCount = count($pages);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($pages as $index => $content) {
            $pageNumber = $index + 1;
            $content .= $this->text("Pagina {$pageNumber} de {$pageCount}", 485, 24, 8, false, [0.43,0.49,0.46]);
            $pageObject = 5 + ($index * 2); $contentObject = $pageObject + 1;
            $kids[] = "{$pageObject} 0 R";
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = "<< /Length ".strlen($content)." >>\nstream\n{$content}endstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.$pageCount.' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf); $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($number = 1; $number < $size; $number++) $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        $safeTitle = str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $title);
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R /Info << /Title ({$safeTitle}) /Creator (FABULOSA APP) >> >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }
}
