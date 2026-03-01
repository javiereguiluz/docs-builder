<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Directive;

use phpDocumentor\Guides\Nodes\CollectionNode;
use phpDocumentor\Guides\Nodes\FigureNode;
use phpDocumentor\Guides\Nodes\ImageNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\ReferenceResolvers\DocumentNameResolverInterface;
use phpDocumentor\Guides\RestructuredText\Directives\SubDirective;
use phpDocumentor\Guides\RestructuredText\Parser\BlockContext;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\Rule;

use function dirname;

/**
 * Overridden to handle "figclass" properly.
 */
class FigureDirective extends SubDirective
{
    public function __construct(
        private readonly DocumentNameResolverInterface $documentNameResolver,
        protected Rule $startingRule,
    ) {
        parent::__construct($startingRule);
    }

    public function getName(): string
    {
        return 'figure';
    }

    protected function processSub(
        BlockContext $blockContext,
        CollectionNode $collectionNode,
        Directive $directive,
    ): Node|null {
        $image = new ImageNode($this->documentNameResolver->absoluteUrl(
            dirname($blockContext->getDocumentParserContext()->getContext()->getCurrentAbsolutePath()),
            $directive->getData(),
        ));
        $scalarOptions = $this->optionsToArray($directive->getOptions());

        /* Start Custom Code */
        $figClass = $scalarOptions['figclass'] ?? null;
        unset($scalarOptions['figclass']);
        /* End Custom Code */

        $image = $image->withOptions([
            'width' => $scalarOptions['width'] ?? null,
            'height' => $scalarOptions['height'] ?? null,
            'alt' => $scalarOptions['alt'] ?? null,
            'scale' => $scalarOptions['scale'] ?? null,
            'target' => $scalarOptions['target'] ?? null,
            'class' => $scalarOptions['class'] ?? null,
            'name' => $scalarOptions['name'] ?? null,
            'align' => $scalarOptions['align'] ?? null,
        ]);

        $figureNode = new FigureNode($image, new CollectionNode($collectionNode->getChildren()));

        /* Start Custom Code */
        if ($figClass) {
            $figureNode->setClasses(explode(' ', (string) $figClass));
        }
        /* End Custom Code */

        return $figureNode;
    }
}
