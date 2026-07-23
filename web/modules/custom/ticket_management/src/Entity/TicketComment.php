<?php

declare(strict_types=1);

namespace Drupal\ticket_management\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ticket_management\TicketCommentAccessControlHandler;
use Drupal\ticket_management\TicketCommentListBuilder;
use Drupal\user\UserInterface;

/**
 * Defines the Ticket Comment content entity.
 *
 * Custom entity — not the Drupal core Comment entity.
 */
#[ContentEntityType(
  id: 'ticket_comment',
  label: new TranslatableMarkup('Ticket comment'),
  label_collection: new TranslatableMarkup('Ticket comments'),
  label_singular: new TranslatableMarkup('ticket comment'),
  label_plural: new TranslatableMarkup('ticket comments'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'id',
  ],
  handlers: [
    'access' => TicketCommentAccessControlHandler::class,
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => TicketCommentListBuilder::class,
  ],
  admin_permission: 'administer ticket_management',
  base_table: 'ticket_comment',
)]
class TicketComment extends ContentEntityBase implements TicketCommentInterface {

  /**
   * {@inheritdoc}
   */
  public function getTicketId(): ?int {
    $target_id = $this->get('ticket_id')->target_id;
    return $target_id !== NULL ? (int) $target_id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    return (string) $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): self {
    $this->set('message', $message);
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
  public function label() {
    $message = $this->getMessage();
    if ($message === '') {
      return (string) $this->id();
    }
    return mb_strlen($message) > 50 ? mb_substr($message, 0, 47) . '…' : $message;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['ticket_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Ticket'))
      ->setDescription(t('The parent support ticket.'))
      ->setSetting('target_type', 'ticket')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Assignment "text_long" → Drupal plain long text field type `string_long`.
    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setDescription(t('Comment body (plain text).'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'basic_string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
        'settings' => ['rows' => 4],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('User who wrote this comment.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the comment was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
