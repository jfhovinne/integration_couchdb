<?php

/**
 * @file
 * Contains \Drupal\integration_couchdb\FormHandlers\Backend\CouchdbBackendFormHandler.
 */

namespace Drupal\integration_couchdb\FormHandlers\Backend;

use Drupal\integration_ui\FormHandlers\Backend\AbstractBackendFormHandler;
use Drupal\integration_ui\FormHelper;

/**
 * Class CouchdbBackendFormHandler.
 *
 * @method BackendConfiguration getConfiguration(array &$form_state)
 *
 * @package Drupal\integration_ui\FormHandlers\Backend
 */
class CouchdbBackendFormHandler extends AbstractBackendFormHandler {

  /**
   * {@inheritdoc}
   */
  public function resourceSchemaForm($machine_name, array &$form, array &$form_state, $op) {
    $configuration = $this->getConfiguration($form_state);
    $form['endpoint'] = FormHelper::textField(t('Resource endpoint'), $configuration->getResourceEndpoint($machine_name));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array &$form, array &$form_state, $op) {
    $configuration = $this->getConfiguration($form_state);
    $form['base_url'] = FormHelper::textField(t('Base URL'), $configuration->getPluginSetting('backend.base_url'));
    $form['id_endpoint'] = FormHelper::textField(t('ID endpoint'), $configuration->getPluginSetting('backend.id_endpoint'));
  }

  /**
   * {@inheritdoc}
   */
  public function formSubmit(array $form, array &$form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function formValidate(array $form, array &$form_state) {

  }

}
