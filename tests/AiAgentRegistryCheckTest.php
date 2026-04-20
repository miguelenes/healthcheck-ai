<?php

declare(strict_types=1);

use IllumaLaw\HealthCheckAi\AiAgentRegistryCheck;
use IllumaLaw\HealthCheckAi\Tests\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Spatie\Health\Enums\Status;

uses(TestCase::class);

#[Provider('openai')]
#[Model('gpt-4')]
class ValidAgent {}

#[Provider('anthropic')]
class MissingModelAgent {}

class NoProviderAgent {}

it('fails if resolvers are missing', function () {
    $result = AiAgentRegistryCheck::new()->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->notificationMessage)->toContain('Missing required resolvers');
});

it('succeeds when all agents are valid', function () {
    $result = AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => [ValidAgent::class])
        ->hasCredentialsUsing(fn ($provider) => $provider === 'openai')
        ->run();

    expect($result->status)->toEqual(Status::ok())
        ->and($result->shortSummary)->toBe('OK');
});

it('fails when credentials are missing', function () {
    $result = AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => [ValidAgent::class])
        ->hasCredentialsUsing(fn ($provider) => false)
        ->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->shortSummary)->toBe('1 issue(s)');
});

it('warns when model attribute is missing', function () {
    $result = AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => [MissingModelAgent::class])
        ->hasCredentialsUsing(fn ($provider) => true)
        ->run();

    expect($result->status)->toEqual(Status::warning())
        ->and($result->shortSummary)->toBe('1 hint(s)');
});

it('skips agents without provider attribute', function () {
    $result = AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => [NoProviderAgent::class])
        ->hasCredentialsUsing(fn ($provider) => true)
        ->run();

    expect($result->status)->toEqual(Status::ok())
        ->and($result->meta['without_provider_attribute'])->toContain(NoProviderAgent::class);
});

it('truncates missing credentials list when too many', function () {
    $agents = [];
    for ($i = 0; $i < 10; $i++) {
        $agents[] = ValidAgent::class;
    }

    $result = AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => $agents)
        ->hasCredentialsUsing(fn ($provider) => false)
        ->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->notificationMessage)->toContain('…');
});

it('truncates missing model list when too many', function () {
    $agents = [];
    for ($i = 0; $i < 10; $i++) {
        $agents[] = MissingModelAgent::class;
    }

    $result = AiAgentRegistryCheck::new()
        ->resolveAgentsUsing(fn () => $agents)
        ->hasCredentialsUsing(fn ($provider) => true)
        ->run();

    expect($result->status)->toEqual(Status::warning())
        ->and($result->notificationMessage)->toContain('…');
});
