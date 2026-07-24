<?php

declare(strict_types=1);

namespace Drupal\Tests\ticket_management\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ticket_management\Entity\TicketInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional REST tests for ticket status transitions (mandatory core tier).
 *
 * Asserts HTTP 200 for valid transitions and 422 for invalid ones.
 */
#[Group('ticket_management')]
#[RunTestsInSeparateProcesses]
class TicketApiFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'options',
    'ticket_management',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Authenticated user with ticket API permissions.
   */
  protected UserInterface $apiUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->apiUser = $this->drupalCreateUser([
      'access ticket overview',
      'view ticket',
      'create ticket',
      'edit ticket',
      'transition ticket status',
      'create ticket comment',
    ]);
    $this->drupalLogin($this->apiUser);
  }

  /**
   * Creates an open ticket owned by the API user.
   */
  protected function createTicketEntity(): TicketInterface {
    /** @var \Drupal\ticket_management\Entity\TicketInterface $ticket */
    $ticket = \Drupal::entityTypeManager()->getStorage('ticket')->create([
      'title' => 'Functional API ticket',
      'description' => 'Created for functional REST tests.',
      'priority' => 'high',
      'created_by' => (int) $this->apiUser->id(),
    ]);
    $ticket->save();
    $this->assertSame('open', $ticket->getStatus());
    return $ticket;
  }

  /**
   * Performs a JSON HTTP request using the Mink BrowserKit client.
   *
   * @param array<string, mixed>|null $payload
   *   JSON body, or NULL for no body.
   *
   * @return array{status: int, body: array<string, mixed>|null}
   *   Status code and decoded JSON body.
   */
  protected function jsonRequest(string $method, string $path, ?array $payload = NULL): array {
    $server = [
      'CONTENT_TYPE' => 'application/json',
      'HTTP_ACCEPT' => 'application/json',
    ];
    $method_upper = strtoupper($method);
    // Write routes require _csrf_request_header_token; match ticket-app.js.
    if (!in_array($method_upper, ['GET', 'HEAD'], TRUE)) {
      $server['HTTP_X_CSRF_TOKEN'] = $this->getSessionCsrfToken();
    }
    $content = $payload === NULL ? NULL : json_encode($payload, JSON_THROW_ON_ERROR);

    $client = $this->getSession()->getDriver()->getClient();
    $client->request($method, $path, [], [], $server, $content);
    $response = $client->getResponse();
    $raw = $response->getContent();
    $body = NULL;
    if (is_string($raw) && $raw !== '') {
      $decoded = json_decode($raw, TRUE);
      $body = is_array($decoded) ? $decoded : NULL;
    }
    return [
      'status' => (int) $response->getStatusCode(),
      'body' => $body,
    ];
  }

  /**
   * Fetches the session CSRF token used by _csrf_request_header_token routes.
   */
  protected function getSessionCsrfToken(): string {
    $client = $this->getSession()->getDriver()->getClient();
    $client->request('GET', '/session/token');
    $token = trim((string) $client->getResponse()->getContent());
    $this->assertNotSame('', $token, 'Expected a non-empty CSRF session token.');
    return $token;
  }

  /**
   * Tests valid status transition returns 200.
   */
  public function testValidStatusTransitionReturns200(): void {
    $ticket = $this->createTicketEntity();
    $result = $this->jsonRequest('PATCH', '/api/tickets/' . $ticket->id() . '/status', [
      'status' => 'in_progress',
    ]);

    $this->assertSame(200, $result['status'], 'Valid transition must return HTTP 200.');
    $this->assertIsArray($result['body']);
    $this->assertSame('in_progress', $result['body']['status']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('ticket')->load($ticket->id());
    $this->assertInstanceOf(TicketInterface::class, $reloaded);
    $this->assertSame('in_progress', $reloaded->getStatus());
  }

  /**
   * Tests invalid status transition returns 422 with JSON error body.
   */
  public function testInvalidStatusTransitionReturns422(): void {
    $ticket = $this->createTicketEntity();
    $result = $this->jsonRequest('PATCH', '/api/tickets/' . $ticket->id() . '/status', [
      'status' => 'closed',
    ]);

    $this->assertSame(422, $result['status'], 'Invalid transition must return HTTP 422.');
    $this->assertIsArray($result['body']);
    $this->assertArrayHasKey('message', $result['body']);
    $this->assertArrayHasKey('errors', $result['body']);
    $this->assertNotEmpty($result['body']['errors']);
    $this->assertSame('invalid_transition', $result['body']['errors'][0]['code']);

    $reloaded = \Drupal::entityTypeManager()->getStorage('ticket')->load($ticket->id());
    $this->assertInstanceOf(TicketInterface::class, $reloaded);
    $this->assertSame('open', $reloaded->getStatus(), 'Invalid transition must not change status.');
  }

  /**
   * Tests GET collection returns 200.
   */
  public function testListTicketsReturns200(): void {
    $this->createTicketEntity();
    $result = $this->jsonRequest('GET', '/api/tickets');
    $this->assertSame(200, $result['status']);
    $this->assertIsArray($result['body']);
    $this->assertArrayHasKey('data', $result['body']);
    $this->assertArrayHasKey('meta', $result['body']);
  }

  /**
   * Tests POST create returns 201 with open status.
   */
  public function testCreateTicketReturns201(): void {
    $result = $this->jsonRequest('POST', '/api/tickets', [
      'title' => 'Created via functional test',
      'description' => 'Body',
      'priority' => 'low',
    ]);
    $this->assertSame(201, $result['status']);
    $this->assertIsArray($result['body']);
    $this->assertSame('open', $result['body']['status']);
    $this->assertArrayHasKey('id', $result['body']);
  }

}
