<?php

declare(strict_types=1);

namespace Pagenode\Content;

use Highlight\Highlighter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Renderer\NodeRendererInterface;

final class BodyRenderer
{
    private MarkdownConverter $converter;

    public function __construct(?MarkdownConverter $converter = null)
    {
        $this->converter = $converter ?? $this->createConverter();
    }

    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }

    private function createConverter(): MarkdownConverter
    {
        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        if ($this->highlightingEnabled()) {
            $renderer = $this->createHighlightingRenderer();
            if ($renderer !== null) {
                $environment->addRenderer(FencedCode::class, $renderer);
                // The default indented code renderer is reused so we only replace fenced blocks.
            }
        }

        return new MarkdownConverter($environment);
    }

    private function highlightingEnabled(): bool
    {
        return defined('PN_SYNTAX_HIGHLIGHT_LANGS') && PN_SYNTAX_HIGHLIGHT_LANGS !== '';
    }

    private function createHighlightingRenderer(): ?NodeRendererInterface
    {
        $languages = array_filter(
            array_map('trim', explode('|', PN_SYNTAX_HIGHLIGHT_LANGS)),
            static fn ($lang) => $lang !== ''
        );

        if (empty($languages)) {
            return null;
        }

        $languages = array_map('strtolower', $languages);
        $highlighter = new Highlighter();
        $highlighter->setAutodetectLanguages($languages);

        return new HighlightingCodeRenderer($highlighter, $languages);
    }
}
