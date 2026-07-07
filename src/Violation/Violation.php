<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

final readonly class Violation
{
    public function __construct(
        public string $sniffCode,
        public string $filePath,
        public int $line,
        public string $message,
        public Severity $severity = Severity::WARNING,
        public int $beginOffset = 0,
        public int $untilOffset = 0,
        public ?string $content = null,
    ) {
    }
}
