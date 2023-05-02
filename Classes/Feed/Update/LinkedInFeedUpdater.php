<?php
declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Feed\Update;

use GuzzleHttp\Client;
use Pixelant\PxaSocialFeed\Domain\Model\Configuration;
use Pixelant\PxaSocialFeed\Domain\Model\Feed;
use Pixelant\PxaSocialFeed\Domain\Model\Token;
use Pixelant\PxaSocialFeed\Feed\Source\FeedSourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LinkedInFeedUpdater
 * @package Pixelant\PxaSocialFeed\Feed\Update
 */
class LinkedInFeedUpdater extends BaseUpdater
{
    /**
     * Create/Update feed items
     *
     * @param FeedSourceInterface $source
     */
    public function update(FeedSourceInterface $source): void
    {
        $items = $source->load();
    }
}