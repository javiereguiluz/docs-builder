<?php

namespace SymfonyDocsBuilder;

use League\Tactician\CommandBus;
use phpDocumentor\FileSystem\Finder\Exclude;
use phpDocumentor\FileSystem\FlySystemAdapter;
use phpDocumentor\Guides\Compiler\CompilerContext;
use phpDocumentor\Guides\Handlers\CompileDocumentsCommand;
use phpDocumentor\Guides\Handlers\ParseDirectoryCommand;
use phpDocumentor\Guides\Handlers\RenderCommand;
use phpDocumentor\Guides\Nodes\ProjectNode;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyDocsBuilder\CI\MissingFilesChecker;
use SymfonyDocsBuilder\CI\UrlChecker;
use SymfonyDocsBuilder\Generator\HtmlForPdfGenerator;
use SymfonyDocsBuilder\Generator\JsonGenerator;

final class DocBuilder
{
    public function build(BuildConfig $config, ?SymfonyStyle $io = null, ?UrlChecker $urlChecker = null): BuildResult
    {
        $filesystem = new Filesystem();
        if (!$config->isBuildCacheEnabled() && $filesystem->exists($config->getOutputDir())) {
            $filesystem->remove($config->getOutputDir());
        }
        $filesystem->mkdir($config->getOutputDir());

        // Create the DI container with all services
        $container = GuidesContainerFactory::createContainer($config, $urlChecker, $io);
        $commandBus = $container->get(CommandBus::class);

        // Create filesystems for source and output
        $sourceFilesystem = FlySystemAdapter::createForPath($config->getContentDir());
        $outputFilesystem = FlySystemAdapter::createForPath($config->getOutputDir());

        // Create project node
        $projectNode = new ProjectNode();

        // Phase 1: Parse
        $documents = $commandBus->handle(
            new ParseDirectoryCommand(
                $sourceFilesystem,
                '',
                'rst',
                $projectNode,
                new Exclude(),
            )
        );

        // Phase 2: Compile
        $compilerContext = new CompilerContext($projectNode);
        $documents = $commandBus->handle(
            new CompileDocumentsCommand($documents, $compilerContext)
        );

        // Phase 3: Render
        $commandBus->handle(
            new RenderCommand(
                'html',
                $documents,
                $sourceFilesystem,
                $outputFilesystem,
                $projectNode,
            )
        );

        $buildResult = new BuildResult($projectNode);

        $missingFilesChecker = new MissingFilesChecker($config);
        $missingFiles = $missingFilesChecker->getMissingFiles();
        foreach ($missingFiles as $missingFile) {
            $buildResult->appendError(sprintf('Missing file "%s"', $missingFile));
        }

        if (!$buildResult->isSuccessful()) {
            $errorLog = sprintf("Build errors from \"%s\"\n%s", date('Y-m-d h:i:s'), implode("\n", $buildResult->getErrors()));
            $filesystem->dumpFile($config->getOutputDir().'/build_errors.txt', $errorLog);
        }

        if ($config->isContentAString()) {
            $htmlFilePath = $config->getOutputDir().'/index.html';
            if (is_file($htmlFilePath)) {
                $crawler = new Crawler(file_get_contents($htmlFilePath));
                $buildResult->setStringResult(trim($crawler->filter('body')->html()));
            }
        } elseif ($config->getSubdirectoryToBuild()) {
            $htmlForPdfGenerator = new HtmlForPdfGenerator($projectNode, $config);
            $htmlForPdfGenerator->generateHtmlForPdf();
        } elseif ($config->generateJsonFiles()) {
            $jsonGenerator = new JsonGenerator($projectNode, $config);
            $buildResult->setJsonResults($jsonGenerator->generateJson());
        }

        return $buildResult;
    }

    public function buildString(string $contents): BuildResult
    {
        $filesystem = new Filesystem();
        $tmpDir = sys_get_temp_dir().'/doc_builder_build_string_'.random_int(1, 100000000);
        if ($filesystem->exists($tmpDir)) {
            $filesystem->remove($tmpDir);
        }
        $filesystem->mkdir($tmpDir);

        $filesystem->dumpFile($tmpDir.'/index.rst', $contents);

        $buildConfig = (new BuildConfig())
            ->setContentIsString()
            ->setContentDir($tmpDir)
            ->setOutputDir($tmpDir.'/output')
            ->disableBuildCache()
            ->disableJsonFileGeneration()
        ;

        $buildResult = $this->build($buildConfig);
        $filesystem->remove($tmpDir);

        return $buildResult;
    }
}
