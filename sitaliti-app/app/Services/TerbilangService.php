<?php

namespace App\Services;

use InvalidArgumentException;

class TerbilangService
{
    /**
     * @var list<string>
     */
    private const UNITS = [
        '',
        'satu',
        'dua',
        'tiga',
        'empat',
        'lima',
        'enam',
        'tujuh',
        'delapan',
        'sembilan',
        'sepuluh',
        'sebelas',
    ];

    /**
     * Mengonversi nominal rupiah bulat menjadi teks terbilang resmi berformat `# ... rupiah #`.
     */
    public function format(int|float $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Nominal terbilang tidak boleh negatif.');
        }

        $rounded = (int) round($amount);
        if ($rounded === 0) {
            return '# Nol rupiah #';
        }

        $words = trim($this->toWords($rounded));

        return '# '.ucfirst($words).' rupiah #';
    }

    /**
     * Mengonversi bilangan bulat non-negatif menjadi kata-kata Bahasa Indonesia.
     */
    public function toWords(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Bilangan terbilang tidak boleh negatif.');
        }

        if ($number === 0) {
            return 'nol';
        }

        return trim($this->convertRecursive($number));
    }

    private function convertRecursive(int $n): string
    {
        if ($n < 12) {
            return self::UNITS[$n];
        }

        if ($n < 20) {
            return $this->convertRecursive($n - 10).' belas';
        }

        if ($n < 100) {
            $tens = intdiv($n, 10);
            $rem = $n % 10;

            return trim($this->convertRecursive($tens).' puluh '.$this->convertRecursive($rem));
        }

        if ($n < 200) {
            return trim('seratus '.$this->convertRecursive($n - 100));
        }

        if ($n < 1000) {
            $hundreds = intdiv($n, 100);
            $rem = $n % 100;

            return trim($this->convertRecursive($hundreds).' ratus '.$this->convertRecursive($rem));
        }

        if ($n < 2000) {
            return trim('seribu '.$this->convertRecursive($n - 1000));
        }

        if ($n < 1000000) {
            $thousands = intdiv($n, 1000);
            $rem = $n % 1000;

            return trim($this->convertRecursive($thousands).' ribu '.$this->convertRecursive($rem));
        }

        if ($n < 1000000000) {
            $millions = intdiv($n, 1000000);
            $rem = $n % 1000000;

            return trim($this->convertRecursive($millions).' juta '.$this->convertRecursive($rem));
        }

        $billions = intdiv($n, 1000000000);
        $rem = $n % 1000000000;

        return trim($this->convertRecursive($billions).' miliar '.$this->convertRecursive($rem));
    }
}
