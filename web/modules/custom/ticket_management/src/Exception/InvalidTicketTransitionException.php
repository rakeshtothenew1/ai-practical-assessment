<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Exception;

/**
 * Thrown when a ticket status transition is not allowed.
 */
final class InvalidTicketTransitionException extends \InvalidArgumentException {

  public function __construct(
    private readonly string $fromStatus,
    private readonly string $toStatus,
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    if ($message === '') {
      $message = sprintf('Transition from %s to %s is not allowed.', $fromStatus, $toStatus);
    }
    parent::__construct($message, $code, $previous);
  }

  public function getFromStatus(): string {
    return $this->fromStatus;
  }

  public function getToStatus(): string {
    return $this->toStatus;
  }

}
