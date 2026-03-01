<?php

namespace SymfonyDocsBuilder;

use phpDocumentor\Guides\Nodes\ProjectNode;

class BuildResult
{
    private array $errors = [];
    private array $jsonResults = [];
    private ?string $stringResult = null;

    public function __construct(private readonly ProjectNode $projectNode)
    {
    }

    public function appendError(string $errorMessage): void
    {
        $this->errors[] = $errorMessage;
    }

    public function prependError(string $errorMessage): void
    {
        $this->errors = array_merge([$errorMessage], $this->errors);
    }

    public function isSuccessful(): bool
    {
        return 0 === \count($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorTrace(): string
    {
        return implode("\n", $this->errors);
    }

    public function getProjectNode(): ProjectNode
    {
        return $this->projectNode;
    }

    public function getJsonResults(): array
    {
        return $this->jsonResults;
    }

    public function setJsonResults(array $jsonResults): void
    {
        $this->jsonResults = $jsonResults;
    }

    public function getStringResult(): ?string
    {
        return $this->stringResult;
    }

    public function setStringResult(string $result): void
    {
        $this->stringResult = $result;
    }
}
