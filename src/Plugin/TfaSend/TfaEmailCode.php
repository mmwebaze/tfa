<?php

namespace Drupal\tfa\Plugin\TfaSend;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaSendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\Exception\EncryptException;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tfa\TfaRandomTrait;
use Drupal\user\UserInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Class for sending a code by email.
 *
 * @TfaSend(
 *   id = "tfa_email_send",
 *   label = @Translation("Send by Email"),
 *   description = @Translation("Send TFA Code by Email."),
 * )
 */
class TfaEmailCode extends TfaBasePlugin implements TfaSendInterface, ContainerFactoryPluginInterface {

  use TfaRandomTrait;
  use StringTranslationTrait;
  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A mail manager for sending email.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The Logger factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The validity of a code in seconds.
   *
   * @var int
   */
  protected $validityLength;

  /**
   * The user's email.
   *
   * @var string|null
   */
  protected $email;

  /**
   * The user's language code.
   *
   * @var string
   */
  protected $langCode;

  /**
   * The time service.
   *
   * @var TimeInterface
   */
  protected $time;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data,
                              EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service,
                              LoggerChannelFactoryInterface $logger_factory, MailManagerInterface $mail_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);
    /** @var UserInterface $user */
    $user = $configuration['user'];
    $this->langCode = $user->getPreferredLangcode();
    $this->email = $user->getEmail();
    $this->loggerFactory = $logger_factory;
    $this->mailManager = $mail_manager;
    $settings = $configuration['settings']->get('validation_plugin_settings.tfa_email_send');
    $this->validityLength = $settings['code_validity_period'];
    $this->time = $time;
  }

  /**
   * TFA process begin.
   * @throws EncryptException
   */
  public function begin() {
    $userData = $this->userData->get('tfa', $this->uid, 'tfa_send_code');

    $code = $this->randomCharacters(9, '1234567890');
    $userData['code'] = $this->encryptService->encrypt($code, $this->encryptionProfile);;
    $userData['expiry'] = $this->time->getCurrentTime() + $this->validityLength;
    $this->userData->set('tfa', $this->uid, 'tfa_send_code', $userData);
    $length = $this->validityLength / 60;
    $message['title'] = 'Authentication code:';
    $message['subject'] = 'Authentication code:';
    $message['langcode'] = $this->langCode;
    $message['message'] = "This code is valid for {$length} minutes. Your code is: {$code}";

    $result = $this->mailManager->mail('tfa', 'tfa_send_code', $this->email, $this->langCode, $message, NULL, true);

    if ($result['result'] != true) {
      $message = t('There was a problem sending authentication code to @email.', array('@email' => $this->email));
      \Drupal::logger('tfa')->error($message);
    }
    else{
      \Drupal::logger('tfa')->notice(json_encode($message));
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
      $container->get('logger.factory'),
      $container->get('plugin.manager.mail'),
      $container->get('datetime.time')
    );
  }

  /**
   * Determine if the plugin can run for the current TFA context.
   *
   * @return bool
   *    True or False based on the checks performed.
   */
  public function ready() {

  }
}
