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

  public function TestClient() {
    $client = new \GuzzleHttp\Client();
    $res = $client->request('GET', 'http://localhost:5984/');
    return $res->getStatusCode();
  }

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

}
