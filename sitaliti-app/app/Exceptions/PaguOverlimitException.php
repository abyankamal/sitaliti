<?php

namespace App\Exceptions;

use RuntimeException;

class PaguOverlimitException extends RuntimeException
{
    public function __construct(
        string $message = 'Alokasi pagu anggaran tidak mencukupi atau melanggar jadwal kas RAK.',
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

