<?php

declare(strict_types=1);

namespace Drupal\ticket_management;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Ticket entities.
 */
final class TicketListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['title'] = $this->t('Title');
    $header['status'] = $this->t('Status');
    $header['priority'] = $this->t('Priority');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ticket_management\Entity\TicketInterface $entity */
    $row['id'] = $entity->id();
    $row['title'] = $entity->label();
    $row['status'] = $entity->getStatus();
    $row['priority'] = $entity->getPriority();
    return $row + parent::buildRow($entity);
  }

}
