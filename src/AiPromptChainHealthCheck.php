<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AnonymousAgent;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;
use Throwable;

final class AiPromptChainHealthCheck extends Check
{
    private const string CACHE_KEY = 'health:ai:prompt_chain:v1';
    private ?Closure $resolveChainUsing = null;
    private ?int $cacheTtl = null;
    private ?int $timeoutSeconds = null;

    public function resolveChainUsing(Closure $callback): self
    {
        $this->resolveChainUsing = $callback;

        return $this;
    }

    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeoutSeconds = $seconds;

        return $this;
    }

    public function run(): Result
    {
        if (!$this->resolveChainUsing) {
            return Result::make()->failed('Missing chain resolver for AiPromptChainHealthCheck');
        }

        $ttl = $this->cacheTtl ?? (int) config('healthcheck-ai.prompt_cache_ttl_seconds', 300);

        /** @var array{skipped: bool, reason: string|null, primary_ok: bool, winner: array<string, mixed>|null, error: string|null} $payload */
        $payload = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->probe());

        if ($payload['skipped']) {
            return (new Result(Status::skipped(), (string) ($payload['reason'] ?? 'Prompt chain probe skipped.')))
                ->meta(['cached' => true, 'cache_ttl_seconds' => $ttl])
                ->shortSummary('Skipped');
        }

        $meta = [
            'cached'            => true,
            'cache_ttl_seconds' => $ttl,
            'winner'            => $payload['winner'],
        ];

        if ($payload['error']) {
            return Result::make()
                ->meta($meta)
                ->shortSummary('Failed')
                ->failed($payload['error']);
        }

        $result = Result::make()
            ->meta($meta)
            ->shortSummary($payload['primary_ok'] ? 'Primary OK' : 'Degraded');

        if (!$payload['primary_ok']) {
            return $result->warning(__('healthcheck-ai::messages.prompt_chain.primary_failed_fallback_ok'));
        }

        return $result->ok(__('healthcheck-ai::messages.prompt_chain.ok'));
    }

    private function probe(): array
    {
        $chain = ($this->resolveChainUsing)();

        if (empty($chain)) {
            return [
                'skipped'    => true,
                'reason'     => 'No AI failover chain is configured.',
                'primary_ok' => false,
                'winner'     => null,
                'error'      => null,
            ];
        }

        $prompt = (string) config('healthcheck-ai.prompt_text', 'Reply with exactly: OK');
        $timeout = $this->timeoutSeconds ?? (int) config('healthcheck-ai.prompt_timeout_seconds', 25);

        foreach ($chain as $index => $step) {
            try {
                $agent = new AnonymousAgent('You are a terse health probe. Follow the user instruction exactly.', [], []);
                $response = $agent->prompt($prompt, [], $step['provider'], $step['model'], $timeout);

                return [
                    'skipped'    => false,
                    'reason'     => null,
                    'primary_ok' => $index === 0,
                    'winner'     => [
                        'provider' => $step['provider'],
                        'model'    => $step['model'],
                    ],
                    'error' => null,
                ];
            } catch (Throwable $e) {
                if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PEST__')) {
                    // fwrite(STDERR, "Step $index failed: " . $e->getMessage() . "\n");
                }
                // Continue to next in chain
                if ($index === count($chain) - 1) {
                    return [
                        'skipped'    => false,
                        'reason'     => null,
                        'primary_ok' => false,
                        'winner'     => null,
                        'error'      => mb_substr($e->getMessage(), 0, 400),
                    ];
                }
            }
        }

        throw new \LogicException('Unreachable state in AiPromptChainHealthCheck');
    }
}
