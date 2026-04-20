<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Embeddings;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

final class AiEmbeddingChainHealthCheck extends Check
{
    private const string CACHE_KEY = 'health:ai:embedding_chain:v1';
    private ?Closure $resolveChainUsing = null;
    private ?int $dimensions = null;
    private ?int $cacheTtl = null;

    public function resolveChainUsing(Closure $callback): self
    {
        $this->resolveChainUsing = $callback;

        return $this;
    }

    public function dimensions(int $dimensions): self
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function run(): Result
    {
        if (!$this->resolveChainUsing) {
            return Result::make()->failed('Missing chain resolver for AiEmbeddingChainHealthCheck');
        }

        $ttl = $this->cacheTtl ?? (int) config('healthcheck-ai.embedding_cache_ttl_seconds', 300);

        /** @var array{results: list<array<string, mixed>>, primary_ok: bool, dimensions: int} $payload */
        $payload = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->probe());

        $meta = [
            'cached'            => true,
            'cache_ttl_seconds' => $ttl,
            'dimensions'        => $payload['dimensions'],
            'steps'             => $payload['results'],
        ];

        $result = Result::make()->meta($meta)->shortSummary($payload['primary_ok'] ? 'Primary OK' : 'Primary degraded');

        if ($payload['results'] === []) {
            return $result->failed(__('healthcheck-ai::messages.embedding_chain.no_providers'));
        }

        if (!$payload['primary_ok']) {
            $hadOk = collect($payload['results'])->contains(fn (array $r): bool => ($r['status'] ?? '') === 'ok');

            return $hadOk
                ? $result->warning(__('healthcheck-ai::messages.embedding_chain.primary_failed_fallback_ok'))
                : $result->failed(__('healthcheck-ai::messages.embedding_chain.all_failed'));
        }

        return $result->ok(__('healthcheck-ai::messages.embedding_chain.ok'));
    }

    private function probe(): array
    {
        $dimensions = $this->dimensions ?? (int) config('healthcheck-ai.embedding_dimensions', 768);
        $chain = ($this->resolveChainUsing)();
        $results = [];
        $primaryOk = false;

        if (empty($chain)) {
            return ['results' => [], 'primary_ok' => false, 'dimensions' => $dimensions];
        }

        $canary = config('healthcheck-ai.embedding_canary_text', 'health check');

        foreach ($chain as $index => $step) {
            $provider = $step['provider'];
            $model = $step['model'];
            $startNs = hrtime(true);

            try {
                $response = Embeddings::for([$canary])
                    ->dimensions($dimensions)
                    ->generate($provider, $model);

                $latencyMs = round((hrtime(true) - $startNs) / 1_000_000, 1);
                $vector = $response->embeddings[0] ?? [];

                $status = (is_array($vector) && count($vector) === $dimensions) ? 'ok' : 'dimension_mismatch';
                $vectorDim = is_array($vector) ? count($vector) : 0;

                $results[] = [
                    'index'        => $index,
                    'provider'     => $provider,
                    'model'        => $model,
                    'status'       => $status,
                    'latency_ms'   => $latencyMs,
                    'vector_dim'   => $vectorDim,
                    'expected_dim' => $dimensions,
                ];

                if ($index === 0 && $status === 'ok') {
                    $primaryOk = true;
                }
            } catch (Throwable $e) {
                $latencyMs = round((hrtime(true) - $startNs) / 1_000_000, 1);
                $results[] = [
                    'index'        => $index,
                    'provider'     => $provider,
                    'model'        => $model,
                    'status'       => 'error',
                    'latency_ms'   => $latencyMs,
                    'error'        => mb_substr($e->getMessage(), 0, 256),
                ];
            }
        }

        return [
            'results'    => $results,
            'primary_ok' => $primaryOk,
            'dimensions' => $dimensions,
        ];
    }
}
