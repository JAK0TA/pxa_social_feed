<?php

defined('TYPO3') or die();

(function () {
    foreach (['feed', 'token', 'configuration'] as $table) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages(
            'tx_pxasocialfeed_domain_model_' . $table
        );
    }
})();
