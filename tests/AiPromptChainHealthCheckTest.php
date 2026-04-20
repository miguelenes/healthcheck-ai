<?php

declare(strict_types=1);

use IllumaLaw\HealthCheckAi\AiPromptChainHealthCheck;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Spatie\Health\Enums\Status;

it('fails if chain resolver is missing', function () {
    $result = AiPromptChainHealthCheck::new()->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->notificationMessage)->toContain('Missing chain resolver');
});

it('skips when chain is empty', function () {
    $result = AiPromptChainHealthCheck::new()
        ->resolveChainUsing(fn () => [])
        ->run();

    expect($result->status)->toEqual(Status::skipped())
        ->and($result->shortSummary)->toBe('Skipped');
});

it('succeeds when primary prompt succeeds', function () {
    Ai::fakeAgent(AnonymousAgent::class, [
        new TextResponse('OK', new Usage(0, 0, 0), new Meta('openai', 'gpt-4o')),
    ]);

    $result = AiPromptChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'gpt-4o'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::ok())
        ->and($result->shortSummary)->toBe('Primary OK');
});

it('warns when primary fails but fallback succeeds', function () {
    $called = 0;
    Ai::fakeAgent(AnonymousAgent::class, function () use (&$called) {
        $called++;
        if ($called === 1) {
            throw new Exception('Primary failed');
        }

        return new TextResponse('OK', new Usage(0, 0, 0), new Meta('anthropic', 'ok'));
    });

    $result = AiPromptChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'fail'],
            ['provider' => 'anthropic', 'model' => 'ok'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::warning())
        ->and($result->shortSummary)->toBe('Degraded');
});

it('fails when all prompts fail', function () {
    Ai::fakeAgent(AnonymousAgent::class, function () {
        throw new Exception('All failed');
    });

    $result = AiPromptChainHealthCheck::new()
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'fail1'],
            ['provider' => 'anthropic', 'model' => 'fail2'],
        ])
        ->run();

    expect($result->status)->toEqual(Status::failed())
        ->and($result->shortSummary)->toBe('Failed');
});

it('can be configured with fluent methods', function () {
    Ai::fakeAgent(AnonymousAgent::class, [
        new TextResponse('OK', new Usage(0, 0, 0), new Meta('openai', 'gpt-4o')),
    ]);

    $check = AiPromptChainHealthCheck::new()
        ->cacheTtl(600)
        ->timeout(10)
        ->resolveChainUsing(fn () => [
            ['provider' => 'openai', 'model' => 'gpt-4o'],
        ]);

    expect($check)->toBeInstanceOf(AiPromptChainHealthCheck::class);

    $result = $check->run();
    expect($result->status)->toEqual(Status::ok())
        ->and($result->meta['cached'])->toBeTrue();
});

it('fails when resolver is not callable', function () {
    $check = AiPromptChainHealthCheck::new();

    $ref = new ReflectionClass($check);
    $prop = $ref->getProperty('resolveChainUsing');
    $prop->setAccessible(true);
    $prop->setValue($check, 'not-a-callable');

    $result = $check->run();
    expect($result->status)->toEqual(Status::skipped())
        ->and($result->shortSummary)->toBe('Skipped');
});
