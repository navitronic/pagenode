<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/pagenode.php',
        __DIR__.'/build-phar',
    ]);

    $rectorConfig->phpVersion(80200);
    $rectorConfig->disableParallel();
    $rectorConfig->importNames();

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ]);

    $rectorConfig->ruleWithConfiguration(RenameFunctionRector::class, [
        'htmlSpecialChars' => 'htmlspecialchars',
        'pathInfo' => 'pathinfo',
    ]);
};
