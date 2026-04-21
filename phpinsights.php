<?php

declare(strict_types=1);
use NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenDefineFunctions;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use SlevomatCodingStandard\Sniffs\Classes\ForbiddenPublicPropertySniff;
use SlevomatCodingStandard\Sniffs\Commenting\DocCommentSpacingSniff;
use SlevomatCodingStandard\Sniffs\Functions\FunctionLengthSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Preset
    |--------------------------------------------------------------------------
    |
    | This option controls the default preset that will be used by PHP Insights
    | to make your code reliable, simple, and clean. However, you can always
    | adjust the `Metrics` and `Insights` below in this configuration file.
    |
    | Supported: "default", "laravel", "symfony", "magento2", "drupal"
    |
    */

    'preset' => 'laravel',

    /*
    |--------------------------------------------------------------------------
    | IDE
    |--------------------------------------------------------------------------
    |
    | This options allow to add hyperlinks in your terminal to quickly open
    | files in your favorite IDE while browsing your PhpInsights report.
    |
    | Supported: "textmate", "macvim", "emacs", "sublime", "phpstorm",
    | "atom", "vscode".
    |
    | If you have another IDE that is not in this list but which provide an
    | url-handler, you could fill this config with a pattern like this:
    |
    | myide://open?url=file://%f&line=%l
    |
    */

    'ide' => 'phpstorm',

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may adjust all the various `Insights` that will be used by PHP
    | Insights. You can either add, remove or configure `Insights`. Keep in
    | mind that all added `Insights` must belong to a specific `Metric`.
    |
    */

    'exclude' => [
        'bootstrap',
        'config',
        'database',
        'resources',
        'storage',
        'vendor',
        'tests/Pest.php',
        'tests/TestCase.php',
    ],

    'add' => [
        //  ExampleMetric::class => [
        //      ExampleInsight::class,
        //  ]
    ],

    'remove' => [
        // Remove specific insights that are not relevant for packages
        ForbiddenDefineFunctions::class,
        ForbiddenNormalClasses::class,
        DeclareStrictTypesSniff::class,
        ForbiddenPublicPropertySniff::class,
        LineLengthSniff::class,
        FunctionLengthSniff::class,
        CyclomaticComplexityIsHigh::class,
        DocCommentSpacingSniff::class,
        OrderedClassElementsFixer::class,
    ],

    'config' => [
        // Adjust specific insights configuration
        OrderedImportsFixer::class => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Requirements
    |--------------------------------------------------------------------------
    |
    | Here you may define a level you want to reach per `Insights` category.
    | When a score is lower than the minimum level defined, then an error
    | code will be returned. This is optional and individually defined.
    |
    */

    'requirements' => [
        'min-quality' => 75,
        'min-complexity' => 80,
        'min-architecture' => 70,
        'min-style' => 80,
        'disable-security-check' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Threads
    |--------------------------------------------------------------------------
    |
    | Here you may adjust how many threads (core) PHPInsights can use to perform
    | the analysis. This is optional, leave blank to use the maximum available.
    | This can significantly speed up the analysis on multi-core systems.
    |
    */

    'threads' => null,

];
