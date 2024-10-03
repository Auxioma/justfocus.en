<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->exclude('var')
    ->exclude('vendor');

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@Symfony' => true,
        'strict_param' => false,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
