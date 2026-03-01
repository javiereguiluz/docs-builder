<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Directive;

use phpDocumentor\Guides\Nodes\CodeNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Directives\BaseDirective;
use phpDocumentor\Guides\RestructuredText\Directives\OptionMapper\CodeNodeOptionMapper;
use phpDocumentor\Guides\RestructuredText\Parser\BlockContext;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use Psr\Log\LoggerInterface;
use SymfonyDocsBuilder\Renderers\CodeNodeRenderer;

use function trim;

class CodeBlockDirective extends BaseDirective
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CodeNodeOptionMapper $codeNodeOptionMapper,
    ) {
    }

    public function getName(): string
    {
        return 'code-block';
    }

    /** @inheritDoc */
    public function getAliases(): array
    {
        return ['code', 'parsed-literal'];
    }

    /** @inheritDoc */
    public function process(
        BlockContext $blockContext,
        Directive $directive,
    ): Node|null {
        if ($blockContext->getDocumentIterator()->isEmpty()) {
            $this->logger->warning('The code-block has no content. Did you properly indent the code? ', $blockContext->getLoggerInformation());

            return null;
        }

        $language = trim($directive->getData());

        if ($language !== '' && !CodeNodeRenderer::isLanguageSupported($language)) {
            throw new \Exception(sprintf('Unsupported code block language "%s". Add it in %s', $language, CodeNodeRenderer::class));
        }

        $node = new CodeNode(
            $blockContext->getDocumentIterator()->toArray(),
        );

        if ($language !== '') {
            $node->setLanguage($language);
        } else {
            $node->setLanguage($blockContext->getDocumentParserContext()->getCodeBlockDefaultLanguage());
        }

        $this->codeNodeOptionMapper->apply($node, $directive->getOptions(), $blockContext);

        if ($directive->getVariable() !== '') {
            $document = $blockContext->getDocumentParserContext()->getDocument();
            $document->addVariable($directive->getVariable(), $node);

            return null;
        }

        return $node;
    }
}
