<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Runner\RunMode;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

abstract class AbstractSniff implements SniffInterface
{
    /** @var array<string, string> */
    protected array $properties = [];

    public function __construct(
        public RunMode $mode = RunMode::Sniff,
    ) {
    }

    public function setProperty(string $name, string $value): void
    {
        $this->properties[$name] = $value;
    }

    protected function getProperty(string $name, string $default = ''): string
    {
        return $this->properties[$name] ?? $default;
    }

    /** @throws \LogicException if an invalid severity level is configured */
    protected function createViolation(
        string $filePath,
        int $line,
        string $message,
        Severity $severity = Severity::ERROR,
        int $beginOffset = 0,
        int $untilOffset = 0,
        ?string $content = null,
    ): Violation {
        return new Violation(
            sniffCode: static::getCode(),
            filePath: $filePath,
            line: $line,
            message: $message,
            severity: Severity::tryFrom($this->getProperty('severity', $severity->value))
                ?: throw new \LogicException('Invalid severity level configured for ExceptionNameSniff.'),
            beginOffset: $beginOffset,
            untilOffset: $untilOffset,
            content: $content,
        );
    }
}
