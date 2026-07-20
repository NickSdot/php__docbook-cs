<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final readonly class FileChange
{
    /** @param list<int> $lineNumbers */
    public function __construct(
        public string $filePath,
        public array $lineNumbers,
    ) {
    }
}
