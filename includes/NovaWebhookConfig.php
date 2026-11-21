<?php
/**
 * NovaWebhook - Config Service
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NovaWebhookConfig {
    public const CONSTRUCTOR_OPTIONS = [
        'NovaWebhookEndpoints',
        'NovaWebhookDefaultHooks',
        'NovaWebhookNoBots',
        'NovaWebhookNoMinor',
        'NovaWebhookNoNull',
        'NovaWebhookMaxChars',
        'NovaWebhookDisabledNS',
        'NovaWebhookDisabledUsers',
        'NovaWebhookPrivateExceptionAlerts',
        'NovaWebhookExceptionDenyList',
    ];

    private ServiceOptions $options;
    private LoggerInterface $logger;

    public function __construct(ServiceOptions $options, ?LoggerInterface $logger = null) {
        $this->logger = $logger ?? new NullLogger();
        $options->assertRequiredOptions(self::CONSTRUCTOR_OPTIONS);
        $this->options = $options;
    }

    public static function fromConfig(Config $config, array $definitions, LoggerInterface $logger): self {
        $values = [];
        foreach (self::CONSTRUCTOR_OPTIONS as $canonical) {
            $legacy = $canonical === 'NovaWebhookEndpoints'
                ? 'DiscordWebhooks' : 'Discord' . substr($canonical, strlen('NovaWebhook'));
            $hasCanonical = $config->has($canonical);
            $hasLegacy = $config->has($legacy);
            if ($hasLegacy) {
                $logger->warning('Legacy setting {legacy} is deprecated; use {canonical}{conflict}', [
                    'legacy' => $legacy, 'canonical' => $canonical,
                    'conflict' => $hasCanonical ? '; canonical setting takes precedence' : '',
                ]);
            }
            $values[$canonical] = $hasCanonical ? $config->get($canonical)
                : ($hasLegacy ? $config->get($legacy) : $definitions[$canonical]['value']);
        }
        return new self(new ServiceOptions(self::CONSTRUCTOR_OPTIONS, $values), $logger);
    }

    /**
     * Returns the URLs of webhooks that have $hookName enabled.
     *
     * Each webhook entry in NovaWebhookEndpoints may have an optional 'hooks' key
     * (array of hook names). If absent, the webhook falls back to NovaWebhookDefaultHooks.
     * A null value (the default) means all hooks are enabled for that webhook.
     */
    // Hooks that are never included in the null (all hooks) default and must be explicitly listed.
    private const EXPLICIT_ONLY_HOOKS = ['LogException'];

    public function getWebhooksForHook(string $hookName): array {
        return array_column($this->getDestinationsForHook($hookName), 'url');
    }

    public function getDestinationsForHook(string $hookName): array {
        $defaultHooks = $this->options->get('NovaWebhookDefaultHooks');
        $destinations = [];
        foreach ($this->options->get('NovaWebhookEndpoints') as $webhook) {
            if (!is_array($webhook)) {
                $this->logger->warning('Skipping webhook entry: expected an array');
                continue;
            }
            $url = $webhook['url'] ?? '';
            if (!is_string($url) || !str_starts_with($url, 'https://') || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $format = array_key_exists('format', $webhook) ? $webhook['format'] : 'discord';
            if (!in_array($format, ['discord', 'json'], true)) {
                $this->logger->warning('Skipping webhook entry: format must be discord or json');
                continue;
            }
            $hooks = array_key_exists('hooks', $webhook) ? $webhook['hooks'] : $defaultHooks;
            if ($hooks === null) {
                if (in_array($hookName, self::EXPLICIT_ONLY_HOOKS, true)) {
                    continue;
                }
            } elseif (!is_array($hooks) || !in_array($hookName, $hooks, true)) {
                continue;
            }
            $destinations[] = ['url' => $url, 'format' => $format];
        }
        return $destinations;
    }

    public function isNoBots(): bool {
        return (bool)$this->options->get('NovaWebhookNoBots');
    }

    public function isNoMinor(): bool {
        return (bool)$this->options->get('NovaWebhookNoMinor');
    }

    public function isNoNull(): bool {
        return (bool)$this->options->get('NovaWebhookNoNull');
    }

    public function getMaxChars(): int {
        return (int)$this->options->get('NovaWebhookMaxChars');
    }

    public function getDisabledNS(): array {
        return (array)$this->options->get('NovaWebhookDisabledNS');
    }

    public function getDisabledUsers(): array {
        return (array)$this->options->get('NovaWebhookDisabledUsers');
    }

    public function isPrivateExceptionAlertsEnabled(): bool {
        return (bool)$this->options->get('NovaWebhookPrivateExceptionAlerts');
    }

    public function getExceptionDenyList(): array {
        return (array)$this->options->get('NovaWebhookExceptionDenyList');
    }
}
