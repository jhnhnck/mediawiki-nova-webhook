<?php
/**
 * NovaWebhook - Service Wiring
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

use MediaWiki\Extension\NovaWebhook\NovaWebhookConfig;
use MediaWiki\Extension\NovaWebhook\NovaWebhookLoggerSpi;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\MediaWikiServices;

return [
    'NovaWebhook.Config' => static function (MediaWikiServices $services): NovaWebhookConfig {
        return NovaWebhookConfig::fromConfig(
            $services->getMainConfig(),
            $services->getExtensionRegistry()->getAttribute('NovaWebhookConfigDefaults'),
            $services->getService('NovaWebhook.LoggerSpi')->getLogger('nova-webhook')
        );
    },
    // LoggerFactory is a static facade and not always a registered service in all MW versions;
    // wrap it here so handlers can receive LoggerSpi via DI without depending on the service name
    'NovaWebhook.LoggerSpi' => static function (MediaWikiServices $services): LoggerSpi {
        return new NovaWebhookLoggerSpi();
    },
];
