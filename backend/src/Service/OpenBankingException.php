<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Exception for PSD2 Open Banking API failures.
 */
class OpenBankingException extends \RuntimeException
{
    private ?string $bankErrorCode;
    private ?string $bankMessage;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $bankErrorCode = null,
        ?string $bankMessage = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->bankErrorCode = $bankErrorCode;
        $this->bankMessage = $bankMessage;
    }

    public function getBankErrorCode(): ?string { return $this->bankErrorCode; }
    public function getBankMessage(): ?string { return $this->bankMessage; }
}
