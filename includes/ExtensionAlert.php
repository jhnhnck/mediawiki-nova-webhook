<?php
/**
 * NovaWebhook - User rename hook
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook;

use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\RenameUser\Hook\RenameUserCompleteHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;

class ExtensionAlert extends WebhookAlert implements RenameUserCompleteHook {
    private UserFactory $userFactory;

    public function __construct(HttpRequestFactory $httpFactory,
                                RevisionLookup $revLookup,
                                TitleFactory $titleFactory,
                                UrlUtils $urlUtils,
                                UserFactory $userFactory,
                                NovaWebhookConfig $novaConfig,
                                LoggerSpi $loggerSpi) {
        $this->userFactory = $userFactory;
        parent::__construct($httpFactory, $revLookup, $titleFactory, $urlUtils, $novaConfig, $loggerSpi);
    }

    /** @inheritDoc */
    public function onRenameUserComplete(int $uid, string $old, string $new): void {
        $hookName = 'RenameUserComplete';

        $user = $this->userFactory->newFromUserIdentity(RequestContext::getMain()->getUser());

        if (!$this->isEnabled($hookName, null, $user)) {
            return;
        }

        $renamedUserPage = $this->userFactory->newFromName($new)->getUserPage();

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), null, [
                'user_id' => $uid, 'old_name' => $old, 'new_name' => $new,
                'new_user_url' => $renamedUserPage->getCanonicalURL(),
            ]
        ));
    }
}
