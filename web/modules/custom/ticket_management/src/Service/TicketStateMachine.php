<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Service;

use Drupal\ticket_management\Entity\TicketInterface;
use Drupal\ticket_management\Exception\InvalidTicketTransitionException;

/**
 * Standalone service enforcing ticket status transitions.
 *
 * Transition map:
 * - open => [in_progress, cancelled]
 * - in_progress => [resolved, cancelled]
 * - resolved => [closed]
 * - closed => []
 * - cancelled => []
 *
 * Also enforced from Ticket::preSave() on every entity save path.
 */
final class TicketStateMachine implements TicketStateMachineInterface {

  /**
   * Allowed transitions: source status => list of targets.
   */
  private const TRANSITIONS = [
    'open' => ['in_progress', 'cancelled'],
    'in_progress' => ['resolved', 'cancelled'],
    'resolved' => ['closed'],
    'closed' => [],
    'cancelled' => [],
  ];

  /**
   * {@inheritdoc}
   */
  public function canTransition(string $from, string $to): bool {
    $from = $this->normalize($from);
    $to = $this->normalize($to);
    if (!isset(self::TRANSITIONS[$from])) {
      return FALSE;
    }
    return in_array($to, self::TRANSITIONS[$from], TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ticket_management\Exception\InvalidTicketTransitionException
   */
  public function assertTransition(string $from, string $to): void {
    $from = $this->normalize($from);
    $to = $this->normalize($to);
    if (!$this->canTransition($from, $to)) {
      throw new InvalidTicketTransitionException($from, $to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedTargets(string $from): array {
    $from = $this->normalize($from);
    return self::TRANSITIONS[$from] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionMap(): array {
    return self::TRANSITIONS;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\ticket_management\Exception\InvalidTicketTransitionException
   */
  public function apply(TicketInterface $ticket, string $to): TicketInterface {
    $current = $ticket->getStatus();
    $this->assertTransition($current, $to);
    $ticket->setStatus($this->normalize($to));
    return $ticket;
  }

  /**
   * Normalizes a status machine name.
   */
  private function normalize(string $status): string {
    return strtolower(trim($status));
  }

}
