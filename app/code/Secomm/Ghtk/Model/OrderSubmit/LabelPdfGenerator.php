<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

/**
 * Generates the shipping-label PDF the native Magento label flow requires
 * (SL-016 / DEC-SL016-001): LabelGenerator only persists tracking + label
 * when the carrier response carries BOTH a tracking number and PDF bytes.
 *
 * This is an internal fallback label (tracking + parties + addresses) — the
 * official GHTK label remains available in the GHTK dashboard; swapping in
 * the GHTK-provided PDF is a follow-up once its endpoint is verified (Q-EXT).
 *
 * Core PDF fonts cover Latin-1 only, so Vietnamese diacritics are
 * transliterated to ASCII for the printed text.
 */
class LabelPdfGenerator
{
    /**
     * @param array<int, string> $lines Address/party lines printed below the tracking number.
     * @return string Raw PDF bytes ("%PDF-…" — what combineLabelsPdf expects).
     */
    public function generate(string $trackingNumber, string $labelId, string $partnerOrderId, array $lines): string
    {
        $pdf = new \Zend_Pdf();
        $page = $pdf->newPage('420:320:'); // portrait label size, points
        $pdf->pages[] = $page;

        $font = \Zend_Pdf_Font::fontWithName(\Zend_Pdf_Font::FONT_HELVETICA_BOLD);
        $page->setFont($font, 20);
        $this->drawLine($page, $this->ascii('GHTK ' . $trackingNumber), 30, 280);

        $page->setFont($font, 9);
        $y = 250;
        foreach ([$this->ascii('Label: ' . $labelId), $this->ascii('Partner: ' . $partnerOrderId)] as $header) {
            $this->drawLine($page, $header, 30, $y);
            $y -= 14;
        }

        $regular = \Zend_Pdf_Font::fontWithName(\Zend_Pdf_Font::FONT_HELVETICA);
        $page->setFont($regular, 9);
        foreach ($lines as $line) {
            $this->drawLine($page, $this->ascii((string) $line), 30, $y);
            $y -= 12;
            if ($y < 20) {
                break;
            }
        }

        return $pdf->render();
    }

    private function drawLine(\Zend_Pdf_Page $page, string $text, float $x, float $y): void
    {
        $text = substr($text, 0, 60); // keep within the label width
        $page->drawText($text, $x, $y, 'UTF-8');
    }

    /**
     * Core-font safe text: strip diacritics to plain ASCII.
     */
    private function ascii(string $text): string
    {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($translit) && $translit !== '') {
            return $translit;
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }
}
