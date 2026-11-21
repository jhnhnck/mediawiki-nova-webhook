<?php
/**
 * NovaWebhook - User-related hooks
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Specials\Hook\BlockIpCompleteHook;
use MediaWiki\Specials\Hook\UnblockUserCompleteHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Hook\UserGroupsChangedHook;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\UrlUtils;

class UserAlert extends WebhookAlert implements LocalUserCreatedHook, BlockIpCompleteHook, UnblockUserCompleteHook, UserGroupsChangedHook {
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

    /**
     * Called when a user is created
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
     */
    public function onLocalUserCreated($user, $autocreated) {
        $hookName = 'LocalUserCreated';

        if (!$this->isEnabled($hookName, null, $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $user->getRegistration()),
            $this->describeUser($user), null, ['autocreated' => (bool)$autocreated]
        ));
    }

    /**
     * Called when a user is blocked
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/BlockIpComplete
     */
    public function onBlockIpComplete($block, $user, $priorBlock) {
        $hookName = 'BlockIpComplete';

        if (!$this->isEnabled($hookName, null, $user)) {
            return;
        }

        $target = $block->getTargetUserIdentity();
        $targetData = $target !== null
            ? $this->describeUser($this->userFactory->newFromUserIdentity($target))
            : ['id' => null, 'name' => $block->getTargetName(), 'url' => null,
                'talk_url' => null, 'contributions_url' => null];
        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $block->getTimestamp()), $this->describeUser($user), null, [
                'target' => $targetData, 'reason' => $block->getReasonComment()->text, 'expiry' => $block->getExpiry(),
            ]
        ));
    }

    /**
     * Called when a user is unblocked
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnblockUserComplete
     */
    public function onUnblockUserComplete($block, $user) {
        $hookName = 'UnblockUserComplete';

        if (!$this->isEnabled($hookName, null, $user)) {
            return;
        }

        $target = $block->getTargetUserIdentity();
        $targetData = $target !== null
            ? $this->describeUser($this->userFactory->newFromUserIdentity($target))
            : ['id' => null, 'name' => $block->getTargetName(), 'url' => null,
                'talk_url' => null, 'contributions_url' => null];
        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), null, [
                'target' => $targetData,
            ]
        ));
    }

    /**
     * Called when a user's rights are changed
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGroupsChanged
     */
    public function onUserGroupsChanged($user, $added, $removed, $performer, $reason, $oldUGMs, $newUGMs) {
        $hookName = 'UserGroupsChanged';

        if ($performer === false) {
            // Rights were changed by auto-promotion, do nothing
            return;
        }

        if (!$this->isEnabled($hookName, null, $performer)) {
            return;
        }

        if ($user instanceof UserIdentity && !($user instanceof User)) {
            $user = $this->userFactory->newFromUserIdentity($user);
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($performer), null, [
                'target' => $this->describeUser($user), 'reason' => $reason ?: '',
                'added' => array_values($added), 'removed' => array_values($removed),
            ]
        ));
    }
}
