<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Service;

use Drupal\ticket_management\Entity\TicketInterface;
use Drupal\ticket_management\Exception\InvalidTicketTransitionException;

/**
 * Defines the ticket status state machine.
 */
interface TicketStateMachineInterface {

  /**
   * Returns whether a transition is allowed.
   */
  public function canTransition(string $from, string $to): bool;

  /**
   * Asserts a transition is allowed or throws.
   *
   * @throws \Drupal\ticket_management\Exception\InvalidTicketTransitionException
   */
  public function assertTransition(string $from, string $to): void;

  /**
   * Returns allowed target statuses from a given status.
   *
   * @return string[]
   */
  public function getAllowedTargets(string $from): array;

  /**
   * Applies a status transition on the ticket entity (does not save).
   *
   * Re-reads the current status from the entity before validating.
   *
   * @throws \Drupal\ticket_management\Exception\InvalidTicketTransitionException
   */
  public function apply(TicketInterface $ticket, string $to): TicketInterface;

}
