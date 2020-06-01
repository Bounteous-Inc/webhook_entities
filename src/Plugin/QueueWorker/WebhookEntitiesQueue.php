<?php

namespace Drupal\webhook_entities\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\webhook_entities\WebhookCrudManager;
use Drupal\webhook_entities\WebhookUuidLookup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of webhook notification payload data.
 *
 * @QueueWorker(
 *   id = "webhook_entities_processor",
 *   title = @Translation("Webhook notification processor"),
 *   cron = {"time" = 30}
 * )
 */
class WebhookEntitiesQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The default logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * CRUD service for entities managed via notifications.
   *
   * @var \Drupal\webhook_entities\WebhookCrudManager
   */
  protected $entityCrud;

  /**
   * The UUID lookup service.
   *
   * @var \Drupal\webhook_entities\WebhookUuidLookup
   */
  protected $uuidLookup;

  /**
   * Constructs a WebhookEntitiesQueue object.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\webhook_entities\CrudManader $crud_manager
   *   An instance of the custom entity CRUD manager.
   * @param \Drupal\webhook_entities\WebhookUuidLookup $uuid_lookup
   *   An instance of the UUID lookup service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, WebhookCrudManager $crud_manager, WebhookUuidLookup $uuid_lookup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->entityCrud = $crud_manager;
    $this->uuidLookup = $uuid_lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      empty($configuration) ? [] : $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.default'),
      $container->get('webhook_entities.crud_manager'),
      $container->get('webhook_entities.uuid_lookup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($payload) {
    // Only process the payload if it contains data.
    if (!empty($payload)) {

      // Decode the JSON payload to a PHP object.
      $entity_data = json_decode($payload);

      // Only process the notification if it contains a UUID.
      if (isset($entity_data->uuid)) {

        // Remove all values we won't be using.
        $unused_value_keys = ['an_unused_value', 'another_unused_value'];
        foreach ($unused_value_keys as $key) {
          if (isset($entity_data->{$key})) {
            unset($entity_data->{$key});
          }
        }

        // Determine whether an existing Drupal entity already
        // corresponds to the incoming UUID.
        $existing_entity = isset($entity_data->uuid) ? $this->uuidLookup->findEntity($entity_data->uuid) : NULL;

        // Handle create events.
        if ($entity_data->event == 'create') {
          // Create a new entity if one doesn't already exist.
          if (!$existing_entity) {
            $this->entityCrud->createEntity($entity_data);
          }
          // Otherwise log a warning.
          else {
            $this->logger->warning('Webhook create notification received for UUID @uuid but corresponding entity @nid already exists', [
              '@uuid' => $entity_data->uuid,
              '@nid' => $existing_entity->id()
            ]);
          }
        }
        // Handle other modification events.
        else {
          // Ensure a Drupal entity to modify exists.
          if ($existing_entity) {
            switch($entity_data->event) {
              case 'update' :
                // Update an entity by passing it and the changed values to our CRUD worker.
                $this->entityCrud->updateEntity($existing_entity, $entity_data);
                break;

              case 'delete' :
                // Call the delete method in our CRUD worker on the entity.
                $this->entityCrud->deleteEntity($existing_entity);
                break;
            }
          }
          // Throw a warning when there is no existing entity to modify.
          else {
            $this->logger->warning('Webhook notification received for UUID @uuid but no corresponding Drupal entity exists', [
              '@uuid' => $entity_data->uuid
            ]);
          }
        }
      }
      // Throw a warning if the payload doesn't contain a UUID.
      else {
        $this->logger->warning('Webhook notification received but not processed because UUID was missing');
      }
    }
  }

}