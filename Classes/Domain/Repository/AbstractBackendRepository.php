<?php

namespace Pixelant\PxaSocialFeed\Domain\Repository;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/**
 * Class AbstractRepository
 */
abstract class AbstractBackendRepository extends Repository
{
    /**
     * Initialize default settings
     */
    public function initializeObject()
    {
        /** @var Typo3QuerySettings $defaultQuerySettings */
        $defaultQuerySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);

        // don't add the pid constraint
        $defaultQuerySettings->setRespectStoragePage(false);
        // don't add sys_language_uid constraint
        $defaultQuerySettings->setRespectSysLanguage(false);

        $this->setDefaultQuerySettings($defaultQuerySettings);
    }

    /**
     * Find all records with backend user group restriction
     */
    public function findAllBackendGroupRestriction(): QueryResultInterface
    {
      /** @var BackendUserAuthentication|null $backendUser */
      $backendUser = $GLOBALS['BE_USER'];
      $query = $this->createQuery();
      
      if($backendUser && !$backendUser->isAdmin()) {
        $query->matching(
          $query->logicalOr(
            $query->equals("be_group", NULL),
            $query->equals("be_group", ""),
            $query->equals("be_group", "0"),
            $query->in("be_group", $backendUser->userGroupsUID)
          ),
        );
      }

      return $query->execute();
    }
}
