<?php

namespace Drupal\webhook_entities\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to configure webhook settings.
 */
class WebhookSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'webhook_entities.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webhook_entities_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Define a field used to capture the stored auth token value.
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => t('Authorization Token'),
      '#required' => TRUE,
      '#default_value' => $config->get('token'),
      '#description' => t('The expected value in the Authorization header, often an API key or similar secret.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      // Save the submitted token value.
      ->set('token', $form_state->getValue('token'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
