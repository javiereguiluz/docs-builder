<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Renderers;

use phpDocumentor\Guides\NodeRenderers\NodeRenderer;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\Nodes\TitleNode;
use phpDocumentor\Guides\RenderContext;
use phpDocumentor\Guides\TemplateRenderer;

use function assert;
use function is_a;

/** @implements NodeRenderer<TitleNode> */
class TitleNodeRenderer implements NodeRenderer
{
    /** @var array<string, array<string, int>> */
    private static array $idUsagesCountByFilename = [];

    public function __construct(private readonly TemplateRenderer $templateRenderer)
    {
    }

    public static function resetHeaderIdCache(): void
    {
        self::$idUsagesCountByFilename = [];
    }

    public function supports(string $nodeFqcn): bool
    {
        return $nodeFqcn === TitleNode::class || is_a($nodeFqcn, TitleNode::class, true);
    }

    public function render(Node $node, RenderContext $renderContext): string
    {
        assert($node instanceof TitleNode);

        $filename = $renderContext->getCurrentFileName();
        $id = $node->getId();

        $idUsagesCount = self::$idUsagesCountByFilename[$filename][$id] ?? 0;

        if (0 === $idUsagesCount) {
            $computedId = $id;
        } else {
            $computedId = self::slugify($node->toString().'-'.$idUsagesCount);
        }

        self::$idUsagesCountByFilename[$filename][$id] = $idUsagesCount + 1;

        return $this->templateRenderer->renderTemplate(
            $renderContext,
            'header-title.html.twig',
            [
                'titleNode' => $node,
                'id' => $computedId,
            ]
        );
    }

    /**
     * Simple slugification for header IDs, replacing the old Environment::slugify().
     */
    private static function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return $text;
    }
}
