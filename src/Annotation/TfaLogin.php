<?php

namespace Drupal\tfa\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a TFA Login annotation object.
 *
 * @Annotation
 */
class TfaLogin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the Tfa login.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The description shown to users.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
