<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Placeholder controller for ticket HTML routes.
 */
final class TicketController extends ControllerBase {

  /**
   * Temporary overview page until the list UI is built.
   */
  public function overview(): array {
    return [
      '#markup' => $this->t('Ticket Management module is enabled. Ticket list UI coming soon.'),
    ];
  }

}
