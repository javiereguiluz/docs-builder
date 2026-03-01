<?php

declare(strict_types=1);

namespace SymfonyDocsBuilder;

use League\Tactician\CommandBus;
use phpDocumentor\Guides\DependencyInjection\GuidesExtension;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\DirectiveContentRule;
use phpDocumentor\Guides\Event\PostRenderProcess;
use phpDocumentor\Guides\Event\PreParseDocument;
use phpDocumentor\Guides\Event\PreRenderProcess;
use phpDocumentor\Guides\RestructuredText\DependencyInjection\ReStructuredTextExtension;
use phpDocumentor\Guides\Event\PostCollectFilesForParsingEvent;
use phpDocumentor\Guides\Event\PostParseDocument;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use SymfonyDocsBuilder\CI\UrlChecker;
use SymfonyDocsBuilder\Compiler\CopyImagesTransformer;
use SymfonyDocsBuilder\Compiler\UrlNodeTransformer;
use SymfonyDocsBuilder\Directive as SymfonyDirectives;
use SymfonyDocsBuilder\Listener\AdmonitionListener;
use SymfonyDocsBuilder\Listener\AssetsCopyListener;
use SymfonyDocsBuilder\Listener\BuildProgressListener;
use SymfonyDocsBuilder\Listener\DuplicatedHeaderIdListener;
use SymfonyDocsBuilder\Reference as SymfonyReferences;
use SymfonyDocsBuilder\Renderers\CodeNodeRenderer;
use SymfonyDocsBuilder\Renderers\TabsNodeRenderer;
use SymfonyDocsBuilder\Renderers\TitleNodeRenderer;

final class GuidesContainerFactory
{
    public static function createContainer(BuildConfig $buildConfig, ?UrlChecker $urlChecker = null, ?SymfonyStyle $io = null): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // Register extensions
        $container->registerExtension(new GuidesExtension());
        $container->registerExtension(new ReStructuredTextExtension());

        // Configure guides extension
        $container->loadFromExtension('guides', [
            'base_template_paths' => [__DIR__.'/Templates/default/html'],
            'output_format' => ['html'],
        ]);

        // Load RST extension (triggers its service definitions)
        $container->loadFromExtension('re_structured_text', []);

        // Register PSR-14 event dispatcher (public so we can add listeners after compilation)
        $container->register(EventDispatcherInterface::class, EventDispatcher::class)->setPublic(true);

        // Register logger
        $container->register(LoggerInterface::class, NullLogger::class);

        // Register BuildConfig as a synthetic service (set after compile)
        $container->register(BuildConfig::class, BuildConfig::class)->setSynthetic(true);

        // Register custom directives
        self::registerDirectives($container);

        // Register custom text roles (references)
        self::registerTextRoles($container, $buildConfig);

        // Register custom node renderers
        self::registerNodeRenderers($container);

        // Register custom node transformers
        self::registerNodeTransformers($container, $buildConfig, $urlChecker);

        // Add compiler pass to remove optional services and make key services public
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // Remove definitions for classes from optional packages that aren't installed
                foreach ($container->getDefinitions() as $id => $definition) {
                    $class = $definition->getClass() ?? $id;
                    if (str_contains($class, '\\') && !class_exists($class) && !interface_exists($class)) {
                        $container->removeDefinition($id);
                    }
                }

