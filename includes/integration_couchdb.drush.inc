<?php

/**
 * @file
 * Contains Drush-related hook implementations.
 */

/**
 * Implements hook_drush_command().
 */
function integration_couchdb_drush_command() {
  $items = array();
  $items['integration-couchdb-test-connection'] = array(
    'description' => 'Test CouchDB backend connection.',
    'aliases' => array('ict'),
    'arguments' => [
      'backend' => 'The backend machine name.',
    ],
  );
  return $items;
}

/**
 * Callback for the integration-couchdb-test-connection command.
 */
function drush_integration_couchdb_test_connection($backend_name) {
  if (!$backend_name) {
    return drush_log(dt('Please specify a backend as an argument.'), 'error');
  }
  $backend = \Drupal\integration\Backend\BackendFactory::getInstance($backend_name);
  if ($backend->isAlive()) {
    drush_log(dt('The CouchDB backend at !url is alive.',
      ['!url' => $backend->getConfiguration()->getPluginSetting('backend.base_url')]), 'success');
  } else {
    drush_log(dt('The CouchDB backend at !url could not be contacted.',
      ['!url' => $backend->getConfiguration()->getPluginSetting('backend.base_url')]), 'error');
  }
}
