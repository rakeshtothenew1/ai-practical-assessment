<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Service;

use Drupal\Core\Database\Connection;

/**
 * Database API queries for ticket list search and status filter.
 */
final class TicketQueryService {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Allowed ticket status values.
   */
  public const STATUSES = [
    'open',
    'in_progress',
    'resolved',
    'closed',
    'cancelled',
  ];

  /**
   * Searches tickets by optional keyword and status.
   *
   * @return array{ids: int[], total: int}
   */
  public function search(?string $search, ?string $status, int $page = 1, int $limit = 25): array {
    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;

    $search = $search !== NULL ? trim($search) : '';
    $status = $status !== NULL && $status !== '' ? strtolower(trim($status)) : NULL;

    if ($status !== NULL && !in_array($status, self::STATUSES, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Invalid status filter: %s', $status));
    }

    $count_query = $this->database->select('ticket', 't');
    $count_query->addExpression('COUNT(t.id)', 'total');
    $this->applyFilters($count_query, $search, $status);
    $total = (int) $count_query->execute()->fetchField();

    $query = $this->database->select('ticket', 't')
      ->fields('t', ['id'])
      ->orderBy('t.created', 'DESC')
      ->range($offset, $limit);
    $this->applyFilters($query, $search, $status);

    $ids = array_map('intval', $query->execute()->fetchCol());

    return [
      'ids' => $ids,
      'total' => $total,
      'page' => $page,
      'limit' => $limit,
    ];
  }

  /**
   * Applies search and status conditions to a select query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query.
   */
  private function applyFilters($query, string $search, ?string $status): void {
    if ($search !== '') {
      $like = '%' . $this->database->escapeLike($search) . '%';
      $or = $query->orConditionGroup()
        ->condition('t.title', $like, 'LIKE')
        ->condition('t.description', $like, 'LIKE');
      $query->condition($or);
    }
    if ($status !== NULL) {
      $query->condition('t.status', $status);
    }
  }

}
