<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/config',
        __DIR__ . '/public',
    ])
    ->exclude([
        'bin',
        'var',
        'vendor',
    ])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,

        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],

        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'trim_array_spaces' => true,
        'line_ending' => true,
        'single_quote' => true,
        'declare_strict_types' => true,

        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => true,
            'import_constants' => false,
        ],

        'phpdoc_tag_type' => [
            'tags' => ['psalm' => 'annotation'],
        ],
    ])
    ->setFinder($finder);