                // Make CommandBus public so it can be fetched from the container
                if ($container->hasDefinition(CommandBus::class)) {
                    $container->getDefinition(CommandBus::class)->setPublic(true);
                }
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);

        // Compile the container
        $container->compile();

        // Set synthetic services after compilation
        $container->set(BuildConfig::class, $buildConfig);
        if (null !== $urlChecker) {
            $container->set(UrlChecker::class, $urlChecker);
        }

        // Register event listeners on the compiled dispatcher
        self::registerEventListeners($container, $buildConfig, $io);

        return $container;
    }

    private static function registerDirectives(ContainerBuilder $container): void
    {
        // Directives that extend SubDirective/AbstractAdmonitionDirective/AbstractVersionChangeDirective
        // need the $startingRule argument bound to DirectiveContentRule
        $directivesNeedingRule = [
            SymfonyDirectives\AdmonitionDirective::class,
            SymfonyDirectives\AttentionDirective::class,
            SymfonyDirectives\BestPracticeDirective::class,
            SymfonyDirectives\CautionDirective::class,
            SymfonyDirectives\ConfigurationBlockDirective::class,
            SymfonyDirectives\DangerDirective::class,
            SymfonyDirectives\DeprecatedDirective::class,
            SymfonyDirectives\ErrorDirective::class,
            SymfonyDirectives\FigureDirective::class,
            SymfonyDirectives\GlossaryDirective::class,
            SymfonyDirectives\HintDirective::class,
            SymfonyDirectives\ImportantDirective::class,
            SymfonyDirectives\IndexDirective::class,
            SymfonyDirectives\NoteDirective::class,
            SymfonyDirectives\RoleDirective::class,
            SymfonyDirectives\RstClassDirective::class,
            SymfonyDirectives\ScreencastDirective::class,
            SymfonyDirectives\SeeAlsoDirective::class,
            SymfonyDirectives\SidebarDirective::class,
            SymfonyDirectives\TabDirective::class,
            SymfonyDirectives\TabsDirective::class,
            SymfonyDirectives\TipDirective::class,
            SymfonyDirectives\TopicDirective::class,
            SymfonyDirectives\VersionAddedDirective::class,
            SymfonyDirectives\WarningDirective::class,
        ];

        foreach ($directivesNeedingRule as $directiveClass) {
            $container->register($directiveClass)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setArgument('$startingRule', new Reference(DirectiveContentRule::class))
                ->addTag('phpdoc.guides.directive');
        }

        // Directives that extend BaseDirective (no $startingRule needed)
        $simpleDirectives = [
            SymfonyDirectives\CodeBlockDirective::class,
        ];

        foreach ($simpleDirectives as $directiveClass) {
            $container->register($directiveClass)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('phpdoc.guides.directive');
        }
    }

    private static function registerTextRoles(ContainerBuilder $container, BuildConfig $buildConfig): void
    {
        $symfonyRepoUrl = $buildConfig->getSymfonyRepositoryUrl();
        $phpDocUrl = $buildConfig->getPhpDocUrl();

        $textRoles = [
            SymfonyReferences\ClassReference::class => ['$symfonyRepositoryUrl' => $symfonyRepoUrl],
            SymfonyReferences\MethodReference::class => ['$symfonyRepositoryUrl' => $symfonyRepoUrl],
            SymfonyReferences\NamespaceReference::class => ['$symfonyRepositoryUrl' => $symfonyRepoUrl],
            SymfonyReferences\PhpFunctionReference::class => ['$phpDocUrl' => $phpDocUrl],
            SymfonyReferences\PhpMethodReference::class => ['$phpDocUrl' => $phpDocUrl],
            SymfonyReferences\PhpClassReference::class => ['$phpDocUrl' => $phpDocUrl],
            SymfonyReferences\TermReference::class => [],
            SymfonyReferences\LeaderReference::class => [],
            SymfonyReferences\MergerReference::class => [],
            SymfonyReferences\DeciderReference::class => [],
        ];

        foreach ($textRoles as $class => $args) {
            $def = $container->register($class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('phpdoc.guides.parser.rst.text_role');

            foreach ($args as $name => $value) {
                $def->setArgument($name, $value);
            }
        }
    }

    private static function registerNodeRenderers(ContainerBuilder $container): void
    {
        $container->register(CodeNodeRenderer::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('phpdoc.guides.noderenderer.html');

        $container->register(TitleNodeRenderer::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('phpdoc.guides.noderenderer.html');

        $container->register(TabsNodeRenderer::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('phpdoc.guides.noderenderer.html');
    }

    private static function registerNodeTransformers(ContainerBuilder $container, BuildConfig $buildConfig, ?UrlChecker $urlChecker = null): void
    {
        $container->register(CopyImagesTransformer::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$buildConfig', new Reference(BuildConfig::class))
            ->addTag('phpdoc.guides.compiler.nodeTransformers');

        $urlTransformerDef = $container->register(UrlNodeTransformer::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$symfonyVersion', $buildConfig->getSymfonyVersion())
            ->addTag('phpdoc.guides.compiler.nodeTransformers');

        if (null !== $urlChecker) {
            $container->register(UrlChecker::class, UrlChecker::class)->setSynthetic(true);
            $urlTransformerDef->setArgument('$urlChecker', new Reference(UrlChecker::class));
        }
    }

    private static function registerEventListeners(ContainerBuilder $container, BuildConfig $buildConfig, ?SymfonyStyle $io = null): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $dispatcher->addListener(PreParseDocument::class, new AdmonitionListener());
        $dispatcher->addListener(PreParseDocument::class, new DuplicatedHeaderIdListener());

        if (!$buildConfig->getSubdirectoryToBuild()) {
            $dispatcher->addListener(PostRenderProcess::class, new AssetsCopyListener($buildConfig->getOutputDir()));
        }

        if (null !== $io) {
            $progressListener = new BuildProgressListener($io);
            $dispatcher->addListener(PostCollectFilesForParsingEvent::class, [$progressListener, 'onFilesCollected']);
            $dispatcher->addListener(PostParseDocument::class, [$progressListener, 'onPostParseDocument']);
            $dispatcher->addListener(PreRenderProcess::class, [$progressListener, 'onPreRender']);
        }
    }
}
