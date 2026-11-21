<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\NovaWebhook\NovaWebhookConfig;
use Psr\Log\NullLogger;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\NovaWebhookConfig::fromConfig
 */
class ConfigMigrationTest extends NovaWebhookIntegrationTestCase {
    /** @dataProvider provideSettings */
    public function testExplicitSettingsPrecedeDefaults(
        array $settings, array $urls, bool $noBots, bool $private
    ): void {
        $definitions = $this->getServiceContainer()->getExtensionRegistry()->getAttribute('NovaWebhookConfigDefaults');
        $config = NovaWebhookConfig::fromConfig(new HashConfig($settings), $definitions, new NullLogger());
        $this->assertSame($urls, $config->getWebhooksForHook('PageSaveComplete'));
        $this->assertSame($noBots, $config->isNoBots());
        $this->assertSame($private, $config->isPrivateExceptionAlertsEnabled());
    }

    public static function provideSettings(): array {
        $url = 'https://receiver.example.test/events';
        return [
            'defaults' => [[], [], true, false],
            'legacy only' => [[
                'DiscordWebhooks' => [['url' => $url]], 'DiscordNoBots' => false,
            ], [$url], false, false],
            'canonical only' => [[
                'NovaWebhookEndpoints' => [['url' => $url]], 'NovaWebhookNoBots' => false,
            ], [$url], false, false],
            'explicit canonical empty and false' => [[
                'DiscordWebhooks' => [['url' => $url]], 'NovaWebhookEndpoints' => [],
                'DiscordPrivateExceptionAlerts' => true, 'NovaWebhookPrivateExceptionAlerts' => false,
            ], [], true, false],
            'canonical null means all hooks' => [[
                'NovaWebhookEndpoints' => [['url' => $url]],
                'DiscordDefaultHooks' => [], 'NovaWebhookDefaultHooks' => null,
            ], [$url], true, false],
            'endpoint aliases do not merge' => [[
                'DiscordWebhooks' => [['url' => $url]], 'NovaWebhookEndpoints' => [['url' => $url]],
            ], [$url], true, false],
            'legacy explicit private opt in' => [[
                'DiscordPrivateExceptionAlerts' => true,
            ], [], true, true],
        ];
    }

    public function testLegacyDiagnosticsExcludeValues(): void {
        $logger = new class extends NullLogger {
            public array $records = [];

            public function warning($message, array $context = []): void {
                $this->records[] = [$message, $context];
            }
        };
        $definitions = $this->getServiceContainer()->getExtensionRegistry()->getAttribute('NovaWebhookConfigDefaults');
        NovaWebhookConfig::fromConfig(new HashConfig([
            'DiscordWebhooks' => [['url' => 'https://secret.example.test/credential']],
            'NovaWebhookEndpoints' => [],
        ]), $definitions, $logger);
        $this->assertCount(1, $logger->records);
        $this->assertStringNotContainsString('secret.example', json_encode($logger->records));
        $this->assertStringContainsString('precedence', json_encode($logger->records));
    }
}
