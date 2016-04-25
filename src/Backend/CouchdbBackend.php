<?php

/**
 * @file
 * Contains \Drupal\integration_couchdb\Backend\CouchdbBackend.
 */

namespace Drupal\integration_couchdb\Backend;

use Drupal\integration\Backend\AbstractBackend;
use Drupal\integration\Document\Document;
use Drupal\integration\Document\DocumentInterface;
use GuzzleHttp\Client as GuzzleClient;

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

  protected $client;
  protected $limit = 1000;

  public function setClient(GuzzleClient $client) {
    $this->client = $client;
    return $this;
  }

  public function getClient() {
    if (!$this->client) {
      $this->client = new GuzzleClient([
        'headers' => [
          'content-type' => $this->getFormatterHandler()->getContentType(),
        ],
      ]);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function find($resource_schema, $args = []) {
    $this->validateResourceSchema($resource_schema);

    $out = [];
    if (isset($args['id']) && $this->read($resource_schema, $args['id'])) {
      $out = [$args['id']];
    } else {
      try {
        $limit = isset($args['limit']) ? (int) $args['limit'] : $this->limit;
        // @todo: Make this uri configurable.
        $uri = $this->getResourceUri($resource_schema) . "/_all_docs?limit=$limit";
        $response = $this->getClient()->request('GET', $uri);
        if ($response->getStatusCode() === 200) {
          $body = (string) $response->getBody();
          $result = $this->getFormatterHandler()->decode($body);
          foreach($result->rows as $item) {
            $out[] = $item->id;
          }
        } else {
          // @todo: Handle this.
        }
      } catch (\GuzzleHttp\Exception\RequestException $e) {
        // @todo: Handle this.
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function create($resource_schema, DocumentInterface $document) {
    $this->validateResourceSchema($resource_schema);

    $uri = $this->getResourceUri($resource_schema);
    $document->deleteMetadata('_id');
    try {
      $response = $this->getClient()->request('POST', $uri, [
        'body' => $this->getFormatterHandler()->encode($document),
      ]);
      if ($response->getStatusCode() === 201) {
        $body = (string) $response->getBody();
        return new Document($this->getFormatterHandler()->decode($body));
      } else {
        return FALSE;
      }
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function read($resource_schema, $id) {
    $this->validateResourceSchema($resource_schema);

    try {
      $uri = $this->getResourceUri($resource_schema) . "/$id";
      $response = $this->getClient()->request('GET', $uri);
      if ($response->getStatusCode() === 200) {
        $body = (string) $response->getBody();
        $result = $this->getFormatterHandler()->decode($body);
        return new Document($this->getFormatterHandler()->decode($body));
      } else {
        return FALSE;
      }
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      return FALSE;
    }
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
    try {
      $response = $this->getClient()->request('GET', $base_url);
      return $response->getStatusCode() === 200;
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      return FALSE;
    }
  }

  /**
   * Get full, single resource URI.
   *
   * @param string $resource_schema
   *    Machine name of a resource schema configuration object.
   *
   * @return string
   *    Single resource URI.
   */
  protected function getResourceUri($resource_schema) {
    $base_url = $this->getConfiguration()->getPluginSetting('backend.base_url');
    $endpoint = $this->getConfiguration()->getResourceEndpoint($resource_schema);
    return "$base_url/$endpoint";
  }

}
