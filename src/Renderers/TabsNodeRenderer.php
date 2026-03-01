<?php

declare(strict_types=1);

namespace SymfonyDocsBuilder\Renderers;

use phpDocumentor\Guides\NodeRenderers\NodeRenderer;
use phpDocumentor\Guides\NodeRenderers\NodeRendererFactory;
use phpDocumentor\Guides\NodeRenderers\NodeRendererFactoryAware;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RenderContext;
use phpDocumentor\Guides\TemplateRenderer;
use Symfony\Component\String\Slugger\AsciiSlugger;
use SymfonyDocsBuilder\Node\TabNode;
use SymfonyDocsBuilder\Node\TabsNode;

use function assert;
use function is_a;

/** @implements NodeRenderer<TabsNode> */
class TabsNodeRenderer implements NodeRenderer, NodeRendererFactoryAware
{
    private ?NodeRendererFactory $nodeRendererFactory = null;

    public function __construct(private readonly TemplateRenderer $templateRenderer)
    {
    }

    public function setNodeRendererFactory(NodeRendererFactory $nodeRendererFactory): void
    {
        $this->nodeRendererFactory = $nodeRendererFactory;
    }

    public function supports(string $nodeFqcn): bool
    {
        return $nodeFqcn === TabsNode::class || is_a($nodeFqcn, TabsNode::class, true);
    }

    public function render(Node $node, RenderContext $renderContext): string
    {
        assert($node instanceof TabsNode);
        assert($this->nodeRendererFactory !== null);

        $slugger = new AsciiSlugger();
        $tabs = [];
        foreach ($node->getTabs() as $tabNode) {
            assert($tabNode instanceof TabNode);

            $tabSlug = $slugger->slug($tabNode->getTabName())->lower()->toString();

            $renderedContent = '';
            foreach ($tabNode->getChildren() as $child) {
                $renderedContent .= $this->nodeRendererFactory->get($child)->render($child, $renderContext);
            }

            $tabs[] = [
                'label' => $tabNode->getTabName(),
                'slug' => $tabSlug,
                'hash' => hash('xxh128', $tabSlug.$renderedContent),
                'content' => $renderedContent,
            ];
        }

        return $this->templateRenderer->renderTemplate(
            $renderContext,
            'directives/tabs.html.twig',
            [
                'title' => $node->getTitle(),
                'tabs' => $tabs,
            ]
        );
    }
}
