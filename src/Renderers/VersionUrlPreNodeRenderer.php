<?php

declare(strict_types=1);

namespace SymfonyDocsBuilder\Renderers;

use phpDocumentor\Guides\Nodes\Inline\HyperLinkNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\NodeRenderers\PreRenderers\PreNodeRenderer;
use phpDocumentor\Guides\RenderContext;

/**
 * Replaces {version} placeholders in HyperLinkNode URLs with the configured
 * Symfony version. Runs during rendering (after ReferenceResolverPreRender
 * has resolved URLs from target references), so the replacement is not
 * overwritten.
 */
class VersionUrlPreNodeRenderer implements PreNodeRenderer
{
    public function __construct(private readonly ?string $symfonyVersion = null)
    {
    }

    public function supports(Node $node): bool
    {
        return $node instanceof HyperLinkNode;
    }

    public function execute(Node $node, RenderContext $renderContext): Node
    {
        \assert($node instanceof HyperLinkNode);

        if (null === $this->symfonyVersion) {
            return $node;
        }

        $url = $node->getUrl();
        if (str_contains($url, '{version}')) {
            $node->setUrl(str_replace('{version}', $this->symfonyVersion, $url));
        }

        return $node;
    }
}
