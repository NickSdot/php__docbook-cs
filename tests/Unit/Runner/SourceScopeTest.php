<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Fix\Fix;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fix::class)]
#[CoversClass(File::class)]
#[CoversClass(Line::class)]
#[CoversClass(SourceScope::class)]
final class SourceScopeTest extends TestCase
{
    #[Test]
    public function itIncludesOnlyViolationsIntersectingChangedLines(): void
    {
        $file = new File('file.xml', "one\ntwo\nthree");
        $scope = SourceScope::changedLines($file, [2]);

        self::assertFalse($scope->isWholeFile());
        self::assertSame([2], $scope->lineNumbers($file));
        self::assertTrue($scope->includes($this->violation(4, 7, 2)));
        self::assertFalse($scope->includes($this->violation(8, 13, 3)));
    }

    #[Test]
    public function wholeFileScopeIncludesEveryLine(): void
    {
        $file = new File('file.xml', "one\ntwo\nthree");
        $scope = SourceScope::wholeFile();

        self::assertTrue($scope->isWholeFile());
        self::assertSame([1, 2, 3], $scope->lineNumbers($file));
    }

    #[Test]
    public function anEmptyChangedLineSetSelectsNoLines(): void
    {
        $file = new File('file.xml', "one\ntwo\nthree");
        $scope = SourceScope::changedLines($file, []);

        self::assertSame([], $scope->lineNumbers($file));
    }

    #[Test]
    public function itKeepsScopeAlignedAfterAnInsertionBeforeIt(): void
    {
        $file = new File('file.xml', "one\ntwo\nthree");
        $scope = SourceScope::changedLines($file, [2]);
        $scope = $scope->after([
            new Fix('file.xml', 0, 0, "x\n", 'Test'),
        ]);
        $file = $file->withContent("x\none\ntwo\nthree");

        self::assertSame([3], $scope->lineNumbers($file));
        self::assertFalse($scope->includes($this->violation(4, 5, 2)));
        self::assertTrue($scope->includes($this->violation(6, 9, 3)));
        self::assertFalse($scope->includes($this->violation(10, 15, 4)));
    }

    #[Test]
    public function itIncludesContentInsertedIntoAnEmptySelectedLine(): void
    {
        $file = new File('file.xml', "root\n");
        $scope = SourceScope::changedLines($file, [2]);
        $scope = $scope->after([
            new Fix('file.xml', 5, 5, 'value', 'Test'),
        ]);

        self::assertTrue($scope->includes($this->violation(5, 10, 2)));
    }

    #[Test]
    public function itSortsFixesBeforeMappingScopeOffsets(): void
    {
        $file = new File('file.xml', "one\ntwo\nthree");
        $scope = SourceScope::changedLines($file, [2]);
        $scope = $scope->after([
            new Fix('file.xml', 13, 13, "\nfour", 'Test'),
            new Fix('file.xml', 0, 0, "zero\n", 'Test'),
        ]);
        $file = $file->withContent("zero\none\ntwo\nthree\nfour");

        self::assertSame([3], $scope->lineNumbers($file));
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
