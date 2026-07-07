<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Fix\FixResult;
use DocbookCS\Report\FileReport;

final readonly class XmlProcessingResult
{
    public function __construct(
        public FileReport $fileReport,
        public ?FixResult $fixResult = null,
    ) {
    }

    public function hasPendingFixesToPersist(): bool
    {
        return $this->fixResult !== null;
    }

    /** @throws FixerException */
    public function fixedContent(): string
    {
        if ($this->fixResult === null) {
            throw FixerException::cannotReadFixedContent();
        }

        return $this->fixResult->content;
    }
}
