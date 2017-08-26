<?php
$bin = iterator_to_array(PhpCsFixer\Finder::create()->in(__DIR__ . '/bin/'));
$src   = iterator_to_array(PhpCsFixer\Finder::create()->in(__DIR__ . '/src/'));
$files       = array_merge($bin, $src);
return PhpCsFixer\Config::create()
        ->setRiskyAllowed(true)
        ->setRules([
            '@PHP56Migration'                    => true,
            '@PSR2'                              => true,
            'array_syntax'                       => [
                'syntax' => 'short'
            ],
            'binary_operator_spaces'             => [
                'align_double_arrow' => true,
                'align_equals'       => true
            ],
            'single_quote'                       => true,
            'no_blank_lines_after_class_opening' => false,
        ])
        ->setFinder($files);
