<?php

use TYPO3\CMS\Core\Http\ApplicationType;
use Psr\Http\Message\ServerRequestInterface;

defined('TYPO3') or die();

(function () {
    if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()) {
        /**
         * Registers a Backend Module
         */
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Pixelant.pxa_social_feed',
            'tools',     // Make module a submodule of 'tools'
            'pxasocialfeed',    // Submodule key
            '',                        // Position
            [
                \Pixelant\PxaSocialFeed\Controller\AdministrationController::class =>
                    'index, editToken, updateToken, deleteToken, editConfiguration, ' .
                    'updateConfiguration, deleteConfiguration, resetAccessToken, runConfiguration',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:pxa_social_feed/Resources/Public/Icons/BE/feed.svg',
                'labels' => 'LLL:EXT:pxa_social_feed/Resources/Private/Language/locallang_be.xlf',
            ]
        );
    }

    foreach (['feed', 'token', 'configuration'] as $table) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages(
            'tx_pxasocialfeed_domain_model_' . $table
        );
    }
})();
