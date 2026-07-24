<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ticket_management\Entity\TicketInterface;
use Drupal\ticket_management\Service\TicketStateMachineInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Twig UI controllers for ticket list, detail, and create screens.
 */
final class TicketController extends ControllerBase {

  public function __construct(
    private readonly TicketStateMachineInterface $stateMachine,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ticket_management.state_machine'),
    );
  }

  /**
   * Ticket list page (search + status filter via JS fetch).
   */
  public function overview(): array {
    return $this->buildPage('list', [
      '#theme' => 'ticket_management_list',
      '#can_create' => $this->currentUser()->hasPermission('create ticket'),
      '#create_url' => Url::fromRoute('ticket_management.ticket_add')->toString(),
    ]);
  }

  /**
   * Create ticket page.
   */
  public function add(): array {
    return $this->buildPage('create', [
      '#theme' => 'ticket_management_create',
      '#assignees' => $this->loadAssigneeOptions(),
      '#list_url' => Url::fromRoute('ticket_management.overview')->toString(),
    ]);
  }

  /**
   * Ticket detail page (fields, transitions, comments via JS fetch).
   */
  public function view(int $ticket): array {
    $entity = $this->entityTypeManager()->getStorage('ticket')->load($ticket);
    if (!$entity instanceof TicketInterface) {
      throw new NotFoundHttpException();
    }

    return $this->buildPage('detail', [
      '#theme' => 'ticket_management_detail',
      '#ticket_id' => (int) $entity->id(),
      '#list_url' => Url::fromRoute('ticket_management.overview')->toString(),
      '#can_transition' => $this->currentUser()->hasPermission('transition ticket status'),
      '#can_comment' => $this->currentUser()->hasPermission('create ticket comment'),
    ], (int) $entity->id());
  }

  /**
   * Wraps a theme render array with the shared library and drupalSettings.
   *
   * @param array<string, mixed> $build
   *   Theme render array.
   *
   * @return array<string, mixed>
   *   Page render array.
   */
  private function buildPage(string $page, array $build, ?int $ticket_id = NULL): array {
    $build['#attached']['library'][] = 'ticket_management/ticket_app';
    $build['#attached']['drupalSettings']['ticketManagement'] = [
      'page' => $page,
      'apiBase' => '/api/tickets',
      'ticketId' => $ticket_id,
      'csrfUrl' => Url::fromRoute('system.csrftoken')->toString(),
      'transitions' => $this->stateMachine->getTransitionMap(),
      'statusLabels' => [
        'open' => (string) $this->t('Open'),
        'in_progress' => (string) $this->t('In Progress'),
        'resolved' => (string) $this->t('Resolved'),
        'closed' => (string) $this->t('Closed'),
        'cancelled' => (string) $this->t('Cancelled'),
      ],
      'priorityLabels' => [
        'low' => (string) $this->t('Low'),
        'medium' => (string) $this->t('Medium'),
        'high' => (string) $this->t('High'),
      ],
      'permissions' => [
        'create' => $this->currentUser()->hasPermission('create ticket'),
        'transition' => $this->currentUser()->hasPermission('transition ticket status'),
        'comment' => $this->currentUser()->hasPermission('create ticket comment'),
      ],
    ];
    return $build;
  }

  /**
   * Loads active users for the assignee select (excludes anonymous).
   *
   * @return array<int, string>
   *   uid => display name.
   */
  private function loadAssigneeOptions(): array {
    $storage = $this->entityTypeManager()->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', 0, '>')
      ->condition('status', 1)
      ->sort('name')
      ->range(0, 100)
      ->execute();
    if (!$ids) {
      return [];
    }
    $options = [];
    foreach ($storage->loadMultiple($ids) as $user) {
      if ($user instanceof UserInterface) {
        $options[(int) $user->id()] = $user->getDisplayName();
      }
    }
    return $options;
  }

}
