<?php

/**
 * @file
 * Contains \Drupal\integration_couchdb\Backend\CouchdbBackend.
 */

namespace Drupal\integration_couchdb\Backend;

use Drupal\integration\Backend\AbstractBackend;
use Drupal\integration\Document\Document;
use Drupal\integration\Document\DocumentInterface;

/**
 * Class CouchdbBackend.
 *
 * Simple REST CouchDB backend using Guzzle.
 *
 * @method BackendConfiguration getConfiguration()
 *
 * @package Drupal\integration\Backend
 */
class CouchdbBackend extends AbstractBackend {

  /**
   * {@inheritdoc}
   */
  public function find($resource_schema, $args = []) {

  }

  /**
   * {@inheritdoc}
   */
  public function create($resource_schema, DocumentInterface $document) {

  }

  /**
   * {@inheritdoc}
   */
  public function read($resource_schema, $id) {

  }

  /**
   * {@inheritdoc}
   */
  public function update($resource_schema, DocumentInterface $document) {

  }

  /**
   * {@inheritdoc}
   */
  public function delete($resource_schema, $id) {

  }

  /**
   * {@inheritdoc}
   */
  public function getBackendContentId(DocumentInterface $document) {

  }

  /**
   * Check whether the CouchDB backend can be contacted or not.
   *
   * @return bool
   *    TRUE if contactable, FALSE otherwise.
   */
  public function isAlive() {
    $base_url = $this->getConfiguration()->getPluginSetting('backend.base_url');
    $client = new \GuzzleHttp\Client();
    $res = $client->request('GET', $base_url);
    return $res->getStatusCode() === 200;
  }

}
