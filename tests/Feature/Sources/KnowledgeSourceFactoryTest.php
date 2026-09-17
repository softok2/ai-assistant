<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\Sources\KnowledgeSourceFactory;
use App\Ai\Sources\Drivers\PentahoMdsSource;
use App\Ai\Sources\Drivers\BiKnowledgeManifestSource;

beforeEach(function () {
    config(['knowledge.sources' => [
        'ccm' => ['driver' => 'pentaho', 'base_url' => 'https://mds.test/api/', 'username' => 'u', 'password' => 'p', 'files' => ['golf-output']],
        'vallealto' => ['driver' => 'bi_knowledge', 'base_url' => 'https://va.test/bi/knowledge', 'username' => 'bi', 'password' => 's'],
    ]]);
});

it('builds the driver of each configured club', function () {
    $factory = app(KnowledgeSourceFactory::class);

    expect($factory->for(ClubName::CCM))->toBeInstanceOf(PentahoMdsSource::class)
        ->and($factory->for(ClubName::VALLEALTO))->toBeInstanceOf(BiKnowledgeManifestSource::class)
        ->and($factory->for(ClubName::TERRALTA))->toBeNull()
        ->and($factory->clubs())->toBe([ClubName::CCM, ClubName::VALLEALTO]);
});

it('rejects an unknown driver', function () {
    config(['knowledge.sources.ccm.driver' => 'ftp']);

    app(KnowledgeSourceFactory::class)->for(ClubName::CCM);
})->throws(InvalidArgumentException::class, 'ftp');

it('rejects a missing driver key without a PHP warning', function () {
    config(['knowledge.sources.ccm' => ['base_url' => 'https://mds.test/api/']]);

    app(KnowledgeSourceFactory::class)->for(ClubName::CCM);
})->throws(InvalidArgumentException::class, 'null');
