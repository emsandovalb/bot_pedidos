<?php

namespace App\Services\Oria;

use RuntimeException;

/** Oria respondió y dijo que no: código vencido, negocio sin Plus, vínculo que ya no existe… */
class OriaRejectedException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
