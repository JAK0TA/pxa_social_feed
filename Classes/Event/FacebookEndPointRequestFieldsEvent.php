<?php

declare(strict_types=1);

namespace Pixelant\PxaSocialFeed\Event;

final class FacebookEndPointRequestFieldsEvent {
  public function __construct(
    private array $fields,
  ) {}

  public function getFields(): array {
    return $this->fields;
  }

  public function setFields(array $fields): void {
    $this->fields = $fields;
  }
}
