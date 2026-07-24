<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ticket_management\Service\TicketStateMachineInterface;
use Drupal\ticket_management\TicketAccessControlHandler;
use Drupal\ticket_management\TicketListBuilder;
use Drupal\ticket_management\TicketStorageSchema;
use Drupal\user\UserInterface;

/**
 * Defines the Ticket content entity.
 *
 * Custom entity — not a Node bundle.
 */
#[ContentEntityType(
  id: 'ticket',
  label: new TranslatableMarkup('Ticket'),
  label_collection: new TranslatableMarkup('Tickets'),
  label_singular: new TranslatableMarkup('ticket'),
  label_plural: new TranslatableMarkup('tickets'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
  ],
  handlers: [
    'access' => TicketAccessControlHandler::class,
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => TicketListBuilder::class,
    'storage_schema' => TicketStorageSchema::class,
  ],
  links: [
    'canonical' => '/tickets/{ticket}',
    'collection' => '/tickets',
  ],
  admin_permission: 'administer ticket_management',
  base_table: 'ticket',
)]
class Ticket extends ContentEntityBase implements TicketInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return (string) $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): self {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): string {
    return (string) $this->get('priority')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority(string $priority): self {
    $this->set('priority', $priority);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedBy(): ?UserInterface {
    $entity = $this->get('created_by')->entity;
    return $entity instanceof UserInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignedTo(): ?UserInterface {
    $entity = $this->get('assigned_to')->entity;
    return $entity instanceof UserInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Enforces status rules on every save path (REST, forms, entity API):
   * - New tickets always start as open.
   * - Status changes must be allowed by TicketStateMachine.
   *
   * @throws \Drupal\ticket_management\Exception\InvalidTicketTransitionException
   *   When an update attempts an illegal status transition.
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if ($this->isNew()) {
      // Initial status is always open; never trust caller-supplied status on create.
      $this->setStatus('open');
      if (!$this->get('created_by')->target_id) {
        $uid = (int) \Drupal::currentUser()->id();
        if ($uid > 0) {
          $this->set('created_by', $uid);
        }
      }
      return;
    }

    $original = $this->getOriginal();
    if (!$original instanceof TicketInterface) {
      return;
    }

    $from = $original->getStatus();
    $to = $this->getStatus();
    if ($from !== $to) {
      /** @var \Drupal\ticket_management\Service\TicketStateMachineInterface $state_machine */
      $state_machine = \Drupal::service('ticket_management.state_machine');
      $state_machine->assertTransition($from, $to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The ticket title.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Assignment "text_long" → Drupal plain long text field type `string_long`.
    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDescription(t('Full ticket description (plain text).'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
        'settings' => ['rows' => 6],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['priority'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Priority'))
      ->setDescription(t('Ticket priority.'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'low' => 'Low',
          'medium' => 'Medium',
          'high' => 'High',
        ],
      ])
      ->setDefaultValue('medium')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Workflow status. Transitions enforced by TicketStateMachine.'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'open' => 'Open',
          'in_progress' => 'In Progress',
          'resolved' => 'Resolved',
          'closed' => 'Closed',
          'cancelled' => 'Cancelled',
        ],
      ])
      ->setDefaultValue('open')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['assigned_to'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Assigned to'))
      ->setDescription(t('User assigned to this ticket.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('User who created this ticket.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the ticket was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the ticket was last updated.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
