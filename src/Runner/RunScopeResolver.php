<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Path\PathMatcher;

final readonly class RunScopeResolver
{
    private const string ENTITY_PATTERN = '/&([a-zA-Z_][\w.\-]*);/';

    /** @param array<string, string> $entityPaths */
    public function __construct(
        private PathMatcher $pathMatcher,
        private array $entityPaths,
    ) {
    }

    /**
     * @param list<string> $files
     * @param array<string, list<int>>|null $diffLines
     * @return array<string, list<int>|null>
     */
    public function resolve(array $files, ?array $diffLines, bool $strict): array
    {
        $targets = [];

        foreach ($files as $file) {
            if ($diffLines === null) {
                $targets[$file] = null;
                continue;
            }

            $changedLines = $this->changedLinesForFile($file, $diffLines);
            if ($changedLines !== null) {
                $targets[$file] = $changedLines;
            }
        }

        if (!$strict) {
            $this->expandReferencedTargets($targets);
        }

        ksort($targets);

        return $targets;
    }

    /** @param array<string, list<int>|null> $targets */
    private function expandReferencedTargets(array &$targets): void
    {
        $pending = array_keys($targets);
        $visitedFiles = [];
        $visitedEntityPaths = [];

        for ($i = 0; isset($pending[$i]); $i++) {
            $file = $pending[$i];

            if (isset($visitedFiles[$file])) {
                continue;
            }

            $visitedFiles[$file] = true;
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            foreach ($this->targetFilesFromContent($content, $visitedEntityPaths) as $targetFile) {
                if (array_key_exists($targetFile, $targets)) {
                    continue;
                }

                $targets[$targetFile] = null;
                $pending[] = $targetFile;
            }
        }
    }

    /**
     * @param array<string, true> $visitedEntityPaths
     * @return list<string>
     */
    private function targetFilesFromContent(string $content, array &$visitedEntityPaths): array
    {
        if (!preg_match_all(self::ENTITY_PATTERN, $content, $matches)) {
            return [];
        }

        $files = [];

        foreach ($matches[1] as $name) {
            if (!isset($this->entityPaths[$name])) {
                continue;
            }

            foreach ($this->expandEntityPath($this->entityPaths[$name], $visitedEntityPaths) as $file) {
                $files[$file] = true;
            }
        }

        return array_keys($files);
    }

    /**
     * @param array<string, true> $visited
     * @return list<string>
     */
    private function expandEntityPath(string $path, array &$visited): array
    {
        $path = str_replace('\\', '/', $path);

        if (isset($visited[$path]) || !is_file($path)) {
            return [];
        }

        $visited[$path] = true;

        if (str_ends_with($path, '.xml')) {
            return $this->pathMatcher->isIncluded($path) ? [$path] : [];
        }

        $content = @file_get_contents($path);

        return $content !== false
            ? $this->targetFilesFromContent($content, $visited)
            : [];
    }

    /**
     * @param array<string, list<int>> $diffLines
     * @return list<int>|null
     */
    private function changedLinesForFile(string $absolutePath, array $diffLines): ?array
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        foreach ($diffLines as $diffPath => $lines) {
            $normalizedDiff = str_replace('\\', '/', $diffPath);

            if (
                $normalized === $normalizedDiff
                || str_ends_with($normalized, '/' . ltrim($normalizedDiff, '/'))
            ) {
                return $lines;
            }
        }

        return null;
    }
}
