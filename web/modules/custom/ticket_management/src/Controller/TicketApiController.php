<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ticket_management\Entity\TicketCommentInterface;
use Drupal\ticket_management\Entity\TicketInterface;
use Drupal\ticket_management\Exception\InvalidTicketTransitionException;
use Drupal\ticket_management\Service\TicketQueryService;
use Drupal\ticket_management\Service\TicketStateMachineInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom JSON REST API for tickets and comments.
 */
final class TicketApiController extends ControllerBase {

  private const PRIORITIES = ['low', 'medium', 'high'];

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    private readonly TicketStateMachineInterface $stateMachine,
    private readonly TicketQueryService $ticketQuery,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ticket_management.state_machine'),
      $container->get('ticket_management.ticket_query'),
    );
  }

  /**
   * GET /api/tickets?search=&status=
   */
  public function collection(Request $request): JsonResponse {
    $search = $request->query->get('search');
    $status = $request->query->get('status');
    $page = (int) $request->query->get('page', 1);
    $limit = (int) $request->query->get('limit', 25);

    try {
      $result = $this->ticketQuery->search(
        is_string($search) ? $search : NULL,
        is_string($status) ? $status : NULL,
        $page,
        $limit,
      );
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e->getMessage(), [
        ['field' => 'status', 'code' => 'invalid', 'message' => $e->getMessage()],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $storage = $this->entityTypeManager()->getStorage('ticket');
    $entities = $result['ids'] ? $storage->loadMultiple($result['ids']) : [];
    // Preserve query order.
    $data = [];
    foreach ($result['ids'] as $id) {
      if (isset($entities[$id]) && $entities[$id] instanceof TicketInterface) {
        $data[] = $this->normalizeTicket($entities[$id]);
      }
    }

    return new JsonResponse([
      'data' => $data,
      'meta' => [
        'page' => $result['page'],
        'limit' => $result['limit'],
        'total' => $result['total'],
      ],
    ]);
  }

  /**
   * GET /api/tickets/{id}
   */
  public function get(int $id): JsonResponse {
    $ticket = $this->loadTicket($id);
    if (!$ticket) {
      return $this->errorResponse('Ticket not found.', [], Response::HTTP_NOT_FOUND);
    }

    $comments = $this->loadCommentsForTicket((int) $ticket->id());

    return new JsonResponse([
      'data' => $this->normalizeTicket($ticket),
      'comments' => array_map([$this, 'normalizeComment'], $comments),
    ]);
  }

  /**
   * POST /api/tickets
   */
  public function post(Request $request): JsonResponse {
    $payload = $this->decodeJson($request);
    if ($payload instanceof JsonResponse) {
      return $payload;
    }

    $errors = [];
    $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
    $description = isset($payload['description']) ? trim((string) $payload['description']) : '';
    $priority = isset($payload['priority']) ? strtolower(trim((string) $payload['priority'])) : '';
    $assigned_to = array_key_exists('assignedTo', $payload) ? $payload['assignedTo'] : NULL;

    if ($title === '') {
      $errors[] = ['field' => 'title', 'code' => 'required', 'message' => 'Title is required.'];
    }
    elseif (mb_strlen($title) > 255) {
      $errors[] = ['field' => 'title', 'code' => 'max_length', 'message' => 'Title must be at most 255 characters.'];
    }
    if ($description === '') {
      $errors[] = ['field' => 'description', 'code' => 'required', 'message' => 'Description is required.'];
    }
    if ($priority === '' || !in_array($priority, self::PRIORITIES, TRUE)) {
      $errors[] = ['field' => 'priority', 'code' => 'invalid', 'message' => 'Priority must be one of: low, medium, high.'];
    }

    $assignee_id = NULL;
    if ($assigned_to !== NULL && $assigned_to !== '') {
      $assignee_id = (int) $assigned_to;
      if (!$this->loadUser($assignee_id)) {
        $errors[] = ['field' => 'assignedTo', 'code' => 'invalid', 'message' => 'Assigned user does not exist.'];
      }
    }

    $account = $this->currentUser();
    if (!$account->isAuthenticated()) {
      return $this->errorResponse('Authentication required.', [], Response::HTTP_UNAUTHORIZED);
    }

    if ($errors) {
      return $this->errorResponse('Validation failed.', $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @var \Drupal\ticket_management\Entity\TicketInterface $ticket */
    $ticket = $this->entityTypeManager()->getStorage('ticket')->create([
      'title' => $title,
      'description' => $description,
      'priority' => $priority,
      'status' => 'open',
      'created_by' => (int) $account->id(),
      'assigned_to' => $assignee_id,
    ]);
    $ticket->save();

    return new JsonResponse($this->normalizeTicket($ticket), Response::HTTP_CREATED, [
      'Location' => '/api/tickets/' . $ticket->id(),
    ]);
  }

  /**
   * PATCH /api/tickets/{id}
   */
  public function patch(int $id, Request $request): JsonResponse {
    $ticket = $this->loadTicket($id);
    if (!$ticket) {
      return $this->errorResponse('Ticket not found.', [], Response::HTTP_NOT_FOUND);
    }

    $payload = $this->decodeJson($request);
    if ($payload instanceof JsonResponse) {
      return $payload;
    }

    if (array_key_exists('status', $payload)) {
      return $this->errorResponse('Status cannot be changed via PATCH /api/tickets/{id}. Use PATCH /api/tickets/{id}/status.', [
        ['field' => 'status', 'code' => 'not_allowed', 'message' => 'Use PATCH /api/tickets/{id}/status for status transitions.'],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $errors = [];
    $touched = FALSE;

    if (array_key_exists('title', $payload)) {
      $title = trim((string) $payload['title']);
      if ($title === '') {
        $errors[] = ['field' => 'title', 'code' => 'required', 'message' => 'Title is required.'];
      }
      elseif (mb_strlen($title) > 255) {
        $errors[] = ['field' => 'title', 'code' => 'max_length', 'message' => 'Title must be at most 255 characters.'];
      }
      else {
        $ticket->setTitle($title);
        $touched = TRUE;
      }
    }

    if (array_key_exists('description', $payload)) {
      $description = trim((string) $payload['description']);
      if ($description === '') {
        $errors[] = ['field' => 'description', 'code' => 'required', 'message' => 'Description is required.'];
      }
      else {
        $ticket->set('description', $description);
        $touched = TRUE;
      }
    }

    if (array_key_exists('priority', $payload)) {
      $priority = strtolower(trim((string) $payload['priority']));
      if (!in_array($priority, self::PRIORITIES, TRUE)) {
        $errors[] = ['field' => 'priority', 'code' => 'invalid', 'message' => 'Priority must be one of: low, medium, high.'];
      }
      else {
        $ticket->setPriority($priority);
        $touched = TRUE;
      }
    }

    if (array_key_exists('assignedTo', $payload)) {
      $assigned_to = $payload['assignedTo'];
      if ($assigned_to === NULL || $assigned_to === '') {
        $ticket->set('assigned_to', NULL);
        $touched = TRUE;
      }
      else {
        $assignee_id = (int) $assigned_to;
        if (!$this->loadUser($assignee_id)) {
          $errors[] = ['field' => 'assignedTo', 'code' => 'invalid', 'message' => 'Assigned user does not exist.'];
        }
        else {
          $ticket->set('assigned_to', $assignee_id);
          $touched = TRUE;
        }
      }
    }

    if (!$touched && !$errors) {
      $errors[] = ['field' => NULL, 'code' => 'empty', 'message' => 'No updatable fields provided.'];
    }

    if ($errors) {
      return $this->errorResponse('Validation failed.', $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $ticket->save();
    return new JsonResponse($this->normalizeTicket($ticket));
  }

  /**
   * PATCH /api/tickets/{id}/status
   */
  public function patchStatus(int $id, Request $request): JsonResponse {
    $ticket = $this->loadTicket($id);
    if (!$ticket) {
      return $this->errorResponse('Ticket not found.', [], Response::HTTP_NOT_FOUND);
    }

    $payload = $this->decodeJson($request);
    if ($payload instanceof JsonResponse) {
      return $payload;
    }

    if (!isset($payload['status']) || trim((string) $payload['status']) === '') {
      return $this->errorResponse('Validation failed.', [
        ['field' => 'status', 'code' => 'required', 'message' => 'Status is required.'],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $target = strtolower(trim((string) $payload['status']));
    if (!in_array($target, TicketQueryService::STATUSES, TRUE)) {
      return $this->errorResponse('Validation failed.', [
        ['field' => 'status', 'code' => 'invalid', 'message' => 'Invalid status value.'],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // Re-load to ensure we validate against the current persisted status.
    $ticket = $this->loadTicket($id);
    assert($ticket instanceof TicketInterface);

    try {
      $this->stateMachine->apply($ticket, $target);
      $ticket->save();
    }
    catch (InvalidTicketTransitionException $e) {
      return $this->errorResponse($e->getMessage(), [
        [
          'field' => 'status',
          'code' => 'invalid_transition',
          'message' => $e->getMessage(),
        ],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    return new JsonResponse($this->normalizeTicket($ticket));
  }

  /**
   * POST /api/tickets/{id}/comments
   */
  public function postComment(int $id, Request $request): JsonResponse {
    $ticket = $this->loadTicket($id);
    if (!$ticket) {
      return $this->errorResponse('Ticket not found.', [], Response::HTTP_NOT_FOUND);
    }

    $payload = $this->decodeJson($request);
    if ($payload instanceof JsonResponse) {
      return $payload;
    }

    $message = isset($payload['message']) ? trim((string) $payload['message']) : '';
    if ($message === '') {
      return $this->errorResponse('Validation failed.', [
        ['field' => 'message', 'code' => 'required', 'message' => 'Message is required.'],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    if (mb_strlen($message) > 5000) {
      return $this->errorResponse('Validation failed.', [
        ['field' => 'message', 'code' => 'max_length', 'message' => 'Message must be at most 5000 characters.'],
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $account = $this->currentUser();
    if (!$account->isAuthenticated()) {
      return $this->errorResponse('Authentication required.', [], Response::HTTP_UNAUTHORIZED);
    }

    /** @var \Drupal\ticket_management\Entity\TicketCommentInterface $comment */
    $comment = $this->entityTypeManager()->getStorage('ticket_comment')->create([
      'ticket_id' => (int) $ticket->id(),
      'message' => $message,
      'created_by' => (int) $account->id(),
    ]);
    $comment->save();

    return new JsonResponse($this->normalizeComment($comment), Response::HTTP_CREATED);
  }

  /**
   * Loads a ticket by numeric id.
   */
  private function loadTicket(int $id): ?TicketInterface {
    if ($id < 1) {
      return NULL;
    }
    $entity = $this->entityTypeManager()->getStorage('ticket')->load($id);
    return $entity instanceof TicketInterface ? $entity : NULL;
  }

  /**
   * Loads a user by id.
   */
  private function loadUser(int $uid): ?UserInterface {
    if ($uid < 1) {
      return NULL;
    }
    $entity = $this->entityTypeManager()->getStorage('user')->load($uid);
    return $entity instanceof UserInterface ? $entity : NULL;
  }

  /**
   * Loads comments for a ticket, oldest first.
   *
   * @return \Drupal\ticket_management\Entity\TicketCommentInterface[]
   */
  private function loadCommentsForTicket(int $ticket_id): array {
    $storage = $this->entityTypeManager()->getStorage('ticket_comment');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('ticket_id', $ticket_id)
      ->sort('created', 'ASC')
      ->execute();
    if (!$ids) {
      return [];
    }
    $entities = $storage->loadMultiple($ids);
    $comments = [];
    foreach ($ids as $cid) {
      if (isset($entities[$cid]) && $entities[$cid] instanceof TicketCommentInterface) {
        $comments[] = $entities[$cid];
      }
    }
    return $comments;
  }

  /**
   * Decodes a JSON request body.
   *
   * @return array<string, mixed>|\Symfony\Component\HttpFoundation\JsonResponse
   */
  private function decodeJson(Request $request): array|JsonResponse {
    $raw = $request->getContent();
    if ($raw === '' || $raw === FALSE) {
      return $this->errorResponse('Request body is required.', [], Response::HTTP_BAD_REQUEST);
    }
    $data = Json::decode($raw);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
      return $this->errorResponse('Malformed JSON.', [], Response::HTTP_BAD_REQUEST);
    }
    return $data;
  }

  /**
   * Normalizes a ticket entity for JSON output.
   *
   * @return array<string, mixed>
   */
  private function normalizeTicket(TicketInterface $ticket): array {
    $assigned = $ticket->get('assigned_to')->target_id;
    $created_by = $ticket->get('created_by')->target_id;
    return [
      'id' => (int) $ticket->id(),
      'uuid' => $ticket->uuid(),
      'title' => $ticket->getTitle(),
      'description' => (string) $ticket->get('description')->value,
      'priority' => $ticket->getPriority(),
      'status' => $ticket->getStatus(),
      'assignedTo' => $assigned !== NULL && $assigned !== '' ? (int) $assigned : NULL,
      'createdBy' => $created_by !== NULL && $created_by !== '' ? (int) $created_by : NULL,
      'createdAt' => $this->formatTimestamp((int) $ticket->get('created')->value),
      'updatedAt' => $this->formatTimestamp((int) $ticket->getChangedTime()),
    ];
  }

  /**
   * Normalizes a comment entity for JSON output.
   *
   * @return array<string, mixed>
   */
  private function normalizeComment(TicketCommentInterface $comment): array {
    $created_by = $comment->get('created_by')->target_id;
    return [
      'id' => (int) $comment->id(),
      'uuid' => $comment->uuid(),
      'ticketId' => $comment->getTicketId(),
      'message' => $comment->getMessage(),
      'createdBy' => $created_by !== NULL && $created_by !== '' ? (int) $created_by : NULL,
      'createdAt' => $this->formatTimestamp((int) $comment->get('created')->value),
    ];
  }

  /**
   * Formats a Unix timestamp as ISO-8601 UTC.
   */
  private function formatTimestamp(int $timestamp): string {
    return gmdate('c', $timestamp);
  }

  /**
   * Builds a JSON error response.
   *
   * @param array<int, array<string, mixed>> $errors
   */
  private function errorResponse(string $message, array $errors = [], int $status = Response::HTTP_BAD_REQUEST): JsonResponse {
    return new JsonResponse([
      'message' => $message,
      'errors' => $errors,
    ], $status);
  }

}
