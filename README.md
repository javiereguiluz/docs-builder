Symfony Docs Builder
====================

This project is used to build the [Symfony Documentation][1] both on
https://symfony.com and the Continuous Integration service used in the Symfony
Docs repository.

This project is considered **an internal tool** and therefore, you
**shouldn't use this project in your application**. Unlike the rest of the
Symfony projects, this repository doesn't provide any support and it doesn't
guarantee backward compatibility either. Any or the entire project can change,
or even disappear, at any moment without prior notice.

Internally, this project uses the [phpDocumentor Guides][2] library to parse
reStructuredText files and render them as HTML.

Usage
-----

### CLI Usage

The `build:docs` command builds a directory of `.rst` files into HTML:

```bash
bin/docs-builder build:docs /path/to/rst-docs /path/to/output --symfony-version=7.2
```

**Arguments:**

| Argument       | Description                     | Default                  |
|----------------|---------------------------------|--------------------------|
| `source-dir`   | Directory containing `.rst` files | Current working directory |
| `output-dir`   | Directory for HTML output        | `{source-dir}/_output`   |

**Options:**

| Option               | Description                                              |
|----------------------|----------------------------------------------------------|
| `--parse-sub-path`   | Build only a subdirectory (generates single HTML for PDF) |
| `--output-json`      | Generate `.fjson` metadata files (Sphinx compatible)      |
| `--disable-cache`    | Clear cache before building                               |
| `--save-errors`      | Save error log to a file                                  |
| `--no-theme`         | Use `default` theme instead of `rtd`                      |
| `--fail-on-errors`   | Return exit code 1 if there are warnings/errors           |

The `--symfony-version` option (or the `SYMFONY_VERSION` environment variable) is required.

If a `docs.json` file exists in the source directory, it is read automatically:

```json
{
    "exclude": ["_build", "vendor"]
}
```

### Library Usage

You can use the builder programmatically without invoking a console command.
The two main classes are `DocBuilder` (the builder) and `BuildConfig` (the
configuration):

#### Build a Directory

```php
use SymfonyDocsBuilder\DocBuilder;
use SymfonyDocsBuilder\BuildConfig;

$config = (new BuildConfig())
    ->setContentDir('/path/to/rst-docs')
    ->setOutputDir('/path/to/output')
    ->setSymfonyVersion('7.2');

$builder = new DocBuilder();
$result = $builder->build($config);

if (!$result->isSuccessful()) {
    foreach ($result->getErrors() as $error) {
        echo $error . "\n";
    }
}
```

#### Build a Single RST String

```php
use SymfonyDocsBuilder\DocBuilder;

$rst = <<<RST
Page Title
==========

A paragraph.

.. note::

    This is a note.

.. code-block:: php

    echo 'Hello world';
RST;

$builder = new DocBuilder();
$result = $builder->buildString($rst);

$html = $result->getStringResult();
```

#### Advanced Configuration

```php
$config = (new BuildConfig())
    ->setContentDir($sourceDir)
    ->setOutputDir($outputDir)
    ->setCacheDir('/tmp/docs-cache')            // Default: {outputDir}/.cache
    ->setImagesDir('/path/to/public/images')    // Default: {outputDir}/_images
    ->setImagesPublicPrefix('/assets/images/')  // URL prefix for <img src>
    ->setSymfonyVersion('7.2')
    ->setTheme('rtd')                           // 'default' or 'rtd'
    ->setExcludedPaths(['_build', 'vendor'])
    ->disableBuildCache()
    ->disableJsonFileGeneration();

$builder = new DocBuilder();
$result = $builder->build($config);
```

#### With Progress Output

```php
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = new SymfonyStyle(new ArrayInput([]), new ConsoleOutput());

$result = $builder->build($config, $io);
```

### BuildConfig API

| Method                           | Description                                  |
|----------------------------------|----------------------------------------------|
| `setContentDir(string)`          | Source directory containing `.rst` files      |
| `setOutputDir(string)`           | Output directory for generated HTML           |
| `setCacheDir(string)`            | Cache directory (default: `{outputDir}/.cache`) |
| `setImagesDir(string)`           | Target directory for copied images            |
| `setImagesPublicPrefix(string)`  | URL prefix for image `src` attributes         |
| `setSymfonyVersion(string)`      | Symfony version (used in URLs and `{version}` placeholders) |
| `setTheme(string)`               | `'default'` or `'rtd'`                       |
| `setSubdirectoryToBuild(string)` | Build only a subdirectory (for PDF generation)|
| `setExcludedPaths(array)`        | Paths to exclude from parsing                 |
| `disableBuildCache()`            | Disable output caching                        |
| `disableJsonFileGeneration()`    | Don't generate `.fjson` files                 |

### BuildResult API

| Method              | Description                                         |
|---------------------|-----------------------------------------------------|
| `isSuccessful()`    | Returns `true` if there are no errors               |
| `getErrors()`       | Returns an array of error message strings            |
| `getErrorTrace()`   | Returns errors formatted as a single string          |
| `getProjectNode()`  | Returns the phpDocumentor `ProjectNode` (full AST)   |
| `getJsonResults()`  | Returns generated JSON metadata                      |
| `getStringResult()` | Returns the HTML output (only for `buildString()`)   |

### Integration in a Symfony Application

`DocBuilder` creates its own internal DI container (via `GuidesContainerFactory`),
so you don't need to register any of its internal services in your Symfony
application. Simply instantiate `DocBuilder` and call `build()` or `buildString()`:

```php
// src/Service/DocumentationBuilder.php
namespace App\Service;

use SymfonyDocsBuilder\DocBuilder;
use SymfonyDocsBuilder\BuildConfig;
use SymfonyDocsBuilder\BuildResult;

class DocumentationBuilder
{
    public function __construct(
        private string $docsSourceDir,
        private string $docsOutputDir,
        private string $symfonyVersion = '7.2',
    ) {}

    public function buildAll(): BuildResult
    {
        $config = (new BuildConfig())
            ->setContentDir($this->docsSourceDir)
            ->setOutputDir($this->docsOutputDir)
            ->setSymfonyVersion($this->symfonyVersion)
            ->setTheme('default')
            ->disableJsonFileGeneration();

        return (new DocBuilder())->build($config);
    }

    public function renderRstString(string $rstContent): string
    {
        $result = (new DocBuilder())->buildString($rstContent);

        if (!$result->isSuccessful()) {
            throw new \RuntimeException(
                'RST rendering failed: ' . $result->getErrorTrace()
            );
        }

        return $result->getStringResult();
    }
}
```

```yaml
# config/services.yaml
services:
    App\Service\DocumentationBuilder:
        arguments:
            $docsSourceDir: '%kernel.project_dir%/docs'
            $docsOutputDir: '%kernel.project_dir%/public/docs'
            $symfonyVersion: '7.2'
```

[1]: https://github.com/symfony/symfony-docs
[2]: https://github.com/phpDocumentor/guides
