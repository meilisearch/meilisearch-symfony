<?php

declare(strict_types=1);

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->append([__FILE__]);
$config = new PhpCsFixer\Config();
$config->setRules([
            '@Symfony' => true,
            'declare_strict_types' => true,
            'global_namespace_import' => [
                'import_classes' => false,
                'import_functions' => false,
                'import_constants' => false,
            ],
        ]
    )
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;

return $config;
