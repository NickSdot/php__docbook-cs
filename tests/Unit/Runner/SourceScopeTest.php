<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Fix\Fix;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Source\SourceLines;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fix::class)]
#[CoversClass(SourceLines::class)]
#[CoversClass(SourceScope::class)]
final class SourceScopeTest extends TestCase
{
    #[Test]
    public function itIncludesOnlyViolationsIntersectingChangedLines(): void
    {
        $scope = SourceScope::changedLines("one\ntwo\nthree", [2]);

        self::assertTrue($scope->includes($this->violation(4, 7, 2)));
        self::assertFalse($scope->includes($this->violation(8, 13, 3)));
    }

    #[Test]
    public function itKeepsScopeAlignedAfterAnInsertionBeforeIt(): void
    {
        $scope = SourceScope::changedLines("one\ntwo\nthree", [2]);
        $scope = $scope->after([
            new Fix('file.xml', 0, 0, "x\n", 'Test', 1),
        ]);

        self::assertFalse($scope->includes($this->violation(4, 5, 2)));
        self::assertTrue($scope->includes($this->violation(6, 9, 3)));
        self::assertFalse($scope->includes($this->violation(10, 15, 4)));
    }

    #[Test]
    public function itIncludesContentInsertedIntoAnEmptySelectedLine(): void
    {
        $scope = SourceScope::changedLines("root\n", [2]);
        $scope = $scope->after([
            new Fix('file.xml', 5, 5, 'value', 'Test', 2),
        ]);

        self::assertTrue($scope->includes($this->violation(5, 10, 2)));
    }

    private function violation(int $beginOffset, int $untilOffset, int $line): Violation
    {
        return new Violation(
            sniffCode: 'Test',
            filePath: 'file.xml',
            line: $line,
            beginOffset: $beginOffset,
            untilOffset: $untilOffset,
            message: 'Test violation.',
        );
    }
}
