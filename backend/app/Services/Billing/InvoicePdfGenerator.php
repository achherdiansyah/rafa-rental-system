<?php

namespace App\Services\Billing;

use App\Models\Invoice;

/**
 * Minimal dependency-free A4 invoice PDF (PRD API: GET /invoices/{id}/pdf).
 *
 * Builds a valid single-page PDF with a byte-accurate xref table.
 *
 * ponytail: single Helvetica font, ASCII-only; swap to a template engine /
 * dompdf when the layout spec is finalised.
 */
class InvoicePdfGenerator
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('details', 'booking.projectLocation');
        $booking = $invoice->booking;
        $location = $booking?->projectLocation;

        $table = [
            ['DESKRIPSI', 'QTY', 'HARGA', 'SUBTOTAL'],
        ];
        foreach ($invoice->details as $detail) {
            $table[] = [
                $this->toAscii(substr($this->clean($detail->description), 0, 34)),
                number_format((float) $detail->quantity, 2),
                number_format((float) $detail->unit_price, 2, ',', '.'),
                number_format((float) $detail->subtotal, 2, ',', '.'),
            ];
        }

        $meta = [
            ['Invoice', $this->toAscii($invoice->invoice_number)],
            ['Tipe', $this->toAscii($invoice->invoice_type->value)],
            ['Status', $this->toAscii($invoice->status->value)],
            ['Booking', $this->toAscii($booking?->booking_code ?? '-')],
            ['Proyek', $this->toAscii(($location?->project_name ?? '-').' / '.($location?->city ?? ''))],
            ['Terbit', $this->toAscii($invoice->issued_at?->toDateTimeString() ?? '-')],
            ['Jatuh Tempo', $this->toAscii($invoice->due_at?->toDateTimeString() ?? '-')],
        ];

        $totals = [
            ['SUBTOTAL', (float) $invoice->subtotal],
            ['TAX', (float) $invoice->tax_total],
            ['GRAND TOTAL', (float) $invoice->grand_total],
            ['TERBAYAR', (float) $invoice->paid_amount],
            ['SISA TAGIHAN', (float) ($invoice->grand_total - $invoice->paid_amount)],
        ];

        return $this->buildPdf($meta, $table, $totals);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $meta
     * @param  array<int, array<int, string>>  $table
     * @param  array<int, array{0: string, 1: float}>  $totals
     */
    private function buildPdf(array $meta, array $table, array $totals): string
    {
        $content = "BT /F1 14 Tf 40 800 Td (RAFA RENTAL SYSTEM) Tj ET\n".
            "BT /F1 10 Tf 40 786 Td (===============================================) Tj ET\n".
            "BT /F1 12 Tf 40 762 Td (INVOICE) Tj ET\n";

        $esc = fn (string $s) => str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $s);

        $y = 742;
        foreach ($meta as [$k, $v]) {
            $content .= "BT /F1 9 Tf 40 {$y} Td (".str_pad($k, 14).': '.$esc($v).') Tj ET'."\n";
            $y -= 15;
        }

        $y -= 8;
        foreach ($table as $row) {
            $content .= "BT /F1 8 Tf 40 {$y} Td (".str_pad($esc($row[0] ?? ''), 38).
                str_pad((string) ($row[1] ?? ''), 12, ' ', STR_PAD_LEFT).
                str_pad((string) ($row[2] ?? ''), 14, ' ', STR_PAD_LEFT).
                str_pad((string) ($row[3] ?? ''), 14, ' ', STR_PAD_LEFT).') Tj ET'."\n";
            $y -= 14;
        }

        foreach ($totals as [$k, $value]) {
            $content .= "BT /F1 9 Tf 40 {$y} Td (".str_pad($k, 20).': '.number_format($value, 2, ',', '.').') Tj ET'."\n";
            $y -= 16;
        }

        $content .= "BT /F1 8 Tf 40 {$y} Td (Dokumen ini dihasilkan sistem dan tidak dapat diubah.) Tj ET\n";

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[5] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';

        $output = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($output);
            $output .= "{$num} 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $output .= "xref\n0 6\n".
            "0000000000 65535 f \n";
        foreach ([1, 2, 3, 4, 5] as $num) {
            $output .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }

        $output .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $output;
    }

    private function clean(string $text): string
    {
        return (string) preg_replace('/\s+/', ' ', $text);
    }

    private function toAscii(string $text): string
    {
        return (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}
