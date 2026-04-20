<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Closure;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use ReflectionClass;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class AiAgentRegistryCheck extends Check
{
    private ?Closure $resolveAgentsUsing = null;

    private ?Closure $hasCredentialsUsing = null;

    public function resolveAgentsUsing(Closure $callback): self
    {
        $this->resolveAgentsUsing = $callback;

        return $this;
    }

    public function hasCredentialsUsing(Closure $callback): self
    {
        $this->hasCredentialsUsing = $callback;

        return $this;
    }

    public function run(): Result
    {
        if (! $this->resolveAgentsUsing || ! $this->hasCredentialsUsing) {
            return Result::make()->failed('Missing required resolvers for AiAgentRegistryCheck');
        }

        $agents = ($this->resolveAgentsUsing)();
        $missingCredentials = [];
        $missingModel = [];
        $withoutProvider = [];

        foreach ($agents as $agentClass) {
            $ref = new ReflectionClass($agentClass);
            $providerAttrs = $ref->getAttributes(Provider::class);

            if ($providerAttrs === []) {
                $withoutProvider[] = $agentClass;

                continue;
            }

            $provider = $providerAttrs[0]->newInstance()->value;

            if (! ($this->hasCredentialsUsing)($provider)) {
                $missingCredentials[] = "{$agentClass} ({$provider})";

                continue;
            }

            $modelAttrs = $ref->getAttributes(Model::class);
            if ($modelAttrs === []) {
                $missingModel[] = $agentClass;
            }
        }

        $meta = [
            'agents_checked'             => count($agents),
            'without_provider_attribute' => array_slice($withoutProvider, 0, 20),
            'missing_model_attribute'    => array_slice($missingModel, 0, 20),
        ];

        $result = Result::make()->meta($meta);

        if ($missingCredentials !== []) {
            return $result
                ->failed(__('healthcheck-ai::messages.agent_registry.missing_credentials', [
                    'agents' => implode('; ', array_slice($missingCredentials, 0, 6)).(count($missingCredentials) > 6 ? '…' : ''),
                ]))
                ->shortSummary(count($missingCredentials).' issue(s)');
        }

        if ($missingModel !== []) {
            return $result
                ->warning(__('healthcheck-ai::messages.agent_registry.missing_model', [
                    'agents' => implode('; ', array_slice($missingModel, 0, 6)).(count($missingModel) > 6 ? '…' : ''),
                ]))
                ->shortSummary(count($missingModel).' hint(s)');
        }

        return $result
            ->ok(__('healthcheck-ai::messages.agent_registry.ok'))
            ->shortSummary('OK');
    }
}
