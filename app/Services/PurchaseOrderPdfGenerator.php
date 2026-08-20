<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class PurchaseOrderPdfGenerator
{
    public function render(PurchaseOrder $order): string
    {
        $order->loadMissing(['supplier', 'bankAccount', 'items', 'creator']);
        $content = $this->header($order);
        $y = 708;
        $content .= $this->band('DATOS DEL PROVEEDOR Y DESTINO', $y); $y -= 37;
        $content .= $this->field('PROVEEDOR', $order->supplier->name, 38, $y, 250).$this->field('RUC', $order->supplier->document_number, 310, $y, 247); $y -= 43;
        $content .= $this->field('SUCURSAL DESTINO', $order->destination_branch, 38, $y, 250).$this->field('ALMACÉN DESTINO', $order->destination_warehouse, 310, $y, 247); $y -= 43;
        $bank = $order->bankAccount ? $order->bankAccount->bank_name.' · '.$order->bankAccount->account_type.' · '.$order->bankAccount->account_number : 'No registrada';
        $content .= $this->field('CUENTA BANCARIA', $bank, 38, $y, 519); $y -= 53;
        $content .= $this->band('DETALLE DE ÍTEMS', $y); $y -= 28;
        $content .= $this->tableHeader($y); $y -= 24;
        $pages = [];
        foreach ($order->items as $index => $item) {
            $description = $this->wrap($item->product_name.($item->description ? ' · '.$item->description : ''), 37);
            $height = max(30, 14 + count($description) * 11);
            if ($y - $height < 128) {
                $pages[] = $content;
                $content = $this->header($order); $y = 708;
                $content .= $this->band('DETALLE DE ÍTEMS · CONTINUACIÓN', $y); $y -= 28;
                $content .= $this->tableHeader($y); $y -= 24;
            }
            $content .= $this->rect(38, $y - $height + 7, 519, $height, false, [0.82, 0.86, 0.92]);
            $content .= $this->text((string) ($index + 1), 47, $y - 9, 8);
            $content .= $this->text($this->number($item->quantity), 73, $y - 9, 8);
            $content .= $this->text($item->unit, 111, $y - 9, 8);
            foreach ($description as $line => $value) $content .= $this->text($value, 165, $y - 9 - ($line * 11), 8, $line === 0);
            $content .= $this->text($item->cost_center ?: '-', 337, $y - 9, 8);
            $content .= $this->text($this->money($item->unit_price), 433, $y - 9, 8);
            $content .= $this->text($this->money($item->total), 503, $y - 9, 8, true);
            $y -= $height;
        }
        if ($y < 185) { $pages[] = $content; $content = $this->header($order); $y = 708; }
        $y -= 12;
        $content .= $this->summary($order, $y); $y -= 103;
        $content .= $this->rect(38, $y - 35, 519, 55, false, [0.82, 0.86, 0.92]);
        $content .= $this->text('OBSERVACIONES / CONDICIONES', 47, $y + 9, 8, true, [0.18, 0.29, 0.47]);
        $content .= $this->text('Documento emitido desde FABULOSA APP. Condición de pago: '.$order->payment_condition.'.', 47, $y - 8, 8, false, [0.28, 0.35, 0.46]);
        $content .= $this->text('Elaborado por: '.($order->creator?->name ?: 'Usuario del sistema'), 47, $y - 21, 8, false, [0.28, 0.35, 0.46]);
        $content .= $this->text('APROBACIÓN', 419, $y - 54, 8, true, [0.18, 0.29, 0.47]).$this->line(397, $y - 45, 535, $y - 45, [0.45, 0.52, 0.63]);
        $pages[] = $content;

        return $this->document($pages, $order->code);
    }

    private function header(PurchaseOrder $order): string
    {
        $title = $order->document === 'OS' ? 'ORDEN DE SERVICIO' : 'ORDEN DE COMPRA';
        return $this->rect(0, 778, 595, 64, true, [0.07, 0.18, 0.36])
            .$this->rect(0, 771, 595, 7, true, [0.16, 0.48, 0.78])
            .$this->text('FABULOSA', 38, 810, 18, true, [1, 1, 1])
            .$this->text('GESTIÓN DE ABASTECIMIENTO', 38, 792, 8, true, [0.73, 0.84, 0.96])
            .$this->text($title, 330, 810, 12, true, [1, 1, 1])
            .$this->text('N° '.$order->series.'-'.$order->number, 406, 792, 10, true, [0.86, 0.94, 1])
            .$this->text('Fecha de emisión: '.$order->created_at->format('d/m/Y'), 38, 753, 8, false, [0.34, 0.41, 0.53])
            .$this->text('Moneda: '.($order->currency === 'USD' ? 'DÓLARES AMERICANOS' : 'SOLES'), 385, 753, 8, true, [0.34, 0.41, 0.53]);
    }

    private function band(string $title, float $y): string { return $this->rect(38, $y - 11, 519, 24, true, [0.92, 0.96, 1]).$this->text($title, 47, $y - 2, 9, true, [0.10, 0.31, 0.57]); }
    private function field(string $label, string $value, float $x, float $y, float $width): string { return $this->rect($x, $y - 25, $width, 36, false, [0.82, 0.86, 0.92]).$this->text($label, $x + 8, $y + 1, 7, true, [0.35, 0.43, 0.55]).$this->text($this->wrap($value, (int) ($width / 6.6))[0] ?? '-', $x + 8, $y - 15, 9, true, [0.10, 0.16, 0.26]); }
    private function tableHeader(float $y): string { return $this->rect(38, $y - 15, 519, 24, true, [0.15, 0.32, 0.56]).$this->text('N°', 46, $y - 6, 7, true, [1,1,1]).$this->text('CANT.', 71, $y - 6, 7, true, [1,1,1]).$this->text('UNIDAD', 110, $y - 6, 7, true, [1,1,1]).$this->text('PRODUCTO / SERVICIO', 165, $y - 6, 7, true, [1,1,1]).$this->text('CENTRO COSTO', 337, $y - 6, 7, true, [1,1,1]).$this->text('P. UNIT.', 429, $y - 6, 7, true, [1,1,1]).$this->text('TOTAL', 505, $y - 6, 7, true, [1,1,1]); }
    private function summary(PurchaseOrder $order, float $y): string { $symbol = $order->currency === 'USD' ? 'US$' : 'S/'; return $this->rect(350, $y - 72, 207, 83, true, [0.95, 0.98, 1]).$this->text('SUBTOTAL', 365, $y - 7, 8, true, [0.28,0.35,0.46]).$this->text($symbol.' '.$this->money($order->subtotal), 480, $y - 7, 9, true).$this->text('IGV '.($order->tax_exempt ? '(EXONERADO)' : '(18%)'), 365, $y - 28, 8, true, [0.28,0.35,0.46]).$this->text($symbol.' '.$this->money($order->tax), 480, $y - 28, 9, true).$this->line(362, $y - 38, 545, $y - 38, [0.62,0.70,0.80]).$this->text('TOTAL', 365, $y - 56, 10, true, [0.10,0.31,0.57]).$this->text($symbol.' '.$this->money($order->total), 474, $y - 56, 12, true, [0.10,0.31,0.57]); }
    private function text(string $value, float $x, float $y, float $size = 10, bool $bold = false, array $color = [0.12,0.16,0.24]): string { $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value; $escaped = str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $encoded); return sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $color[0], $color[1], $color[2], $x, $y, $escaped); }
    private function rect(float $x, float $y, float $width, float $height, bool $fill, array $color): string { return sprintf("%.3F %.3F %.3F %s %.2F %.2F %.2F %.2F re %s\n", $color[0], $color[1], $color[2], $fill ? 'rg' : 'RG', $x, $y, $width, $height, $fill ? 'f' : 'S'); }
    private function line(float $x1, float $y1, float $x2, float $y2, array $color): string { return sprintf("%.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S\n", $color[0], $color[1], $color[2], $x1, $y1, $x2, $y2); }
    private function wrap(string $value, int $limit): array { $words = preg_split('/\s+/u', trim($value)) ?: []; $lines=[]; $line=''; foreach ($words as $word) { $candidate=trim($line.' '.$word); if ($line !== '' && mb_strlen($candidate)>$limit) {$lines[]=$line;$line=$word;} else {$line=$candidate;} } if ($line!=='') $lines[]=$line; return $lines ?: ['']; }
    private function money($value): string { return number_format((float) $value, 2, '.', ','); }
    private function number($value): string { return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'); }
    private function document(array $pages, string $title): string { $count=count($pages); $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>']; $kids=[]; foreach($pages as $i=>$content){$page=5+$i*2;$stream=$page+1;$content.=$this->text('Página '.($i+1).' de '.$count,470,24,8,false,[.35,.43,.55]);$kids[]="$page 0 R";$objects[$page]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $stream 0 R >>";$objects[$stream]='<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";} $objects[2]='<< /Type /Pages /Count '.$count.' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offset=[0];foreach($objects as $number=>$object){$offset[$number]=strlen($pdf);$pdf.="$number 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$size=max(array_keys($objects))+1;$pdf.="xref\n0 $size\n0000000000 65535 f \n";for($number=1;$number<$size;$number++)$pdf.=sprintf('%010d 00000 n ', $offset[$number])."\n";$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$title);return $pdf."trailer\n<< /Size $size /Root 1 0 R /Info << /Title ($safe) /Creator (FABULOSA APP) >> >>\nstartxref\n$xref\n%%EOF"; }
}
