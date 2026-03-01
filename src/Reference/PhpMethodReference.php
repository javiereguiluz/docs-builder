<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Reference;

use phpDocumentor\Guides\Nodes\Inline\HyperLinkNode;
use phpDocumentor\Guides\Nodes\Inline\InlineNodeInterface;
use phpDocumentor\Guides\RestructuredText\Parser\DocumentParserContext;
use phpDocumentor\Guides\RestructuredText\TextRoles\TextRole;
use function Symfony\Component\String\u;

class PhpMethodReference implements TextRole
{
    public function __construct(private readonly string $phpDocUrl)
    {
    }

    public function getName(): string
    {
        return 'phpmethod';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function processNode(
        DocumentParserContext $documentParserContext,
        string $role,
        string $content,
        string $rawContent,
    ): InlineNodeInterface {
        $data = u($content);
        if (!$data->containsAny('::')) {
            throw new \RuntimeException(sprintf('Malformed method reference "%s"', $data));
        }

        [$className, $methodName] = $data->split('::', 2);
        $className = $className->replace('\\\\', '\\');

        return new HyperLinkNode(
            $methodName.'()',
            sprintf('%s/%s.%s.php', $this->phpDocUrl, $className->replace('\\', '-')->lower(), $methodName->lower()),
        );
    }
}
