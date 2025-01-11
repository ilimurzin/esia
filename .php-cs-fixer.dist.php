<?php

return (new \PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
    ])
    ->setFinder(
        (new \PhpCsFixer\Finder())
            ->in([
                __DIR__ . '/src',
                __DIR__ . '/tests',
            ])
            ->exclude([
                '_support/_generated',
            ])
    );
