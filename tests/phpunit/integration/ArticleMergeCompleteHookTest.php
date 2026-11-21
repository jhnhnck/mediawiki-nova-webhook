<?php
/**
 * NovaWebhook - ArticleMergeComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\ArticleAlert::onArticleMergeComplete
 */
class ArticleMergeCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testArticleMergeSendsWebhook(): void {
        $user = $this->getTestSysop()->getUser();
        \MediaWiki\Context\RequestContext::getMain()->setUser($user);
        \MediaWiki\Utils\MWTimestamp::setFakeTime('20200101000000');
        $this->editPage('NovaMergeSource', 'Source');
        \MediaWiki\Utils\MWTimestamp::setFakeTime('20200102000000');
        $this->editPage('NovaMergeDestination', 'Destination');
        $factory = $this->getServiceContainer()->getTitleFactory();
        $merge = $this->getServiceContainer()->getMergeHistoryFactory()->newMergeHistory(
            $factory->newFromText('NovaMergeSource'), $factory->newFromText('NovaMergeDestination')
        );
        $this->runDeferredUpdates();
        $this->mockHttpFactory->reset();
        $this->assertStatusGood($merge->merge($user, 'Merge reason'));
        $this->runDeferredUpdates();
        $this->assertNotification('merged', 2);
        \MediaWiki\Utils\MWTimestamp::setFakeTime(false);
    }
}
