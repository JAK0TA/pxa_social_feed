<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Controller;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Fluid\View\TemplateView;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Pixelant\PxaSocialFeed\Domain\Model\Feed;
use TYPO3\CMS\Backend\Attribute\AsController;
use Pixelant\PxaSocialFeed\Domain\Model\Token;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use Pixelant\PxaSocialFeed\Domain\Model\Configuration;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Pixelant\PxaSocialFeed\Utility\ConfigurationUtility;
use Pixelant\PxaSocialFeed\Domain\Repository\FeedRepository;
use Pixelant\PxaSocialFeed\Domain\Repository\TokenRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use Pixelant\PxaSocialFeed\Service\Task\ImportFeedsTaskService;
use Pixelant\PxaSocialFeed\Domain\Repository\ConfigurationRepository;
use Pixelant\PxaSocialFeed\Domain\Repository\AbstractBackendRepository;
use Pixelant\PxaSocialFeed\Domain\Repository\BackendUserGroupRepository;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * SocialFeedAdministrationController
 */
#[AsController]
final class AdministrationController extends ActionController 
{
    public function __construct(
      protected ModuleTemplateFactory $moduleTemplateFactory,
      protected FeedRepository $feedRepository,
      protected ConfigurationRepository $configurationRepository,
      protected BackendUserGroupRepository $backendUserGroupRepository,
      protected TokenRepository $tokenRepository,
    ) {}

    protected ModuleTemplate $moduleTemplate;

