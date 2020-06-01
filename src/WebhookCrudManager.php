<?php

namespace Drupal\webhook_entities;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 * Entity CRUD operations in response to webhook notifications.
 *
 */
class WebhookCrudManager {

  /**
   * The manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The default logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a WebhookCrudManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Creates a new entity using notification data.
   *
   * @param object $entity_data
   *   Required data from the notification body.
   */
  public function createEntity($entity_data) {
    // Map incoming notification values to Drupal fields.
    $node_values = $this->mapFieldData($entity_data);

    // Ensure any required values exist before proceeding.
    // UUID was already confirmed in the queue worker.
    if (!empty($node_values['title'])) {
      // Add other values used for node creation.
      $node_values['type'] = 'page';
      $node_values['field_webhook_uuid'] = $entity_data->uuid;
      // Attempt to create a node from the notification data.
      try {
        $storage = $this->entityTypeManager->getStorage('node');
        $node = $storage->create($node_values);
        $node->save();
        // Log a message when sucessful
        $this->logger->notice('Node @nid created to represent webhook entity @uuid', [
          '@nid' => $node->id(),
          '@uuid' => $entity_data->uuid
        ]);
      }
      // Display an error if the node could not be created.
      catch (\Exception $e) {
        $this->logger->warning('A node could not be created to represent webhook entity @uuid. @error', [
          '@uuid' => $entity_data->uuid,
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Updates an existing entity with notification data.
   *
   * @param object $existing_entity
   *   A Drupal entity that was loaded by its UUID field.
   * @param object $entity_data
   *   Required data from the notification body.
   */
  public function updateEntity($existing_entity, $entity_data) {
    // Flag to track update status.
    $updated = FALSE;

    // Update the title if it was changed by a notification.
    if (!empty($existing_entity->title)) {
      $existing_entity->title = $entity_data->title;
      $updated = TRUE;
    }
    // Update the body if it was changed by a notification.
    if (!empty($existing_entity->body)) {
      $existing_entity->body->value = $entity_data->body;
      $updated = TRUE;
    }

    // Save the entity if any fields were updated.
    if ($updated) {
      $existing_entity->save();
      // Log a notice that the entity was updated.
      $this->logger->notice('Node @nid updated via webhook notification.', [
        '@nid' => $existing_entity->id(),
      ]);
    }
  }

  /**
   * Deletes an exsiting entity identified in a notification.
   *
   * @param object $existing_entity
   *   Required data from the notification body.
   */
  public function deleteEntity($existing_entity) {
    // Log a notice that the entity was deleted.
    $this->logger->notice('Node @nid deleted via webhook notification.', [
      '@nid' => $existing_entity->id(),
    ]);
    $existing_entity->delete();
  }

  /**
   * Maps and optionally sanitizes payload data for entity creation.
   *
   * @param object $entity_data
   *   Required data from the notification body.
   *
   * @return array $node_values
   *   Structured field values required for creating a basic page node.
   */
  private function mapFieldData($entity_data) {
    // Store values in an array to facilitate node creation.
    $node_values = [];

    // Capture the title from the notification data.
    if (!empty($entity_data->title)) {
      $node_values['title'] = $entity_data->title;
    }

    // Capture the body from the notification data.
    if (!empty($entity_data->body)) {
      $node_values['body'] = [
        'value' => $entity_data->body,
        'format' => 'basic_html',
      ];
    }

    return $node_values;
  }
}