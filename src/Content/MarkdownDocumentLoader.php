<?php

declare(strict_types=1);

namespace Pagenode\Content;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class MarkdownDocumentLoader
{
    private Filesystem $filesystem;
    private FrontMatterParser $parser;

    public function __construct(
        ?FrontMatterParser $parser = null,
        ?Filesystem $filesystem = null
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->parser = $parser ?? new FrontMatterParser();
    }

    public function load(string $path): MarkdownDocument
    {
        if (!$this->filesystem->exists($path)) {
            throw new RuntimeException(sprintf('Markdown file not found: %s', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Unable to read markdown file: %s', $path));
        }

        return $this->parser->parse($content, $path);
    }
}
