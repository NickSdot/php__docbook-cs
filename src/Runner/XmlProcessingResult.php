<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Source\File;

final readonly class XmlProcessingResult
{
    public function __construct(
        public FileReport $fileReport,
        public File $file,
        public bool $modified,
    ) {
    }

    public function hasPendingFixesToPersist(): bool
    {
        return $this->modified;
    }

    /** @throws FixerException */
    public function fixedContent(): string
    {
        if (!$this->modified) {
            throw FixerException::cannotReadFixedContent();
        }

        return $this->file->content;
    }
}
