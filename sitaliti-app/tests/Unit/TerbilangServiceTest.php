<?php

namespace Tests\Unit;

use App\Services\TerbilangService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TerbilangServiceTest extends TestCase
{
    public function test_formats_zero_and_standard_sppd_amounts(): void
    {
        $service = new TerbilangService;

        $this->assertSame('# Nol rupiah #', $service->format(0));
        $this->assertSame('# Tujuh puluh ribu rupiah #', $service->format(70000));
        $this->assertSame('# Seratus ribu rupiah #', $service->format(100000));
        $this->assertSame('# Tiga ratus tujuh puluh ribu rupiah #', $service->format(370000));
        $this->assertSame('# Satu juta dua ratus lima puluh ribu lima ratus rupiah #', $service->format(1250500));
    }

    public function test_throws_on_negative_amount(): void
    {
        $service = new TerbilangService;

        $this->expectException(InvalidArgumentException::class);
        $service->format(-1000);
    }
}
