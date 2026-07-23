<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a Ticket entity.
 */
interface TicketInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the ticket title.
   */
  public function getTitle(): string;

  /**
   * Sets the ticket title.
   */
  public function setTitle(string $title): self;

  /**
   * Gets the ticket status machine name.
   */
  public function getStatus(): string;

  /**
   * Sets the ticket status machine name.
   */
  public function setStatus(string $status): self;

  /**
   * Gets the priority machine name.
   */
  public function getPriority(): string;

  /**
   * Sets the priority machine name.
   */
  public function setPriority(string $priority): self;

  /**
   * Gets the author user.
   */
  public function getCreatedBy(): ?UserInterface;

  /**
   * Gets the assignee user.
   */
  public function getAssignedTo(): ?UserInterface;

}
