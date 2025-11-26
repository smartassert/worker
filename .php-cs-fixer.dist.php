<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PhpCsFixer' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'types_spaces' => [
            'space' => 'none',
            'space_multiple_catch' => 'single',
        ],
        'global_namespace_import' => [
            'import_classes' => false,
        ],
        'no_useless_concat_operator' => false,
        'single_line_empty_body' => false,
        'modifier_keywords' => false,
    ])
    ->setFinder($finder)
;
