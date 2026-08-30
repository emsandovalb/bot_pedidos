<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class WhatsappApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $responseBody
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $responseBody = [],
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $body = (array) $response->json();

        $message = data_get($body, 'error.message', 'WhatsApp API request failed.');

        return new self($message, $response->status(), $body);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function responseBody(): array
    {
        return $this->responseBody;
    }
}
