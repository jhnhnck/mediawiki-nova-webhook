<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

use MediaWiki\Extension\NovaWebhook\NovaWebhookConfig;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\NovaWebhookConfig::fromConfig
 */
class FreshInstallConfigTest extends MediaWikiIntegrationTestCase {
    public function testInstalledSettingsResolveWithoutTestOverrides(): void {
        $config = $this->getServiceContainer()->getService('NovaWebhook.Config');
        $this->assertInstanceOf(NovaWebhookConfig::class, $config);
        $this->assertSame([['url' => getenv('NOVA_WEBHOOK_URL'), 'format' => 'discord']],
            $config->getDestinationsForHook('PageSaveComplete'));
        $this->assertFalse($config->isNoBots());
        $this->assertFalse($config->isPrivateExceptionAlertsEnabled());
    }
}
