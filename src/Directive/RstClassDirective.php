<?php

namespace SymfonyDocsBuilder\Directive;

use phpDocumentor\Guides\Nodes\ClassNode;
use phpDocumentor\Guides\Nodes\CollectionNode;
use phpDocumentor\Guides\Nodes\DocumentNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Directives\SubDirective;
use phpDocumentor\Guides\RestructuredText\Parser\BlockContext;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\Rule;
use Symfony\Component\String\Slugger\AsciiSlugger;

use function array_map;
use function array_merge;
use function explode;

/**
 * Allows you to add custom classes to the next directive.
 */
class RstClassDirective extends SubDirective
{
    public function __construct(protected Rule $startingRule)
    {
        parent::__construct($startingRule);
    }

    public function getName(): string
    {
        return 'rst-class';
    }

    /** @return string[] */
    public function getAliases(): array
    {
        return ['class'];
    }

    protected function processSub(
        BlockContext $blockContext,
        CollectionNode $collectionNode,
        Directive $directive,
    ): Node|null {
        $classes = explode(' ', $directive->getData());

        $slugger = new AsciiSlugger();
        $normalizedClasses = array_map(
            fn (string $class): string => $slugger->slug($class)->lower()->toString(),
            $classes,
        );

        $collectionNode->setClasses($normalizedClasses);

        if ($collectionNode->getChildren() === []) {
            $classNode = new ClassNode($directive->getData());
            $classNode->setClasses($classes);

            return $classNode;
        }

        $this->setNodesClasses($collectionNode->getChildren(), $classes);

        return new CollectionNode($collectionNode->getChildren());
    }

    /**
     * @param Node[] $nodes
     * @param string[] $classes
     */
    private function setNodesClasses(array $nodes, array $classes): void
    {
        foreach ($nodes as $node) {
            $node->setClasses(array_merge($node->getClasses(), $classes));

            if (!($node instanceof DocumentNode)) {
                continue;
            }

            $this->setNodesClasses($node->getNodes(), $classes);
        }
    }
}
