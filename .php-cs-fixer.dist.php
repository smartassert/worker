<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'trailing_comma_in_multiline' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'types_spaces' => [
            'space' => 'none',
            'space_multiple_catch' => 'single',
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'default',
                'phpdoc',
                'do',
                'exit',
                'for',
                'goto',
                'include',
                'include_once',
                'require',
                'require_once',
                'return',
                'switch',
                'throw',
                'try',
                'while',
                'yield',
                'yield_from',
            ],
        ],
        'global_namespace_import' => [
            'import_classes' => false,
        ],
        'no_useless_concat_operator' => false,
    ])
    ->setFinder($finder)
;
