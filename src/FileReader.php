<?php

declare(strict_types=1);

namespace Pagenode;

use Pagenode\Content\FrontMatterParser;
use Pagenode\Content\MarkdownDocumentLoader;

/**
 * @deprecated Use MarkdownDocumentLoader directly instead.
 */
class FileReader
{
    private static ?MarkdownDocumentLoader $loader = null;

    /**
     * @return array<string, mixed>
     */
    public static function ReadMeta(string $path): array
    {
        return self::loader()->load($path)->meta();
    }

    public static function ReadContent(string $path): string
    {
        return self::loader()->load($path)->body();
    }

    private static function loader(): MarkdownDocumentLoader
    {
        if (self::$loader === null) {
            self::$loader = new MarkdownDocumentLoader(new FrontMatterParser());
        }

        return self::$loader;
    }
}
