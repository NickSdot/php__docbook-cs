<?php

declare(strict_types=1);

namespace DocbookCS\Report;

final class FileReport
{
    /** @var list<Violation> */
    private array $violations = [];

    public readonly string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $this->makeRelative($filePath);
    }

    public function addViolation(Violation $violation): void
    {
        $this->violations[] = $violation;
    }

    /** @param list<Violation> $violations */
    public function addViolations(array $violations): void
    {
        foreach ($violations as $violation) {
            $this->addViolation($violation);
        }
    }

    /** @return list<Violation> */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function getViolationCount(): int
    {
        return count($this->violations);
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    public function getErrorCount(): int
    {
        return array_filter(
            $this->violations,
            static fn(Violation $v): bool => $v->severity === Severity::ERROR,
        ) |> count(...);
    }

    public function getWarningCount(): int
    {
        return array_filter(
            $this->violations,
            static fn(Violation $v): bool => $v->severity === Severity::WARNING,
        ) |> count(...);
    }

    private function makeRelative(string $filePath): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return $filePath; // @codeCoverageIgnore
        }

        $prefix = rtrim(str_replace('\\', '/', $cwd), '/') . '/';
        $normalized = str_replace('\\', '/', $filePath);

        if (str_starts_with($normalized, $prefix)) {
            return substr($normalized, strlen($prefix));
        }

        return $filePath;
    }
}
