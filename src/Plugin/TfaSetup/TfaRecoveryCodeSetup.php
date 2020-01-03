<?php

namespace Drupal\tfa\Plugin\TfaSetup;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tfa\Plugin\TfaSetupInterface;
use Drupal\tfa\Plugin\TfaValidation\TfaRecoveryCode;
use Drupal\tfa\TfaDataTrait;

/**
 * Class TfaRecoveryCodeSetup.
 *
 * @TfaSetup(
 *   id = "tfa_recovery_code_setup",
 *   label = @Translation("TFA Recovery Code Setup"),
 *   description = @Translation("TFA Recovery Code Setup Plugin"),
 *   setupMessages = {
 *    "saved" = @Translation("Recovery codes saved."),
 *    "skipped" = @Translation("Recovery codes not saved.")
 *   }
 * )
 */
class TfaRecoveryCodeSetup extends TfaRecoveryCode implements TfaSetupInterface {
  use TfaDataTrait;

  /**
   * Determine if the plugin can run for the current TFA context.
   *
   * @return bool
   *   True or False based on the checks performed.
   */
  public function ready() {
    return TRUE;
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
    $output = [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Recovery Codes'),
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Generate one-time use codes for two-factor login. These are generally used to recover your account in case you lose access to another 2nd-factor device.'),
      ],
      'setup' => [
        '#theme' => 'links',
        '#links' => [
          'reset' => [
            'title' => !$params['enabled'] ? $this->t('Generate codes') : $this->t('Reset codes'),
            'url' => Url::fromRoute('tfa.plugin.reset', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
              'reset' => 1,
            ]),
          ],
        ],
      ],
      'show_codes' => [
        '#theme' => 'links',
        '#access' => $params['enabled'],
        '#links' => [
          'show' => [
            'title' => $this->t('Show codes'),
            'url' => Url::fromRoute('tfa.validation.setup', [
              'user' => $params['account']->id(),
              'method' => $params['plugin_id'],
            ]),
          ],
        ],
      ],
    ];
    return $output;
  }

  /**
   * Get the setup form for the validation method.
   *
   * @param array $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $reset
   *   Whether or not the user is resetting the application.
   *
   * @return array
   *   Form API array.
   */
  public function getSetupForm(array $form, FormStateInterface $form_state, $reset = 0) {
    $codes = $this->getCodes();

    // If $reset has a value, we're setting up new codes.
    if (!empty($reset)) {
      $codes = $this->generateCodes();

      // Make the human friendly.
      foreach ($codes as $key => $code) {
        $codes[$key] = implode(' ', str_split($code, 3));
      }
      $form['recovery_codes'] = [
        '#type' => 'value',
        '#value' => $codes,
      ];
    }

    $form['recovery_codes_output'] = [
      '#title' => $this->t('Recovery Codes'),
      '#theme' => 'item_list',
      '#items' => $codes,
    ];
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Print or copy these codes and store them somewhere safe before continuing.'),
    ];

    if (!empty($reset)) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['save'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save codes to account'),
      ];
    }

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
   *   Whether or not form passes validation.
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('recovery_codes'))) {
      return TRUE;
    }

    return FALSE;
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
    $this->storeCodes($form_state->getValue('recovery_codes'));
    return TRUE;
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
    return ($this->pluginDefinition['setupMessages']) ?: [];
  }

}
