<?php

/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

/**
 * Copyright (c) 2026 guanguans<ityaozm@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/GuansGuans/swiss-knife
 */

use App\Support\Rector\ClassHandleMethodRector;
use App\Support\Rector\MixinStaticRector;
use Ergebnis\Rector\Rules\Expressions\Arrays\SortAssociativeArrayByKeyRector;
use Guanguans\PhpCsFixerCustomFixers\Support\Utils;
use Guanguans\RectorRules\Rector\Array_\SortListItemOfSameScalarTypeRector;
use Guanguans\RectorRules\Rector\File\AddNoinspectionDocblockToFileFirstStmtRector;
use Guanguans\RectorRules\Rector\FunctionLike\RenameGarbageParamNameRector;
use Guanguans\RectorRules\Rector\Name\RenameToConventionalCaseNameRector;
use Guanguans\RectorRules\Rector\Scalar\StringToClassConstantOfRequestOptionsRector;
use Guanguans\RectorRules\Set\SetList;
use Pest\Rector\Rules\ChainExpectCallsRector;
use Pest\Rector\Set\PestSetList;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\LogicalAnd\LogicalToBooleanRector;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\CodingStyle\Rector\Assign\SplitDoubleAssignRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StmtsAwareInterface\RemoveDeadInstanceOfAssertRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\ArrayDimFetch\ArrayToArrGetRector;
use RectorLaravel\Rector\Empty_\EmptyToBlankAndFilledFuncRector;
use RectorLaravel\Rector\FuncCall\HelperFuncCallToFacadeClassRector;
use RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector;
use RectorLaravel\Rector\FuncCall\TypeHintTappableCallRector;
use RectorLaravel\Rector\If_\ThrowIfRector;
use RectorLaravel\Rector\MethodCall\ValidationRuleArrayStringValueToArrayRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use RectorLaravel\Rector\StaticCall\RequestStaticValidateToInjectRector;

error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);

return RectorConfig::configure()
    ->withPaths([...Utils::defaultRootDirectories(), ...Utils::defaultRootFiles(), __DIR__.'/bootstrap/app.php'])
    ->withSkip(['*.blade.php', '*/Fixtures/*', __DIR__.'/app/Support/VarDumper/ServerDumper.php'])
    ->withSkip([
        // Skip common rules.
        ChainExpectCallsRector::class,
        LogicalToBooleanRector::class,
        NewlineBetweenClassLikeStmtsRector::class,
        PreferPHPUnitThisCallRector::class,
        SplitDoubleAssignRector::class,
    ])
    ->withSkip([
        ArrayToArrGetRector::class,
        DispatchToHelperFunctionsRector::class,
        EmptyToBlankAndFilledFuncRector::class,
        HelperFuncCallToFacadeClassRector::class,
        RequestStaticValidateToInjectRector::class,
        ThrowIfRector::class,
        ValidationRuleArrayStringValueToArrayRector::class,
    ])
    ->withSkip([
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            // __DIR__.'/app/Support/Mixin/',
        ],
        CompleteDynamicPropertiesRector::class => [
            __DIR__.'/app/Support/Mixin/',
        ],
        RemoveDeadInstanceOfAssertRector::class => [
            __DIR__.'/bootstrap/app.php',
        ],
        RemoveDumpDataDeadCodeRector::class => [
            __DIR__.'/app/Support/Mixin/SchedulingEventMixin.php',
        ],
        RemoveExtraParametersRector::class => [
            __DIR__.'/app/Support/Mixin/',
        ],
        RenameGarbageParamNameRector::class => [
            '*Abstract*.php',
        ],
        RenameToConventionalCaseNameRector::class => [
            __DIR__.'/app/Models/',
        ],
        SortAssociativeArrayByKeyRector::class => [
            __DIR__.'/app/',
            __DIR__.'/bootstrap/app.php',
            __DIR__.'/config/',
            __DIR__.'/database/',
        ],
        SortListItemOfSameScalarTypeRector::class => [
            __DIR__.'/app/Console/Commands/NetEaseCloudMusicCommand.php',
            __DIR__.'/config/',
            __DIR__.'/database/migrations/',
        ],
        StringClassNameToClassConstantRector::class => [
            __DIR__.'/bootstrap/app.php',
            __DIR__.'/tests/Arch/',
        ],
        StringToClassConstantOfRequestOptionsRector::class => [
            __DIR__.'/config/telescope.php',
        ],
        TypeHintTappableCallRector::class => [
            __DIR__.'/bootstrap/app.php',
        ],
    ])
    ->withCache(__DIR__.'/.build/rector/')
    // ->withoutParallel()
    ->withParallel()
    ->withImportNames(importDocBlockNames: false, importShortClasses: false, removeUnusedImports: false)
    // ->withImportNames(true, false, false, false)
    ->reportUnusedSkips()
    ->withFluentCallNewLine()
    ->withTreatClassesAsFinal()
    ->withTypeGuardedClasses([])
    ->withAttributesSets(phpunit: true, all: true)
    ->withComposerBased(phpunit: true, laravel: true)
    ->withPhpVersion(PhpVersion::PHP_85)
    // ->withDowngradeSets(php85: true)
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: true,
        // namedArgs: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
        phpunitNarrowAsserts: true,
        phpunitMockToStub: true,
    )
    ->withSets([
        PestSetList::CODING_STYLE,
        SetList::ALL,
    ])
    ->withRules([
        ClassHandleMethodRector::class,
        MixinStaticRector::class,
    ])
    ->withConfiguredRule(AddNoinspectionDocblockToFileFirstStmtRector::class, [
        '*/app/Support/Mixin/*' => [
            'OverrideMissingInspection',
        ],
        '*/tests/*' => [
            'AnonymousFunctionStaticInspection',
            'NullPointerExceptionInspection',
            'PhpPossiblePolymorphicInvocationInspection',
            'PhpUndefinedClassInspection',
            'PhpUnhandledExceptionInspection',
            'PhpVoidFunctionResultUsedInspection',
            'StaticClosureCanBeUsedInspection',
        ],
        '*/tests/Arch/*' => [
            'ClassConstantCanBeUsedInspection',
            'ClassnameLiteralInspection',
        ],
    ])
    ->withConfiguredRule(RenameToConventionalCaseNameRector::class, [
        'DB',
        'Debug',
        'MIT',
        'PDO',
        'URL',
        'Value',
        'afterEach',
        'beforeEach',
        'current_password',
        'new_password',
    ]);
