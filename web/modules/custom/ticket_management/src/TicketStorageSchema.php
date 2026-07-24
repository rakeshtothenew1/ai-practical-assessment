<?php

declare(strict_types=1);

namespace Drupal\ticket_management;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Adds indexes for the ticket list search and status filter.
 *
 * The list query (see TicketQueryService) filters on `status` and sorts on
 * `created`; these indexes keep it off full table scans as ticket volume grows.
 */
final class TicketStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $base_table = $this->storage->getBaseTable();
    if ($base_table !== NULL && isset($schema[$base_table])) {
      $schema[$base_table]['indexes'] += [
        'ticket__status' => ['status'],
        'ticket__created' => ['created'],
      ];
    }

    return $schema;
  }

}
