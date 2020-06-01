<?php

namespace Drupal\webhook_entities;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class WebhookUuidLookup.
 *
 * Attempts to load an entity by the UUID received from a webhook notification.
 *
 */
class WebhookUuidLookup {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * WebhookUuidLookup constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public function findEntity($uuid) {
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_webhook_uuid' => $uuid]);

    if ($node = reset($nodes)) {
      return $node;
    }

    return FALSE;
  }
}