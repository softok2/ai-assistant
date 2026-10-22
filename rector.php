<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Rector\If_\ThrowIfRector;
use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;
use RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use RectorLaravel\Rector\FuncCall\ThrowIfAndThrowUnlessExceptionsToUseClassStringRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/public',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // #[\Override] no se añade a las propiedades del código existente.
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        // Job::dispatch() -> dispatch(new \FQN()): cambia el estilo del proyecto sin ganar nada.
        DispatchToHelperFunctionsRector::class,
        // throw_if() evalúa la excepción (y sus argumentos) siempre, aunque la condición sea falsa.
        ThrowIfRector::class,
        ThrowIfAndThrowUnlessExceptionsToUseClassStringRector::class,
        // Carbon::now() -> Date::now(): depende de Date::use() y cambia la clase concreta devuelta.
        CarbonToDateFacadeRector::class,
        // sleep() -> Sleep::sleep(): los tests que usan sleep(0) real pasarían a depender de Sleep::fake().
        SleepFuncToSleepStaticCallRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        codingStyle: true,
    )
    ->withPhpSets(php85: true)
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_COLLECTION,
    ]);
