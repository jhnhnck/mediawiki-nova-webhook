<?php
/**
 * NovaWebhook - LoggerSpi wrapper
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logger\Spi as LoggerSpi;
use Psr\Log\LoggerInterface;

/**
 * LoggerSpi implementation that delegates to the MW LoggerFactory static API.
 * Used as a custom service so handlers can receive LoggerSpi via DI without
 * depending on 'LoggerFactory' being a registered service (it isn't in all MW versions).
 */
class NovaWebhookLoggerSpi implements LoggerSpi {
    /** @inheritDoc */
    public function getLogger( $channel ): LoggerInterface {
        return LoggerFactory::getInstance( $channel );
    }
}
