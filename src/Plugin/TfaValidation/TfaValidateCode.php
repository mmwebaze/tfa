<?php

namespace Drupal\tfa\Plugin\TfaValidation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Exception\EncryptException;
use Drupal\encrypt\Exception\EncryptionMethodCanNotDecryptException;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Performs validation of code sent to user.
 *
 * @TfaValidation(
 *   id = "tfa_email_send",
 *   label = @Translation("TFA Code Validation"),
 *   description = @Translation("TFA Code Validation Plugin"),
 * )
 */
class TfaValidateCode extends TfaBasePlugin implements TfaValidationInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Logger factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The time service.
   *
   * @var TimeInterface
   */
  protected $time;

  /**
   * Default code validity in seconds.
   *
   * @var int
   */
  protected $validityPeriod = 300;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data,
                              EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service,
                              ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);

    $validity_period = $config_factory->get('tfa.settings')->get('validation_plugin_settings.tfa_email_send.code_validity_period');
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    if (!empty($validity_period)) {
      $this->validityPeriod = $validity_period;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.data'),
      $container->get('encrypt.encryption_profile.manager'),
      $container->get('encryption'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter the received code'),
      '#required' => TRUE,
      '#description' => $this->t('The code has a Format: XXX XXX XXX and is valid for...'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['login'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Verify'),
    ];
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  protected function validate($code) {
    $this->isValid = FALSE;

    // Remove empty spaces.
    $code = str_replace(' ', '', $code);
    $userData = $this->userData->get('tfa', $this->uid, 'tfa_send_code');
    $timestamp = $this->time->getCurrentTime();

    if ($timestamp > $userData['expiry']){
      unset($userData['code']);
      unset($userData['expiry']);
      return $this->isValid;
    }
    $storedCode = $userData['code'];
    try {
      $storedCode = $this->encryptService->decrypt($storedCode, $this->encryptionProfile);
    } catch (EncryptException $e) {
      $this->loggerFactory->get('tfa')->error($e->getMessage());
    } catch (EncryptionMethodCanNotDecryptException $e) {
      $this->loggerFactory->get('tfa')->error($e->getMessage());
    }

    if (trim(str_replace(' ', '', $storedCode)) === $code) {
      $this->isValid = TRUE;
      unset($userData['code']);
      unset($userData['expiry']);
      $this->userData->set('tfa', $this->uid, 'tfa_send_code', $userData);
      return $this->isValid;
    }

    return $this->isValid;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    return $this->validate($values['code']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(Config $config, array $state = []) {
    $options = array(60 => 1, 120 => 2, 180 => 3, 240 => 4, 300 => 5);

    $settings_form['code_validity_period'] = array(
      '#type' => 'select',
      '#title' => $this->t('Code validity period in minutes'),
      '#description' => $this->t('Select the validity period of code sent.'),
      '#options' => $options,
      '#default_value' => $this->validityPeriod,
    );

    return $settings_form;
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    $userData = $this->userData->get('tfa', $this->uid, 'tfa_user_settings');

    if (empty($userData['data']['plugins'])){
      return FALSE;
    }
    return TRUE;
  }
}
