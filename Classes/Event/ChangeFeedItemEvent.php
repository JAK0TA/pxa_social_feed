<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Event;

use Pixelant\PxaSocialFeed\Domain\Model\Feed;

final class ChangeFeedItemEvent {
  public function __construct(
    private Feed $feedItem,
  ) {}

  public function getFeedItem(): Feed {
    return $this->feedItem;
  }

  public function setFeedItem(Feed $feedItem): void {
    $this->feedItem = $feedItem;
  }
}
