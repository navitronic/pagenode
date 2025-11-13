<?php

namespace Pagenode;

use Parsedown;

/** Generic Syntax Highlighting extension for Parsedown */
class ParsedownSyntaxHighlight extends Parsedown
{
    public static function SyntaxHighlight($s): string
    {
        $s = htmlspecialchars((string) $s, ENT_COMPAT) . "\n";
        $s = str_replace('\\\\', '\\\\<e>', $s); // break escaped backslashes

        $tokens = [];
        $transforms = [
            // Insert helpers to find regexps
            '/
				([\[({=:+,]\s*)
					\/
				(?![\/\*])
			/x'
            => '$1<h>/',

            // Extract Comments, Strings & Regexps, insert them into $tokens
            // and return the index
            '/(
				\/\*.*?\*\/|
				\/\/.*?\n|
				\#.*?\n|
				--.*?\n|
				(?<!\\\)&quot;.*?(?<!\\\)&quot;|
				(?<!\\\)\'(.*?)(?<!\\\)\'|
				(?<!\\\)<h>\/.+?(?<!\\\)\/\w*
			)/sx'
            => function ($m) use (&$tokens): string {
                $id = '<r' . count($tokens) . '>';
                $block = $m[1];

                if ($block[0] === '&' || $block[0] === "'") {
                    $type = 'string';
                } elseif ($block[0] === '<') {
                    $type = 'regexp';
                } else {
                    $type = 'comment';
                }
                $tokens[$id] = '<span class="' . $type . '">' . $block . '</span>';
                return $id;
            },

            // Punctuation
            '/((
				&\w+;|
				[-\/+*=?:.,;()\[\]{}|%^!]
			)+)/x'
            => '<span class="punct">$1</span>',

            // Numbers (also look for Hex encoding)
            '/(?<!\w)(
				0x[\da-f]+|
				\d+
			)(?!\w)/ix'
            => '<span class="number">$1</span>',

            // Keywords
            '/(?<!\w|\$)(
				and|or|xor|not|for|do|while|foreach|as|endfor|endwhile|break|
				endforeach|continue|return|die|exit|if|then|else|elsif|elseif|
				endif|new|delete|try|throw|catch|finally|switch|case|default|
				goto|class|function|extends|this|self|parent|public|private|
				protected|published|friend|virtual|
				string|array|object|resource|var|let|bool|boolean|int|integer|
				float|double|real|char|short|long|const|static|global|
				enum|struct|typedef|signed|unsigned|union|extern|true|false|
				null|void
			)(?!\w|=")/ix'
            => '<span class="keyword">$1</span>',

            // PHP-Style Vars: $var
            '/(?<!\w)(
				\$(\-&gt;|\w)+
			)(?!\w)/ix'
            => '<span class="var">$1</span>',

            // Make the bold assumption that an all uppercase word has a
            // special meaning
            '/(?<!\w|\$|>)(
				[A-Z_][A-Z_0-9]+
			)(?!\w)/x'
            => '<span class="def">$1</span>'
        ];

        foreach ($transforms as $search => $replace) {
            $s = is_string($replace)
                ? preg_replace($search, $replace, (string) $s)
                : preg_replace_callback($search, $replace, (string) $s);
        }

        // Paste the comments and strings back in again
        $s = strtr($s, $tokens);

        // Delete the escaped backslash breaker and replace tabs with 4 spaces
        $s = str_replace(['<e>', '<h>', "\t"], ['', '', '    '], $s);

        return trim($s, "\n\r");
    }

    protected function blockFencedCodeComplete($Block)
    {
        $class = $Block['element']['element']['attributes']['class'] ?? null;
        $re = '/^language-(' . PN_SYNTAX_HIGHLIGHT_LANGS . ')$/';
        if (empty($class) || !preg_match($re, (string) $class)) {
            return $Block;
        }

        $text = $Block['element']['element']['text'];
        unset($Block['element']['element']['text']);
        $Block['element']['element']['rawHtml'] = self::SyntaxHighlight($text);
        $Block['element']['element']['allowRawHtmlInSafeMode'] = true;
        return $Block;
    }
}
