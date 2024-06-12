<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Event;

use Pixelant\PxaSocialFeed\Domain\Model\Configuration;
use Pixelant\PxaSocialFeed\Domain\Model\Feed;

final class BeforeUpdateTwitterFeedEvent {
  public function __construct(
    private Feed $feedItem,
    private array $rawData,
    private Configuration $configuration,
  ) {}

  public function getConfiguration(): Configuration {
    return $this->configuration;
  }

  public function getFeedItem(): Feed {
    return $this->feedItem;
  }

  public function getRawData(): array {
    return $this->rawData;
  }

  public function setConfiguration(Configuration $configuration): void {
    $this->configuration = $configuration;
  }

  public function setFeedItem(Feed $feedItem): void {
    $this->feedItem = $feedItem;
  }

  public function setRawData(array $rawData): void {
    $this->rawData = $rawData;
  }
}
