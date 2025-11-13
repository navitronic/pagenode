<?php

namespace Pagenode;

class FileReader
{
    public static function ReadMeta($path)
    {
        return self::ReadFile($path)[0];
    }

    public static function ReadContent($path)
    {
        return self::ReadFile($path)[1];
    }

    private static function ReadFile($path): array
    {
        $meta = [];
        $file = file_get_contents($path);

        $lines = preg_split('/\R/', $file);

        if ($lines[0] === '---') {
            array_shift($lines);
            $file = implode("\n", $lines);
        }

        if (preg_match('/(.*?)^---\s*$/ms', $file, $metaSection)) {
            preg_match_all('/^(\w+):(.*)$/m', $metaSection[1], $metaAttribs);
            foreach ($metaAttribs[1] as $i => $key) {
                $meta[$key] = trim($metaAttribs[2][$i]);
            }
        }

        $meta['tags'] = empty($meta['tags'])
            ? []
            : array_map('trim', explode(',', $meta['tags']));

        if (
            isset($meta['date']) && ($meta['date'] !== '' && $meta['date'] !== '0' && $meta['date'] !== []) &&
            preg_match(
                '/(\d{4})[\.\-](\d{2})[\.\-](\d{2})( (\d{2}):(\d{2}))?/',
                $meta['date'],
                $dateMatch
            )
        ) {
            $y = $dateMatch[1];
            $m = $dateMatch[2];
            $d = $dateMatch[3];
            $h = empty($dateMatch[5]) ? 0 : $dateMatch[5];
            $i = empty($dateMatch[6]) ? 0 : $dateMatch[6];
            $meta['date'] = mktime($h, $i, 0, $m, $d, $y);
        } else {
            $meta['date'] = filemtime($path);
        }

        $meta['active'] = empty($meta['active']) || $meta['active'] !== 'false';

        $markdown = (preg_match('/^---\s*$(.*)/ms', $file, $m))
            ? $m[1]
            : $file;

        return [$meta, $markdown];
    }
}
