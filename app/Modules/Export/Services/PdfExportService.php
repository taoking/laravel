<?php

namespace App\Modules\Export\Services;

class PdfExportService
{
    /**
     * @param  list<string>  $lines
     */
    public function build(string $title, array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 790 Td\n16 TL\n";

        foreach (array_slice([$title, ...$lines], 0, 45) as $line) {
            $content .= '('.$this->escapeText($line).") Tj\nT*\n";
        }

        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}endstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapeText(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }
}
