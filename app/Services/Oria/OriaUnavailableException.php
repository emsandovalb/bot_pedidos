<?php

namespace App\Services\Oria;

use RuntimeException;

/** Oria no respondió, respondió con error o no está configurada. */
class OriaUnavailableException extends RuntimeException {}
