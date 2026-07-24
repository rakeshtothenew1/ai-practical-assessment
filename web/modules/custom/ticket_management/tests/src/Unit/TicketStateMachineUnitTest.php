<?php

declare(strict_types=1);

namespace Drupal\Tests\ticket_management\Unit;

use Drupal\ticket_management\Exception\InvalidTicketTransitionException;
use Drupal\ticket_management\Service\TicketStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for the ticket status state machine.
 */
#[Group('ticket_management')]
class TicketStateMachineUnitTest extends TestCase {

  /**
   * The state machine under test.
   */
  private TicketStateMachine $stateMachine;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->stateMachine = new TicketStateMachine();
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function allowedTransitionsProvider(): array {
    return [
      'open to in_progress' => ['open', 'in_progress'],
      'open to cancelled' => ['open', 'cancelled'],
      'in_progress to resolved' => ['in_progress', 'resolved'],
      'in_progress to cancelled' => ['in_progress', 'cancelled'],
      'resolved to closed' => ['resolved', 'closed'],
    ];
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function disallowedTransitionsProvider(): array {
    return [
      'open to closed' => ['open', 'closed'],
      'open to resolved' => ['open', 'resolved'],
      'in_progress to open' => ['in_progress', 'open'],
      'in_progress to closed' => ['in_progress', 'closed'],
      'resolved to open' => ['resolved', 'open'],
      'resolved to cancelled' => ['resolved', 'cancelled'],
      'closed to open' => ['closed', 'open'],
      'closed to in_progress' => ['closed', 'in_progress'],
      'cancelled to open' => ['cancelled', 'open'],
      'cancelled to resolved' => ['cancelled', 'resolved'],
      'same open to open' => ['open', 'open'],
      'same closed to closed' => ['closed', 'closed'],
    ];
  }

  /**
   * Tests allowed transitions return TRUE and do not throw.
   */
  #[DataProvider('allowedTransitionsProvider')]
  public function testCanTransitionAllowsValidEdges(string $from, string $to): void {
    $this->assertTrue($this->stateMachine->canTransition($from, $to));
    $this->stateMachine->assertTransition($from, $to);
  }

  /**
   * Tests disallowed transitions return FALSE and throw.
   */
  #[DataProvider('disallowedTransitionsProvider')]
  public function testCanTransitionRejectsInvalidEdges(string $from, string $to): void {
    $this->assertFalse($this->stateMachine->canTransition($from, $to));
    $this->expectException(InvalidTicketTransitionException::class);
    $this->stateMachine->assertTransition($from, $to);
  }

  /**
   * Tests getAllowedTargets for each status.
   */
  public function testGetAllowedTargets(): void {
    $this->assertSame(['in_progress', 'cancelled'], $this->stateMachine->getAllowedTargets('open'));
    $this->assertSame(['resolved', 'cancelled'], $this->stateMachine->getAllowedTargets('in_progress'));
    $this->assertSame(['closed'], $this->stateMachine->getAllowedTargets('resolved'));
    $this->assertSame([], $this->stateMachine->getAllowedTargets('closed'));
    $this->assertSame([], $this->stateMachine->getAllowedTargets('cancelled'));
  }

  /**
   * Tests transition map keys.
   */
  public function testGetTransitionMap(): void {
    $map = $this->stateMachine->getTransitionMap();
    $this->assertArrayHasKey('open', $map);
    $this->assertArrayHasKey('in_progress', $map);
    $this->assertArrayHasKey('resolved', $map);
    $this->assertArrayHasKey('closed', $map);
    $this->assertArrayHasKey('cancelled', $map);
  }

}
