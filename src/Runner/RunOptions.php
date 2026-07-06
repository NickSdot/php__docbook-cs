<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

final readonly class RunOptions
{
    /**
     * @param list<string>|null $overridePaths
     * @param array<string, list<int>>|null $diffLines
     */
    public function __construct(
        public RunMode $mode = RunMode::Sniff,
        public ?array $overridePaths = null,
        public ?array $diffLines = null,
        public bool $strict = false,
    ) {
    }
}
