<?php

namespace Drupal\tfa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tfa\TfaDataTrait;
use Drupal\tfa\TfaSetupPluginManager;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * TFA Basic account setup overview page.
 */
class BasicOverview extends FormBase {
  use TfaDataTrait;

  /**
   * The setup plugin manager to fetch setup information.
   *
   * @var \Drupal\tfa\TfaLoginPluginManager
   */
  protected $tfaSetup;

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * BasicOverview constructor.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\tfa\TfaSetupPluginManager $tfa_setup_manager
   *   The setup plugin manager.
   */
  public function __construct(UserDataInterface $user_data, TfaSetupPluginManager $tfa_setup_manager) {
    $this->userData = $user_data;
    $this->tfaSetup = $tfa_setup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.data'),
      $container->get('plugin.manager.tfa.setup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tfa_base_overview';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $output['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Two-factor authentication (TFA) provides
      additional security for your account. With TFA enabled, you log in to
      the site with a verification code in addition to your username and
      password.') . '</p>',
    ];
    // $form_state['storage']['account'] = $user;.
    $configuration = $this->config('tfa.settings')->getRawData();
    $user_tfa = $this->tfaGetTfaData($user->id(), $this->userData);
    $enabled = isset($user_tfa['status']) && $user_tfa['status'] ? TRUE : FALSE;

    if (!empty($user_tfa)) {
      $date_formatter = \Drupal::service('date.formatter');
      if ($enabled && !empty($user_tfa['data']['plugins'])) {
        if ($this->currentUser()->hasPermission('disable own tfa')) {
          $status_text = $this->t('Status: <strong>TFA enabled</strong>, set
          @time. <a href=":url">Disable TFA</a>', [
            '@time' => $date_formatter->format($user_tfa['saved']),
            ':url' => URL::fromRoute('tfa.disable', ['user' => $user->id()])->toString(),
          ]);
        }
        else {
          $status_text = $this->t('Status: <strong>TFA enabled</strong>, set @time.', [
            '@time' => $date_formatter->format($user_tfa['saved']),
          ]);
        }
      }
      else {
        $status_text = $this->t('Status: <strong>TFA disabled</strong>, set @time.', [
          '@time' => $date_formatter->format($user_tfa['saved']),
        ]);
      }
      $output['status'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $status_text . '</p>',
      ];
    }

    if ($configuration['enabled']) {
      $enabled = isset($user_tfa['status'],$user_tfa['data']) && !empty($user_tfa['data']['plugins']) && $user_tfa['status'] ? TRUE : FALSE;
      // Validation plugin setup.
      $allowed_plugins = $configuration['allowed_validation_plugins'];
      $enabled_plugins = isset($user_tfa['data']['plugins']) ? $user_tfa['data']['plugins'] : [];
      $default_plugin = $configuration['default_validation_plugin'];

      foreach ($allowed_plugins as $allowed_plugin) {
        $output[$allowed_plugin] = $this->tfaPluginSetupFormOverview($allowed_plugin, $user, !empty($enabled_plugins[$allowed_plugin]));
      }

      if ($enabled) {
        $login_plugins = $configuration['login_plugins'];
        foreach ($login_plugins as $lplugin_id) {
          $output[$lplugin_id] = $this->tfaPluginSetupFormOverview($lplugin_id, $user, $enabled);

        }

        $send_plugin = $configuration['send_plugins'];
        if ($send_plugin) {
          foreach ($send_plugin as $lplugin_id){
            $output[$send_plugin] = $this->tfaPluginSetupFormOverview($lplugin_id, $user, $enabled);
          }
        }
      }
    }
    else {
      $output['disabled'] = [
        '#type' => 'markup',
        '#markup' => '<b>Currently there are no enabled plugins.</b>',
      ];
    }

    if ( $configuration['enabled'] ) {
      $output['validation_skip_status'] = [
        '#type'   => 'markup',
        '#markup' => $this->t('Number of times validation skipped: @skipped of @limit', [
          '@skipped' => isset($user_tfa['validation_skipped']) ? $user_tfa['validation_skipped'] : 0,
          '@limit' => $configuration['validation_skip'],
        ]),
      ];
    }

    if ($this->canPerformReset($user)) {
      $output['actions'] = ['#type' => 'actions'];
      $output['actions']['reset_skip_attempts'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset skip validation attempts'),
        '#submit' => ['::resetSkipValidationAttempts'],
      ];
      $output['account'] = [
        '#type' => 'value',
        '#value' => $user,
      ];
    }

    return $output;
  }

  /**
   * Get TFA basic setup action links for use on overview page.
   *
   * @param string $plugin
   *   The setup plugin.
   * @param object $account
   *   Current user account.
   * @param bool $enabled
   *   Tfa data for current user.
   *
   * @return array
   *   Render array
   */
  protected function tfaPluginSetupFormOverview($plugin, $account, $enabled) {
    $params = [
      'enabled' => $enabled,
      'account' => $account,
      'plugin_id' => $plugin,
    ];
    $output = $this->tfaSetup
                ->createInstance($plugin . '_setup', ['uid' => $account->id()])
                ->getOverview($params);
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Resets TFA attempts for the given user account.
   *
   * @param array $form
   *   The form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function resetSkipValidationAttempts(array $form, FormStateInterface $form_state) {
    $account = $form_state->getValue('account');
    $tfa_data = $this->tfaGetTfaData($account->id(), $this->userData);
    $tfa_data['validation_skipped'] = 0;
    $this->tfaSaveTfaData($account->id(), $this->userData, $tfa_data);
    $this->messenger()->addMessage($this->t('Validation attempts have been reset for user @name.', [
      '@name' => $account->getDisplayName(),
    ]));
    $this->logger('tfa')->notice('Validation attempts reset for @account by @current_user.', [
      '@account' => $account->getAccountName(),
      '@current_user' => $this->currentUser()->getAccountName(),
    ]);
  }

  /**
   * Determine if the current user can perform a TFA attempt reset.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account that TFA is for.
   */
  protected function canPerformReset(UserInterface $account) {
    $current_user = $this->currentUser();
    return $current_user->hasPermission('administer users')
      // Disallow users from resetting their own.
      // @todo Make this configurable.
      && $current_user->id() != $account->id();
  }

}
