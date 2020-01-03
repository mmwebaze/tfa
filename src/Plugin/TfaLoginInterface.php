<?php

namespace Drupal\tfa\Plugin;

/**
 * Interface TfaLoginInterface.
 *
 * Login plugins interact with the Tfa loginAllowed() process prior to starting
 * a TFA process.
 */
interface TfaLoginInterface {

  /**
   * Whether authentication should be interrupted.
   *
   * @return bool
   */
  public function loginAllowed();

}
