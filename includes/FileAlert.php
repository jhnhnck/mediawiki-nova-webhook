<?php
/**
 * NovaWebhook - File-related hooks
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook;

use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Specials\Hook\FileUndeleteCompleteHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Upload\Hook\UploadCompleteHook;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;

class FileAlert extends WebhookAlert implements UploadCompleteHook, FileDeleteCompleteHook, FileUndeleteCompleteHook {
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
    public function onUploadComplete($uploadBase) {
        $hookName = 'UploadComplete';

        $lf = $uploadBase->getLocalFile();
        $uploader = $lf->getUploader();
        if ($uploader === null) {
            return;
        }
        $user = $this->userFactory->newFromUserIdentity($uploader);

        if (!$this->isEnabled($hookName, NS_FILE, $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $lf->getTimestamp()),
            $this->describeUser($user), $this->describePage($lf->getTitle()), [
                'filename' => $lf->getName(), 'reupload' => $lf->getHistory(1) !== [],
                'comment' => $lf->getDescription(), 'size' => $lf->getSize(),
                'width' => $lf->getWidth(), 'height' => $lf->getHeight(), 'mime_type' => $lf->getMimeType(),
            ]
        ));
    }

    /** @inheritDoc */
    public function onFileDeleteComplete($file, $oldimage, $article, $user, $reason) {
        $hookName = 'FileDeleteComplete';

        if (!$this->isEnabled($hookName, NS_FILE, $user)) {
            return;
        }

        if ($article) {
            // Entire page was deleted, onPageDeleteComplete will handle this
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), $this->describePage($file->getTitle()), [
                'filename' => $file->getName(), 'archive_name' => $oldimage, 'reason' => $reason,
            ]
        ));
    }

    /** @inheritDoc */
    public function onFileUndeleteComplete($title, $fileVersions, $user, $reason) {
        $hookName = 'FileUndeleteComplete';

        if (!$this->isEnabled($hookName, NS_FILE, $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), $this->describePage($title), [
                'versions' => $fileVersions === null ? null : array_values($fileVersions), 'reason' => $reason,
            ]
        ));
    }
}
