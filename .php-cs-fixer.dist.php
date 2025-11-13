<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->in([__DIR__ . '/src'])
    ->append([
        new SplFileInfo(__DIR__ . '/pagenode.php'),
        new SplFileInfo(__DIR__ . '/build-phar'),
    ]);

return (new Config())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
    ])
    ->setFinder($finder);
