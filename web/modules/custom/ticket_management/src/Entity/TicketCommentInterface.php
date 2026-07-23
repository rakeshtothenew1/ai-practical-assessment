<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a Ticket Comment entity.
 */
interface TicketCommentInterface extends ContentEntityInterface {

  /**
   * Gets the parent ticket ID.
   */
  public function getTicketId(): ?int;

  /**
   * Gets the comment message.
   */
  public function getMessage(): string;

  /**
   * Sets the comment message.
   */
  public function setMessage(string $message): self;

  /**
   * Gets the author user.
   */
  public function getCreatedBy(): ?UserInterface;

}
