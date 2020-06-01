<?php

namespace Drupal\webhook_entities\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a controller for managing webhook notifications.
 */
class WebhookEntitiesController extends ControllerBase {

  /**
   * The HTTP request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The queue factory.
   *
   * @var Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a WebhookEntitiesController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The HTTP request object.
   * @param Drupal\Core\Queue\QueueFactory $queue
   *  The queue factory.
   */
  public function __construct(Request $request, QueueFactory $queue) {
    $this->request = $request;
    $this->queueFactory = $queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('queue')
    );
  }

  /**
   * Listens for webhook notifications and queues them for processing.
   *
   * @return Symfony\Component\HttpFoundation\Response
   *   Webhook providers typically expect an HTTP 200 (OK) response.
   */
  public function listener() {
    // Prepare the response.
    $response = new Response();
    $response->setContent('Notification received');

    // Capture the contents of the notification (payload).
    $payload = $this->request->getContent();

    // Get the queue implementation.
    $queue = $this->queueFactory->get('webhook_entities_processor');

    // Add the $payload to the queue.
    $queue->createItem($payload);

    // Respond with the success message.
    return $response;
  }

  /**
   * Checks access for incoming webhook notifications.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    // Get the access token from the headers.
    $incoming_token = $this->request->headers->get('Authorization');

    // Retrieve the token value stored in config.
    $stored_token = \Drupal::config('webhook_entities.settings')->get('token');

    // Compare the stored token value to the token in each notification.
    // If they match, allow access to the route.
    return AccessResult::allowedIf($incoming_token === $stored_token);
  }

}