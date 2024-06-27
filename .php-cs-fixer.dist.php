<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@PHP80Migration:risky' => true,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_functions' => false,
            'import_constants' => false,
        ],
        'no_superfluous_phpdoc_tags' => false,
    ]);
