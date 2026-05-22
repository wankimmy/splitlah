<?php

namespace Tests\Unit;

use App\Services\Receipt\ReceiptParserService;
use Tests\TestCase;

class ReceiptParserServiceTest extends TestCase
{
    public function test_parses_total_from_sample_ocr(): void
    {
        $text = <<<'TXT'
RESTORAN NASI KANDAR MAJU
21/05/2026
NASI KANDAR AYAM MADU 18.90
TOTAL RM86.40
THANK YOU
TXT;
        $parsed = (new ReceiptParserService)->parse($text);
        $this->assertEquals(8640, $parsed['total_cents']);
        $this->assertNotEmpty($parsed['merchant_name']);
    }

    public function test_ignores_noise_lines(): void
    {
        $text = "THANK YOU\nCASH\nTOTAL RM12.90\n";
        $parsed = (new ReceiptParserService)->parse($text);
        $this->assertEquals(1290, $parsed['total_cents']);
    }
}
