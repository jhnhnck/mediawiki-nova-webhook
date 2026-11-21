<?php
/**
 * NovaDiscord - AfterImportPage Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaDiscord\ArticleAlert::onAfterImportPage
 */
class AfterImportPageHookTest extends NovaDiscordIntegrationTestCase {
    public function testPageImportSendsWebhook(): void {
        $user = $this->getTestSysop()->getUser();
        \MediaWiki\Context\RequestContext::getMain()->setUser($user);
        $xml = '<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.10/" version="0.10">'
            . '<page><title>NovaImportedPage</title><ns>0</ns><id>123</id><revision><id>456</id>'
            . '<timestamp>2020-01-01T00:00:00Z</timestamp><contributor><username>Importer</username>'
            . '<id>1</id></contributor><model>wikitext</model><format>text/x-wiki</format>'
            . '<text xml:space="preserve">Imported content</text></revision></page></mediawiki>';
        $importer = $this->getServiceContainer()->getWikiImporterFactory()->getWikiImporter(
            new \MediaWiki\Import\ImportStringSource($xml), $user
        );
        $importer->doImport();
        $this->runDeferredUpdates();
        $this->assertNotification('imported');
    }
}
