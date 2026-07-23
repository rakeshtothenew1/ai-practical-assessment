<?php

declare(strict_types=1);

namespace Drupal\ticket_management;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Ticket Comment entities.
 */
final class TicketCommentListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['ticket_id'] = $this->t('Ticket');
    $header['message'] = $this->t('Message');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ticket_management\Entity\TicketCommentInterface $entity */
    $row['id'] = $entity->id();
    $row['ticket_id'] = $entity->getTicketId();
    $row['message'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

}
