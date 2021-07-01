<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->append([__FILE__]);
$config = new PhpCsFixer\Config();

$config->setRules([
            '@Symfony' => true,
            '@PHP80Migration:risky' => true,
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
