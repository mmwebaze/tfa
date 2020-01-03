<?php

namespace Drupal\tfa_test_plugins\Plugin\TfaSetup;

use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaSetupInterface;

/**
 * Class TfaTestValidationPluginSetupPlugin.
 *
 * @package Drupal\tfa_test_plugins
 *
 * @TfaSetup(
 *   id = "tfa_test_plugins_validation_setup",
 *   label = @Translation("TFA Test Validation Plugin Setup"),
 *   description = @Translation("TFA Test Validation Plugin Setup Plugin"),
 *   helpLinks = {},
 *   setupMessages = {}
 * )
 */
class TfaTestValidationPluginSetupPlugin extends TfaBasePlugin implements TfaSetupInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function ready() {
    return TRUE;
  }

  /**
   * Get the setup form for the validation method.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form API array.
   */
  public function getSetupForm(array $form, FormStateInterface $form_state) {
    $form['expected_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Expected field'),
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['login'] = [
      '#type'  => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Verify and save'),
    ];
    return $form;
  }

  /**
   * Validate the setup data.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether or not the form is valid.
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state) {
    $expected_value = $form_state->getValue('expected_field');

    if (empty($expected_value)) {
      $form_state->setError($form['expected_field'], $this->t('Missing expected value.'));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Submit the setup form.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if no errors occur when saving the data.
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state) {
    $encrypted = $this->encrypt($form_state->getValue('expected_field'));
    $record = [
      'test_data' => [
        'expected_field' => base64_encode($encrypted),
      ],
    ];
    $this->setUserData($this->pluginDefinition['id'], $record, $this->uid, $this->userData);

    return TRUE;
  }

  /**
   * Get and decode the data expected during setup.
   *
   * @return null|string
   *   The string if found, otherwise NULL;
   */
  public function getExpectedFieldData() {
    $data = $this->getUserData($this->pluginDefinition['id'], 'test_data', $this->uid, $this->userData);
    if (!empty($data['expected_field'])) {
      return $this->decrypt(base64_decode($data['expected_field']));
    }

    return NULL;
  }

  /**
   * Returns a list of links containing helpful information for plugin use.
   *
   * @return string[]
   *   An array containing help links for e.g., OTP generation.
   */
  public function getHelpLinks() {
    return [];
  }

  /**
   * Returns a list of messages for plugin step.
   *
   * @return string[]
   *   An array containing messages to be used during plugin setup.
   */
  public function getSetupMessages() {
    return [];
  }

  /**
   * Plugin overview page.
   *
   * @param array $params
   *   Parameters to setup the overview information.
   *
   * @return array
   *   The overview form.
   */
  public function getOverview($params) {
    return [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('TFA application'),
      ],
      'link' => [
        '#theme' => 'links',
        '#links' => [
          'admin' => [
            'title' => !$params['enabled'] ? $this->t('Set up application') : $this->t('Reset application'),
            'url' => Url::fromRoute('tfa.validation.setup', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
            ]),
          ],
        ],
      ],
    ];
  }

}
