<?php

declare(strict_types=1);

namespace Drupal\Tests\ticket_management\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ticket_management\Entity\TicketInterface;
use Drupal\ticket_management\Exception\InvalidTicketTransitionException;
use Drupal\ticket_management\Service\TicketStateMachineInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests: entity save enforces TicketStateMachine via preSave().
 */
#[Group('ticket_management')]
#[RunTestsInSeparateProcesses]
class TicketStateMachineKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'options',
    'serialization',
    'rest',
    'jsonapi',
    'ticket_management',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('ticket');
    $this->installEntitySchema('ticket_comment');
    $this->installConfig(['system', 'user']);
    $this->setUpCurrentUser();
  }

  /**
   * Creates a ticket with status open.
   */
  private function createOpenTicket(): TicketInterface {
    $account = $this->createUser();
    $this->setCurrentUser($account);

    /** @var \Drupal\ticket_management\Entity\TicketInterface $ticket */
    $ticket = $this->container->get('entity_type.manager')->getStorage('ticket')->create([
      'title' => 'Kernel test ticket',
      'description' => 'Description for kernel test.',
      'priority' => 'medium',
      // Intentionally wrong; preSave must force open.
      'status' => 'closed',
      'created_by' => (int) $account->id(),
    ]);
    $ticket->save();
    $this->assertSame('open', $ticket->getStatus(), 'New tickets are forced to open.');
    return $ticket;
  }

  /**
   * Tests a valid transition persists through entity save.
   */
  public function testValidTransitionIsPersistedOnSave(): void {
    $ticket = $this->createOpenTicket();
    /** @var \Drupal\ticket_management\Service\TicketStateMachineInterface $machine */
    $machine = $this->container->get('ticket_management.state_machine');
    $machine->apply($ticket, 'in_progress');
    $ticket->save();

    $reloaded = $this->container->get('entity_type.manager')->getStorage('ticket')->load($ticket->id());
    $this->assertInstanceOf(TicketInterface::class, $reloaded);
    $this->assertSame('in_progress', $reloaded->getStatus());
  }

  /**
   * Tests an invalid transition is rejected on save and status is unchanged.
   */
  public function testInvalidTransitionIsRejectedOnSave(): void {
    $ticket = $this->createOpenTicket();
    $id = (int) $ticket->id();

    $ticket->setStatus('closed');
    try {
      $ticket->save();
      $this->fail('Expected EntityStorageException was not thrown.');
    }
    catch (EntityStorageException $e) {
      $previous = $e->getPrevious();
      $this->assertInstanceOf(InvalidTicketTransitionException::class, $previous);
      $this->assertStringContainsString('open', $previous->getMessage());
      $this->assertStringContainsString('closed', $previous->getMessage());
    }

    $reloaded = $this->container->get('entity_type.manager')->getStorage('ticket')->load($id);
    $this->assertInstanceOf(TicketInterface::class, $reloaded);
    $this->assertSame('open', $reloaded->getStatus());
  }

  /**
   * Tests assertTransition throws for illegal edges.
   */
  public function testStateMachineServiceAssertTransition(): void {
    /** @var \Drupal\ticket_management\Service\TicketStateMachineInterface $machine */
    $machine = $this->container->get('ticket_management.state_machine');
    $this->assertInstanceOf(TicketStateMachineInterface::class, $machine);
    $this->expectException(InvalidTicketTransitionException::class);
    $machine->assertTransition('open', 'resolved');
  }

}
