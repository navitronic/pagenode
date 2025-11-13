<?php

declare(strict_types=1);

namespace Pagenode\Content;

use Highlight\Highlighter;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Applies syntax highlighting for fenced code blocks before falling back
 * to the default CommonMark renderer.
 */
final class HighlightingCodeRenderer implements NodeRendererInterface
{
    private NodeRendererInterface $fallback;

    /**
     * @param string[] $languages
     */
    public function __construct(
        private Highlighter $highlighter,
        private array $languages
    ) {
        $this->fallback = new FencedCodeRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement|string|null
    {
        if (!$node instanceof FencedCode) {
            throw new \InvalidArgumentException('Incompatible node type: ' . $node::class);
        }

        $language = $node->getInfoWords()[0] ?? null;
        if ($language !== null && $this->supportsLanguage($language)) {
            try {
                $highlighted = $this->highlighter->highlight($language, $node->getLiteral() ?? '');
                $code = new HtmlElement(
                    'code',
                    ['class' => 'hljs language-' . $language],
                    $highlighted->value
                );
                return new HtmlElement('pre', [], $code);
            } catch (\Throwable) {
                // Fall back to the default renderer if highlighting fails.
            }
        }

        return $this->fallback->render($node, $childRenderer);
    }

    private function supportsLanguage(string $language): bool
    {
        if (empty($this->languages)) {
            return false;
        }

        return in_array(strtolower($language), $this->languages, true);
    }
}
