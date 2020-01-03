<?php

namespace Drupal\tfa\Plugin\EncryptionMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\encrypt\EncryptionMethodInterface;
use Drupal\encrypt\Plugin\EncryptionMethod\EncryptionMethodBase;

/**
 * Class McryptAES128Encryption.
 *
 * @package Drupal\encrypt\Plugin\EncryptionMethod
 *
 * @EncryptionMethod(
 *   id = "mcrypt_aes_128",
 *   title = @Translation("Mcrypt AES 128"),
 *   description = "This uses PHPs mcrypt extension and <a href='http://en.wikipedia.org/wiki/Advanced_Encryption_Standard'>AES-128</a>.",
 *   key_type = {"encryption"}
 * )
 */
class McryptAES128Encryption extends EncryptionMethodBase implements EncryptionMethodInterface {
  use StringTranslationTrait;

  /**
   * @return mixed
   */
  public function encrypt($text, $key, $options = []) {
    $processed_text = '';

    // Key cannot be too long for this encryption.
    $key = Unicode::substr($key, 0, 32);

    // Define iv cipher.
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $disable_base64 = array_key_exists('base64', $options) && $options['base64'] == FALSE;

    $processed_text = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $text, MCRYPT_MODE_ECB, $iv);

    // Check if we are disabling base64 encoding.
    if (!$disable_base64) {
      $processed_text = base64_encode($processed_text);
    }

    return $processed_text;
  }

  /**
   * @return mixed
   */
  public function decrypt($text, $key, $options = []) {
    $processed_text = '';

    // Key cannot be too long for this encryption.
    $key = Unicode::substr($key, 0, 32);

    // Define iv cipher.
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $disable_base64 = array_key_exists('base64', $options) && $options['base64'] == FALSE;

    // Check if we are disabling base64 encoding.
    if (!$disable_base64) {
      $text = base64_decode($text);
    }

    // Decrypt text.
    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $text, MCRYPT_MODE_ECB, $iv));
  }

  /**
   * Check dependencies for the encryption method.
   *
   * @param string $text
   *   The text to be checked.
   * @param string $key
   *   The key to be checked.
   *
   * @return array
   *   An array of error messages, providing info on missing dependencies.
   */
  public function checkDependencies($text = NULL, $key = NULL) {
    $errors = [];

    if (!function_exists('mcrypt_encrypt')) {
      $errors[] = $this->t('MCrypt library not installed.');
    }

    // Check if we have a 128 bit key.
    if (strlen($key) != 16) {
      $errors[] = $this->t('This encryption method requires a 128 bit key.');
    }

    return $errors;
  }

}
