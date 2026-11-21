<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @coversNothing
 */
class HookRegistrationTest extends NovaDiscordIntegrationTestCase {
    public function testRegisteredHandlersResolveTheirServices(): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../../../extension.json'), true, 512, JSON_THROW_ON_ERROR
        );
        $container = TestingAccessWrapper::newFromObject($this->getServiceContainer()->getHookContainer());
        foreach ($manifest['Hooks'] as $hook => $handler) {
            $callbacks = array_column($container->getHandlers($hook), 'callback');
            $classes = array_map(
                static fn ($callback) => is_array($callback) && is_object($callback[0])
                    ? get_class($callback[0]) : '',
                $callbacks
            );
            $this->assertContains($manifest['HookHandlers'][$handler]['class'], $classes, $hook);
        }
    }
}
