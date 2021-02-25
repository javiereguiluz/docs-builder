<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Directive;

use Doctrine\RST\Directives\SubDirective;
use Doctrine\RST\Nodes\CodeNode;
use Doctrine\RST\Nodes\Node;
use Doctrine\RST\Parser;
use function strtoupper;

class ConfigurationBlockDirective extends SubDirective
{
    public function getName(): string
    {
        return 'configuration-block';
    }

    public function processSub(Parser $parser, ?Node $document, string $variable, string $data, array $options): ?Node
    {
        $blocks = [];
        foreach ($document->getNodes() as $node) {
            if (!$node instanceof CodeNode) {
                continue;
            }

            $language = $node->getLanguage() ?? 'Unknown';

            $blocks[] = [
                'language' => $this->formatLanguageTab($language),
                'code' => $node->render(),
            ];
        }

        $wrapperDiv = $parser->renderTemplate(
            'directives/configuration-block.html.twig',
            [
                'blocks' => $blocks,
            ]
        );

        return $parser->getNodeFactory()->createWrapperNode(null, $wrapperDiv, '</div>');
    }

    /**
     * A hack to print exactly what we want in the tab of a configuration block.
     */
    private function formatLanguageTab(string $language): string
    {
        switch ($language) {
            case 'php-annotations':
                return 'Annotations';
            case 'php-attributes':
                return 'Attributes';
            case 'xml':
            case 'yaml':
            case 'php':
                return strtoupper($language);
            default:
                return $language;
        }
    }
}