    /**
     * Set up the doc header properly here
     *
     * @param TemplateView $view
     */
    protected function initializeView(TemplateView $view): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        // create select box menu
        $this->createMenu();

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);

        $pageRenderer->addRequireJsConfiguration(
            [
                'paths' => [
                    'clipboard' => PathUtility::getAbsoluteWebPath(
                        GeneralUtility::getFileAbsFileName(
                            'EXT:pxa_social_feed/Resources/Public/JavaScript/clipboard.min'
                        )
                    ),
                ],
                'shim' => [
                    'deps' => ['jquery'],
                    'clipboard' => ['exports' => 'ClipboardJS'],
                ],
            ]
        );

        $pageRenderer->loadRequireJsModule(
            'TYPO3/CMS/PxaSocialFeed/Backend/SocialFeedModule',
            "function(socialFeedModule) { socialFeedModule.getInstance({$this->getInlineSettings()}).run() }"
        );
    }

    /**
     * Index action to show all configurations and tokens
     *
     * @param bool $activeTokenTab
     */
    public function indexAction(bool $activeTokenTab = false): ResponseInterface
    {
        $tokens = $this->findAllByRepository($this->tokenRepository);

        $this->view->assignMultiple([
            'tokens' => $tokens,
            'configurations' => $this->findAllByRepository($this->configurationRepository),
            'activeTokenTab' => $activeTokenTab,
            'isTokensValid' => $this->isTokensValid($tokens),
            'isAdmin' => $GLOBALS['BE_USER']->isAdmin(),
            'tokenTabUri' => $this->uriBuilder->reset()->setArguments(["activeTokenTab" => 1])->build(),
            'configurationTabUri' => $this->uriBuilder->reset()->setArguments(["activeTokenTab" => 0])->build(),
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());    
    }

    public function initializeEditTokenAction() {

    }
    
    /**
     * Edit token form
     *
     * @param Token $token
     * @param int $type
     */
    public function editTokenAction(Token $tokenRecord = null, int $type = Token::FACEBOOK): ResponseInterface
    {
        $isNew = $tokenRecord === null;

        if (!$isNew) {
            $type = $tokenRecord->getType();
        }
        $availableTypes = [];

        if ($isNew) {
            foreach (Token::getAvailableTokensTypes() as $availableTokensType) {
                $availableTypes[$availableTokensType] = $this->translate('type.' . $availableTokensType);
            }
        }

        $this->view->assignMultiple(["token" => $tokenRecord, "type" => $type, 'isNew' => $isNew, 'availableTypes' => $availableTypes]);
        $this->assignBEGroups();

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());    
    }

    /**
     * Save token changes
     *
     * @param Token $token
     * @TYPO3\CMS\Extbase\Annotation\Validate("\Pixelant\PxaSocialFeed\Domain\Validation\Validator\TokenValidator", param="tokenRecord")
     */
    public function updateTokenAction(Token $tokenRecord): ResponseInterface
    {
        $isNew = $tokenRecord->getUid() === null;

        $this->tokenRepository->{$isNew ? 'add' : 'update'}($tokenRecord);

        return $this->redirectToIndexTokenTab($this->translate('action_changes_saved'));
    }

    /**
     * Reset access token
     *
     * @param Token $token
     */
    public function resetAccessTokenAction(Token $tokenRecord): ResponseInterface
    {
        $tokenRecord->setAccessToken('');
        $this->tokenRepository->update($tokenRecord);

        return $this->redirectToIndexTokenTab();
    }

    /**
     * Delete token
     *
     * @param Token $token
     */
    public function deleteTokenAction(Token $tokenRecord): ResponseInterface
    {
        $tokenConfigurations = $this->configurationRepository->findConfigurationByToken($tokenRecord);

        if ($tokenConfigurations->count() === 0) {
            $this->tokenRepository->remove($tokenRecord);

            if ($tokenRecord->getType() === Token::FACEBOOK) {
                // Remove all page access tokens created by this token
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable('tx_pxasocialfeed_domain_model_token');
                $queryBuilder->delete('tx_pxasocialfeed_domain_model_token', ['parent_token' => $tokenRecord->getUid()]);
            }

            return $this->redirectToIndexTokenTab($this->translate('action_delete'));
        }

        return $this->redirectToIndexTokenTab(
            $this->translate('error_token_configuration_exist', [$tokenConfigurations->getFirst()->getName()]),
            ContextualFeedbackSeverity::ERROR
        );
    }

    /**
     * Edit configuration
     *
     * @param Configuration $configuration
     */
    public function editConfigurationAction(Configuration $configuration = null): ResponseInterface
    {
        $tokens = $this->findAllByRepository($this->tokenRepository);

        $this->view->assignMultiple(compact('configuration', 'tokens'));
        $this->assignBEGroups();

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());    
    }

    /**
     * Update configuration
     *
     * @param Configuration $configuration
     * @TYPO3\CMS\Extbase\Annotation\Validate("\Pixelant\PxaSocialFeed\Domain\Validation\Validator\ConfigurationValidator", param="configuration")
     */
    public function updateConfigurationAction(Configuration $configuration): ResponseInterface
    {
        $isNew = $configuration->getUid() === null;

        // If storage was updated and it's not new configuration, need to migrate existing feed records
        if ($isNew == false && $configuration->_isDirty('storage')) {
            $this->migrateFeedsToNewStorage($configuration, $configuration->getStorage());
        }

        $this->configurationRepository->{$isNew ? 'add' : 'update'}($configuration);

        if ($isNew) {
            // Save first, so we can pass it as argument
            GeneralUtility::makeInstance(PersistenceManagerInterface::class)->persistAll();

            // Redirect back to edit view, so user can now provide social ID according to selected token
            return $this->redirect('editConfiguration', null, null, ['configuration' => $configuration]);
        }

        return $this->redirectToIndex($this->translate('action_changes_saved'));
    }

    /**
     * Delete configuration and feed items
     *
     * @param Configuration $configuration
     */
    public function deleteConfigurationAction(Configuration $configuration): ResponseInterface
    {
        // Remove all feeds
        $feeds = $this->feedRepository->findByConfiguration($configuration);

        foreach ($feeds as $feed) {
            $this->feedRepository->remove($feed);
        }

        $this->configurationRepository->remove($configuration);

        return $this->redirectToIndex($this->translate('action_delete'));
    }

    /**
     * Test run of import configuration
     *
     * @param Configuration $configuration
     */
    public function runConfigurationAction(Configuration $configuration): ResponseInterface
    {
        $importService = GeneralUtility::makeInstance(ImportFeedsTaskService::class);
        try {
            $importService->import([ $configuration->getUid() ]);
        } catch (\Exception $e) {
            $this->redirectToIndex($e->getMessage(), ContextualFeedbackSeverity::ERROR);
        }

        return $this->redirectToIndex($this->translate('single_import_end'));
    }

    /**
     * Check if editor restriction feature is enabled
     * If so find all with backend group access restriction
     *
     * @param AbstractBackendRepository $repository
     * @return QueryResultInterface
     */
    protected function findAllByRepository(AbstractBackendRepository $repository): QueryResultInterface
    {
        return ConfigurationUtility::isFeatureEnabled('editorRestriction')
            ? $repository->findAllBackendGroupRestriction()
            : $repository->findAll();
    }

    /**
     * Assign BE groups to template
     * If admin all are available
     */
    protected function assignBEGroups()
    {
        if (!ConfigurationUtility::isFeatureEnabled('editorRestriction')) {
            return;
        }

        $excludeGroups = $this->getExcludeGroups();

        if ($GLOBALS['BE_USER']->isAdmin()) {
            $groups = $this->backendUserGroupRepository->findAll($excludeGroups);
        } else {
            $groups = array_filter($GLOBALS['BE_USER']->userGroups, function ($group) use ($excludeGroups) {
                return !in_array($group['uid'], $excludeGroups);
            });
        }

        $this->view->assign('beGroups', $groups);
    }

    /**
     * Shortcut for translate
     *
     * @param string $key
     * @param array|null $arguments
     * @return string
     */
    protected function translate(string $key, array $arguments = null): ?string
    {
        $key = 'module.' . $key;

        return LocalizationUtility::translate($key, 'PxaSocialFeed', $arguments);
    }

    /**
     * create BE menu
     */
    protected function createMenu(): void
    {
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uriBuilder->setRequest($this->request);


        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('pxa_social_feed');

        $actions = [
            'index',
            'editConfiguration',
            'editToken',
        ];

        foreach ($actions as $action) {
            $item = $menu->makeMenuItem()
                ->setTitle($this->translate($action . 'Action'))
                ->setHref($uriBuilder->reset()->uriFor($action, ["token" => null], 'Administration'))
                ->setActive($this->request->getControllerActionName() === $action);

            $menu->addMenuItem($item);
        }

        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Migrate feed items of configuration if storoge was changed
     *
     * @param Configuration $configuration
     * @param int $newStorage
     */
    protected function migrateFeedsToNewStorage(Configuration $configuration, int $newStorage): void
    {
        $feedItems = $this->feedRepository->findByConfiguration($configuration);

        /** @var Feed $feedItem */
        foreach ($feedItems as $feedItem) {
            $feedItem->setPid($newStorage);
            $this->feedRepository->update($feedItem);
        }
    }

    /**
     * Check if instagram and facebook tokens has access token
     *
     * @param $tokens
     * @return bool
     */
    protected function isTokensValid($tokens): bool
    {
        /** @var Token $token */
        foreach ($tokens as $token) {
            if ($token->getType() === Token::INSTAGRAM || $token->getType() === Token::FACEBOOK) {
                if (!$token->isValidFacebookAccessToken()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Generate settings for JS
     */
    protected function getInlineSettings(): string
    {
        $uriBuilder = GeneralUtility::makeInstance(BackendUriBuilder::class);

        return json_encode([
            'browserUrl' => (string)$uriBuilder->buildUriFromRoute('wizard_element_browser'),
        ]);
    }

    /**
     * Shortcut to redirect to index on tokens tab with flash message
     *
     * @param string|null $message
     * @param int $severity
     */
    protected function redirectToIndexTokenTab(string $message = null, ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::OK): ResponseInterface
    {
        if (!empty($message)) {
            $this->addFlashMessage(
                $message,
                '',
                $severity
            );
        }

        return $this->redirect('index', null, null, ['activeTokenTab' => true]);
    }

    /**
     * Shortcut to redirect to index with flash message
     *
     * @param string|null $message
     * @param int $severity
     */
    protected function redirectToIndex(string $message = null, ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::OK): ResponseInterface
    {
        if (!empty($message)) {
            $this->addFlashMessage(
                $message,
                '',
                $severity
            );
        }

        return $this->redirect('index');
    }

    /**
     * Return exclude user group uids from ext configuration
     *
     * @return array
     */
    protected function getExcludeGroups()
    {
        $configuration = ConfigurationUtility::getExtensionConfiguration();
        if (isset($configuration['excludeBackendUserGroups'])) {
            return GeneralUtility::intExplode(',', $configuration['excludeBackendUserGroups'], true);
        }

        return [];
    }
}
