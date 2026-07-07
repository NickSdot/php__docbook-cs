<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

final readonly class Violation
{
    public function __construct(
        public string $sniffCode,
        public string $filePath,
        public int $line,
        public int $beginOffset,
        public int $untilOffset,
        public string $message,
        public ?string $content = null,
        public Severity $severity = Severity::WARNING,
    ) {
    }
}
