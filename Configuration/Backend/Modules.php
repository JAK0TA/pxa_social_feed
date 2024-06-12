<?php

declare(strict_types=1);

use Pixelant\PxaSocialFeed\Controller\AdministrationController;

// Definitions for modules provided by EXT:examples
return [
  // Example for a module registration with Extbase controller
  'web_examples' => [
    'parent' => 'web',
    'position' => "bottom",
    'access' => 'user,group',
    'workspaces' => 'live',
    'path' => '/socialFeed',
    'labels' => 'LLL:EXT:pxa_social_feed/Resources/Private/Language/locallang_be.xlf',
    'icon' => 'EXT:pxa_social_feed/Resources/Public/Icons/BE/feed.svg',
    // Extbase-specific configuration telling the TYPO3 Core to bootstrap Extbase
    'extensionName' => 'PxaSocialFeed',
    'controllerActions' => [
      AdministrationController::class => [
        'index', 'editToken', 'updateToken', 'resetAccessToken', 'deleteToken', 'editConfiguration', 'updateConfiguration', 'deleteConfiguration', 'runConfiguration',
      ],
    ],
  ],
];
