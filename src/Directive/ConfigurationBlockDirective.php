<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Directive;

use phpDocumentor\Guides\Nodes\CodeNode;
use phpDocumentor\Guides\Nodes\CollectionNode;
use phpDocumentor\Guides\Nodes\Configuration\ConfigurationBlockNode;
use phpDocumentor\Guides\Nodes\Configuration\ConfigurationTab;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Directives\SubDirective;
use phpDocumentor\Guides\RestructuredText\Parser\BlockContext;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\Rule;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

use function assert;
use function get_debug_type;
use function sprintf;

class ConfigurationBlockDirective extends SubDirective
{
    private const LANGUAGE_LABELS = [
        'caddy' => 'Caddy',
        'env' => 'Bash',
        'html+jinja' => 'Twig',
        'html+php' => 'PHP',
        'html+twig' => 'Twig',
        'jinja' => 'Twig',
        'php' => 'PHP',
        'php-annotations' => 'Annotations',
        'php-attributes' => 'Attributes',
        'php-standalone' => 'Standalone Use',
        'php-symfony' => 'Framework Use',
        'rst' => 'RST',
        'terminal' => 'Bash',
        'varnish3' => 'Varnish 3',
        'varnish4' => 'Varnish 4',
        'vcl' => 'VCL',
        'xml' => 'XML',
        'xml+php' => 'XML',
        'yaml' => 'YAML',
    ];

    private SluggerInterface $slugger;

    public function __construct(
        private readonly LoggerInterface $logger,
        protected Rule $startingRule,
    ) {
        parent::__construct($startingRule);

        $this->slugger = new AsciiSlugger();
    }

    public function getName(): string
    {
        return 'configuration-block';
    }

    protected function processSub(
        BlockContext $blockContext,
        CollectionNode $collectionNode,
        Directive $directive,
    ): Node|null {
        $tabs = [];
        foreach ($collectionNode->getValue() as $child) {
            if (!$child instanceof CodeNode) {
                $this->logger->warning(
                    sprintf('The ".. configuration-block::" directive only supports code blocks, "%s" given.', get_debug_type($child)),
                    $blockContext->getLoggerInformation(),
                );

                continue;
            }

            $language = $child->getLanguage();
            assert($language !== null);

            $label = self::LANGUAGE_LABELS[$language] ?? $this->slugger->slug($language, ' ')->title()->toString();

            $tabs[] = new ConfigurationTab(
                $label,
                $this->slugger->slug($label)->lower()->toString(),
                $child,
            );
        }

        return new ConfigurationBlockNode($tabs);
    }
}
