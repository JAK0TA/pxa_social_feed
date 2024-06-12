<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Event;

final class FacebookEndPointEvent {
  public function __construct(
    private string $endPoint,
  ) {}

  public function getEndPoint(): string {
    return $this->endPoint;
  }

  public function setEndPoint(string $endPoint): void {
    $this->endPoint = $endPoint;
  }
}
