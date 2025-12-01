<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@PHP8x0Migration:risky' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'global_namespace_import' => [
            'import_classes' => false,
            'import_functions' => false,
            'import_constants' => false,
        ],
        'no_superfluous_phpdoc_tags' => false,
        // @todo: when we'll support only PHP 8.0 and upper, we can enable `parameters` for `trailing_comma_in_multiline` rule
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['array_destructuring', 'arrays', 'match'/* , 'parameters' */]],
        // @todo: when we'll support only PHP 8.0 and upper, we can enable this
        'get_class_to_class_keyword' => false,
    ]);
