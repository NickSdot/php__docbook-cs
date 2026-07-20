<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final class GitDiffProvider
{
    /** @var \Closure(list<string>, string): array{exitCode: int, stdout: string, stderr: string} */
    private \Closure $execute;

    /**
     * @param null|\Closure(list<string>, string): array{exitCode: int, stdout: string, stderr: string} $execute
     */
    public function __construct(?\Closure $execute = null)
    {
        $this->execute = $execute ?? $this->executeCommand(...);
    }

    /** @throws \RuntimeException if the repository, branch point, or diff cannot be determined. */
    public function for(string $pwd): string
    {
        $repositoryRoot = trim($this->commandOutput(
            ['git', 'rev-parse', '--show-toplevel'],
            $pwd,
            'Could not find Git repository.'
        ));

        $baseReference = $this->resolveBaseReference($repositoryRoot);

        $error = sprintf('Unclear where HEAD branched from %s.', $baseReference);

        $mergeBase = trim($this->commandOutput(
            ['git', 'merge-base', 'HEAD', $baseReference],
            $repositoryRoot,
            $error,
        ));

        return $this->commandOutput(
            ['git', 'diff', '--no-ext-diff', '--no-color', $mergeBase, '--'],
            $repositoryRoot,
            'Could not read diff.',
        );
    }

    /** @throws \RuntimeException if no default branch reference exists. */
    private function resolveBaseReference(string $repositoryRoot): string
    {
        $candidates = [];

        foreach (['upstream', 'origin'] as $remote) {
            $result = ($this->execute)(
                ['git', 'symbolic-ref', '--quiet', sprintf('refs/remotes/%s/HEAD', $remote)],
                $repositoryRoot,
            );

            if ($result['exitCode'] === 0) {
                $candidates[] = trim($result['stdout']);
            }

            $candidates[] = sprintf('refs/remotes/%s/main', $remote);
            $candidates[] = sprintf('refs/remotes/%s/master', $remote);
        }

        $candidates[] = 'refs/heads/main';
        $candidates[] = 'refs/heads/master';

        foreach (array_unique($candidates) as $candidate) {
            $result = ($this->execute)(
                ['git', 'rev-parse', '--verify', '--quiet', $candidate . '^{commit}'],
                $repositoryRoot,
            );

            if ($result['exitCode'] === 0) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'Could not determine the upstream default branch for the contribution diff.',
        );
    }

    /**
     * @param list<string> $cmd
     *
     * @throws \RuntimeException if the command fails.
     */
    private function commandOutput(array $cmd, string $pwd, string $error): string
    {
        $result = ($this->execute)($cmd, $pwd);

        if ($result['exitCode'] === 0) {
            return $result['stdout'];
        }

        $detail = trim($result['stderr']);

        throw new \RuntimeException(
            $detail !== '' ? "$error $detail" : $error,
        );
    }

    /**
     * @param list<string> $cmd
     * @return array{exitCode: int, stdout: string, stderr: string}
     * @throws \RuntimeException if Git cannot be started.
     */
    private function executeCommand(array $cmd, string $pwd): array
    {
        $process = proc_open(
            $cmd,
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w'],
            ],
            $pipes,
            $pwd,
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start Git.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout !== false ? $stdout : '',
            'stderr' => $stderr !== false ? $stderr : '',
        ];
    }
}
