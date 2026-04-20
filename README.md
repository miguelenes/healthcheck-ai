# Ai Chain Health

[![Tests](https://github.com/illuma-law/healthcheck-ai/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/healthcheck-ai/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/healthcheck-ai?label=Version)](https://packagist.org/packages/illuma-law/healthcheck-ai)

**Focused AI failover chain and agent registry health checks for Spatie's Laravel Health package**

This package provides a suite of health checks for [Laravel Ai](https://github.com/laravel/ai), ensuring your LLM and embedding failover chains are responsive and your agents are correctly configured.

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Agent Registry Check](#agent-registry-check)
  - [Embedding Chain Check](#embedding-chain-check)
  - [Prompt Chain Check](#prompt-chain-check)
- [Testing](#testing)
- [Credits](#credits)
- [License](#license)

## Installation

You can install the package via composer:

```bash
composer require illuma-law/healthcheck-ai
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="healthcheck-ai-config"
```

## Configuration

The configuration file allows you to define cache TTLs and canary probe settings:

```php
return [
    'embedding_cache_ttl_seconds' => 300,
    'embedding_dimensions' => 768,
    'embedding_canary_text' => 'health check',
    'prompt_cache_ttl_seconds' => 300,
    'prompt_text' => 'Respond with the word "OK".',
    'prompt_timeout_seconds' => 30,
];
```

## Usage

### Agent Registry Check

Ensures all registered agents have valid credentials and model attributes.

```php
use IllumaLaw\HealthCheckAi\AiAgentRegistryCheck;

AiAgentRegistryCheck::new()
    ->resolveAgentsUsing(fn() => [
        \App\Agents\LegalSummarizer::class,
    ])
    ->hasCredentialsUsing(fn($provider) => config("ai.providers.{$provider}.key") !== null);
```

### Embedding Chain Check

Probes your embedding failover chain to ensure primary and fallback providers are healthy.

```php
use IllumaLaw\HealthCheckAi\AiEmbeddingChainHealthCheck;

AiEmbeddingChainHealthCheck::new()
    ->resolveChainUsing(fn() => [
        ['provider' => 'openai', 'model' => 'text-embedding-3-small'],
        ['provider' => 'gemini', 'model' => 'text-embedding-004'],
    ])
    ->dimensions(768);
```

### Prompt Chain Check

Probes your LLM failover chain to ensure primary and fallback providers are healthy.

```php
use IllumaLaw\HealthCheckAi\AiPromptChainHealthCheck;

AiPromptChainHealthCheck::new()
    ->resolveChainUsing(fn() => [
        ['provider' => 'openai', 'model' => 'gpt-4o'],
        ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet'],
    ])
    ->timeout(25);
```

## Testing

The package includes a comprehensive test suite using Pest.

```bash
composer test
```

## Credits

- [illuma-law](https://github.com/illuma-law)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
