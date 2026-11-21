<?php
/**
 * NovaWebhook - LogException Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\NovaWebhook\ErrorAlert;
use MediaWiki\Extension\NovaWebhook\NovaWebhookConfig;
use RuntimeException;
use Wikimedia\Assert\PreconditionException;

/**
 * @group Database
 * @covers MediaWiki\Extension\NovaWebhook\ErrorAlert::onLogException
 */
class ErrorAlertHookTest extends NovaWebhookIntegrationTestCase {
    private const WEBHOOK_URL = 'https://novawebhook.local/webhook/exception-test';

    /**
     * Build an ErrorAlert with a fresh NovaWebhookConfig from the given overrides,
     * so each test can dictate config in isolation without touching the global service.
     */
    private function buildAlert(array $overrides): ErrorAlert {
        $defaults = [
            'NovaWebhookEndpoints' => [
                ['url' => self::WEBHOOK_URL, 'hooks' => ['LogException']],
                ['url' => 'https://events.example.test/errors', 'format' => 'json', 'hooks' => ['LogException']],
            ],
            'NovaWebhookDefaultHooks' => null,
            'NovaWebhookNoBots' => true,
            'NovaWebhookNoMinor' => false,
            'NovaWebhookNoNull' => true,
            'NovaWebhookMaxChars' => 500,
            'NovaWebhookDisabledNS' => [],
            'NovaWebhookDisabledUsers' => [],
            'NovaWebhookPrivateExceptionAlerts' => true,
            'NovaWebhookExceptionDenyList' => [],
        ];
        $config = new NovaWebhookConfig(
            new ServiceOptions(
                NovaWebhookConfig::CONSTRUCTOR_OPTIONS,
                new HashConfig(array_merge($defaults, $overrides))
            )
        );
        $services = $this->getServiceContainer();
        return new ErrorAlert(
            $this->mockHttpFactory,
            $services->getRevisionLookup(),
            $services->getTitleFactory(),
            $services->getUrlUtils(),
            $config
        );
    }

    public function testEmptyDenyListPostsWebhook(): void {
        $alert = $this->buildAlert(['NovaWebhookExceptionDenyList' => []]);
        $alert->onLogException(new RuntimeException('NovaTest empty deny ' . uniqid()), false);
        $this->assertCount(1, $this->getDiscordRequests(),
            'Empty deny-list should not suppress any exception');
    }

    public function testPrivacyAndExplicitRoutingGates(): void {
        foreach ([
            ['NovaWebhookPrivateExceptionAlerts' => false],
            ['NovaWebhookEndpoints' => [['url' => self::WEBHOOK_URL]]],
        ] as $overrides) {
            $this->buildAlert($overrides)->onLogException(new RuntimeException('Private event'), false);
            $this->assertCount(0, $this->getDiscordRequests());
        }
        $this->buildAlert([])->onLogException(new RuntimeException('Suppressed event'), true);
        $this->assertCount(0, $this->getDiscordRequests());
    }

    public function testRepeatedExceptionIsRateLimited(): void {
        $this->assertTrue(apcu_enabled(), 'The integration runner must enable APCu for CLI');
        $alert = $this->buildAlert([]);
        $error = new RuntimeException('Repeated exception ' . uniqid());
        $alert->onLogException($error, false);
        $alert->onLogException($error, false);
        $this->assertCount(1, $this->getDiscordRequests());
    }

    public function testBareClassEntrySuppressesAllInstances(): void {
        $alert = $this->buildAlert(['NovaWebhookExceptionDenyList' => [RuntimeException::class]]);
        $alert->onLogException(new RuntimeException('NovaTest bare class ' . uniqid()), false);
        $this->assertCount(0, $this->getDiscordRequests(),
            'Bare class entry should suppress matching exception');
    }

    public function testMessageContainsSuppressesOnlyMatchingMessage(): void {
        $denyList = [
            ['class' => RuntimeException::class, 'messageContains' => 'IGNORE-ME'],
        ];

        // matching message should be suppressed
        $alert = $this->buildAlert(['NovaWebhookExceptionDenyList' => $denyList]);
        $alert->onLogException(new RuntimeException('NovaTest IGNORE-ME ' . uniqid()), false);
        $this->assertCount(0, $this->getDiscordRequests(),
            'Matching messageContains should suppress the alert');

        // non-matching message in same class should still post
        $alert->onLogException(new RuntimeException('NovaTest report-me ' . uniqid()), false);
        $this->assertCount(1, $this->getDiscordRequests(),
            'Non-matching message of same class should still post');
    }

    public function testDenyListAppliesEvenWhenPrivateAlertsDisabled(): void {
        // verifies the deny-list filter runs *before* the private-alerts gate, so a
        // future re-enable of NovaWebhookPrivateExceptionAlerts cannot leak suppressed errors
        $alert = $this->buildAlert([
            'NovaWebhookPrivateExceptionAlerts' => false,
            'NovaWebhookExceptionDenyList' => [PreconditionException::class],
        ]);
        $alert->onLogException(
            new PreconditionException('NovaTest precondition ' . uniqid()),
            false
        );
        $this->assertCount(0, $this->getDiscordRequests(),
            'Deny-listed exception must remain suppressed when private alerts toggl'
                . 'e on later');
    }
}
