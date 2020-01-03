<?php

namespace Drupal\tfa\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush commandfile.
 */
class TfaCommands extends DrushCommands implements SanitizePluginInterface {

  /**
   * Run your sanitization logic using standard Drupal APIs.
   *
   * @param $result Exit code from the main operation for sql-sanitize.
   * @param $commandData Information about the current request.
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    // DBTNG does not support expressions in delete queries.
    $sql = "DELETE FROM users_data WHERE LEFT(name, 4) = 'tfa_'";
    db_query($sql);
    $this->logger()->success('Removed recovery codes and other user-specific TFA data.');
  }

  /**
   * @hook on-event sql-sanitize-confirms
   *
   * @param $messages An array of messages to show during confirmation.
   * @param $input The effective commandline input for this request.
   *
   * @return array.
   *   A messages array.
   */
  public function messages(&$messages, InputInterface $input) {
    return $messages[] = dt('Remove recovery codes and other user-specific TFA data.');
  }

}
