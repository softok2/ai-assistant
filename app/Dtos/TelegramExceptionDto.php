<?php

declare(strict_types=1);

namespace App\Dtos;

use Throwable;

final readonly class TelegramExceptionDto
{
    public function __construct(
        public string $message,
        public string $file,
        public int $line,
        public string $trace
    ) {}

    public static function fromThrowable(Throwable $e): self
    {
        return new self(
            message: $e->getMessage(),
            file: $e->getFile(),
            line: $e->getLine(),
            trace: $e->getTraceAsString()
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getTrace(): string
    {
        return $this->trace;
    }
}
