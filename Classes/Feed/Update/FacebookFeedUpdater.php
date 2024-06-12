<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Feed\Update;

use Pixelant\PxaSocialFeed\Domain\Model\Configuration;
use Pixelant\PxaSocialFeed\Domain\Model\Feed;
use Pixelant\PxaSocialFeed\Domain\Model\Token;
use Pixelant\PxaSocialFeed\Event\BeforeUpdateFacebookFeedEvent;
use Pixelant\PxaSocialFeed\Feed\Source\FeedSourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FacebookFeedUpdater
 */
class FacebookFeedUpdater extends BaseUpdater
{
    /**
     * Create/Update feed items
     *
     * @param FeedSourceInterface $source
     */
    public function update(FeedSourceInterface $source): void
    {
        $items = $source->load();

        if (count($items) > 0) {
            foreach ($items as $rawItem) {
                $feedItem = $this->feedRepository->findOneByExternalIdentifier(
                    $rawItem['id'],
                    $source->getConfiguration()->getStorage()
                );
                if ($feedItem === null) {
                    $feedItem = $this->createFeedItem($rawItem, $source->getConfiguration());
                }

                $this->updateFeedItem($feedItem, $rawItem);
            }
        }
    }

    /**
     * Update single facebook item
     *
     * @param Feed $feedItem
     * @param array $rawData
     * @param Configuration $configuration
     */
    protected function updateFeedItem(Feed $feedItem, array $rawData): void
    {
        $updated = strtotime($rawData['updated_time']);
        $feedUpdated = $feedItem->getUpdateDate() ? $feedItem->getUpdateDate()->getTimestamp() : 0;

        if ($feedUpdated < $updated) {
            $this->setFacebookData($feedItem, $rawData);
            $feedItem->setUpdateDate((new \DateTime())->setTimestamp($updated));
        }

        $feedItem->setLikes((int)($rawData['reactions']['summary']['total_count']));

        /** @var BeforeUpdateFacebookFeedEvent $event */
        $event = $this->eventDispatcher->dispatch(new BeforeUpdateFacebookFeedEvent($feedItem, $rawData, $feedItem->getConfiguration()));

        $this->addOrUpdateFeedItem($event->getFeedItem());
    }

    /**
     * Update facebook data
     *
     * @param Feed $feed
     * @param array $rawData
     */
    protected function setFacebookData(Feed $feed, array $rawData): void
    {
        if (isset($rawData['message'])) {
            $feed->setMessage($this->encodeMessage($rawData['message']));
        }

        $imageRef = null;
        if (isset($rawData['attachments']['data'][0]['media']['image']['src'])) {
            $imageRef = $this->storeImg($rawData['attachments']['data'][0]['media']['image']['src'], $feed);
        } elseif (isset($rawData['attachments']['data'][0]['subattachments']['data'][0]['media']['image']['src'])) {
            $imageRef = $this->storeImg($rawData['attachments']['data'][0]['subattachments']['data'][0]['media']['image']['src'], $feed);
        }

        if ($imageRef != null && !$this->checkIfFalRelationIfAlreadyExists($feed->getFalMedia(), $imageRef)) {
            $feed->addFalMedia($imageRef);
        }

        if (isset($rawData['attachments']['data'][0]['title'])) {
            $feed->setTitle($rawData['attachments']['data'][0]['title']);
        }
    }

    /**
     * Create new feed item
     *
     * @param array $rawData
     * @param Configuration $configuration
     * @return object|Feed
     */
    protected function createFeedItem(array $rawData, Configuration $configuration): Feed
    {
        $feedItem = GeneralUtility::makeInstance(Feed::class);

        $feedItem->setPostUrl($rawData['permalink_url']);
        $feedItem->setPostDate(\DateTime::createFromFormat(\DateTime::ISO8601, $rawData['created_time']));
        $feedItem->setConfiguration($configuration);
        $feedItem->setExternalIdentifier($rawData['id']);
        $feedItem->setPid($configuration->getStorage());
        $feedItem->setType(Token::FACEBOOK);

        return $feedItem;
    }
}
