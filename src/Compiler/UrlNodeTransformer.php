<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Compiler;

use phpDocumentor\Guides\Compiler\CompilerContext;
use phpDocumentor\Guides\Compiler\NodeTransformer;
use phpDocumentor\Guides\Nodes\Inline\HyperLinkNode;
use phpDocumentor\Guides\Nodes\Node;
use SymfonyDocsBuilder\CI\UrlChecker;

use function str_contains;
use function str_replace;
use function str_starts_with;

/**
 * Transforms HyperLinkNode URLs during compilation:
 * - Replaces {version} placeholders with the configured Symfony version
 * - Checks external URLs for validity (when UrlChecker is configured)
 * - Adds security attributes (rel, target) for external non-Symfony URLs
 *
 * This replaces the URL-related functionality that was previously in SpanNodeRenderer
 * (which was part of the doctrine/rst-parser integration).
 *
 * @implements NodeTransformer<HyperLinkNode>
 */
class UrlNodeTransformer implements NodeTransformer
{
    public function __construct(
        private readonly ?UrlChecker $urlChecker = null,
        private readonly ?string $symfonyVersion = null,
    ) {
    }

    public function enterNode(Node $node, CompilerContext $compilerContext): Node
    {
        if (!$node instanceof HyperLinkNode) {
            return $node;
        }

        $url = $node->getUrl();
        if ('' === $url) {
            $url = $node->getTargetReference();
        }

        // Replace {version} placeholders with the configured Symfony version
        if (null !== $this->symfonyVersion && str_contains($url, '{version}')) {
            $url = str_replace('{version}', $this->symfonyVersion, $url);
            $node->setUrl($url);
        }

        // Check external URLs for validity
        if (
            null !== $this->urlChecker
            && $this->isExternalUrl($url)
            && !str_starts_with($url, 'http://localhost')
            && !str_starts_with($url, 'http://192.168')
        ) {
            $this->urlChecker->checkUrl($url);
        }

        // Add security attributes for external non-Symfony URLs
        if (!$this->isSafeUrl($url)) {
            $node = $node->withOptions([
                'rel' => 'external noopener noreferrer',
                'target' => '_blank',
            ]);
        }

        return $node;
    }

    public function leaveNode(Node $node, CompilerContext $compilerContext): Node|null
    {
        return $node;
    }

    public function supports(Node $node): bool
    {
        return $node instanceof HyperLinkNode;
    }

    public function getPriority(): int
    {
        return 1000;
    }

    private function isExternalUrl(string $url): bool
    {
        return str_contains($url, '://');
    }

    /**
     * If the URL is considered safe, it's opened in the same browser tab;
     * otherwise it's opened in a new tab and with some strict security options.
     */
    private function isSafeUrl(string $url): bool
    {
        // The following are considered Symfony URLs:
        //   * https://symfony.com/[...]
        //   * https://[...].symfony.com/ (e.g. insight.symfony.com, etc.)
        //   * https://symfony.wip/[...]  (used for internal/local development)
        $isSymfonyUrl = (bool) preg_match('{^http(s)?://(.*\.)?symfony.(com|wip)}', $url);
        $isRelativeUrl = !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://');

        return $isSymfonyUrl || $isRelativeUrl;
    }
}
