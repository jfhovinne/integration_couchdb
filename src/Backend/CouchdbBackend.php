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
 * @package Drupal\integration\Backend
 */
class CouchdbBackend extends AbstractBackend {

  protected $client;
  protected $cookies;
  protected $limit = 1000;

  public function setClient(GuzzleClient $client) {
    $this->client = $client;
    return $this;
  }

  public function getClient() {
    if (!$this->client) {
      $configuration = $this->getConfiguration();
      $headers = ['Content-Type' => $this->getFormatterHandler()->getContentType()];
      switch ($configuration->authentication) {
        case 'http_authentication':
          // Use basic auth and add username/password in the request headers.
          $username = $configuration->getComponentSetting('authentication_handler', 'username');
          $password = $configuration->getComponentSetting('authentication_handler', 'password');
          $headers['auth'] = ["$username", "$password"];
        break;
        case 'cookie_authentication':
          // Use cookie authentication, see CookieAuthentication class.
          $authentication = $this->getAuthenticationHandler();
          if ($authentication->authenticate()) {
            $context = $authentication->getContext();
            $this->cookies = $context['cookies'];
          } else {
            // @todo: Could not authenticate; handle this.
          }
        break;
      }
      $this->client = new GuzzleClient([
        'headers' => $headers,
        'cookies' => $this->cookies,
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
          $result = $this->getResponseData($response);
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

    if ($id = $this->getBackendContentId($document)) {
      return $this->update($resource_schema, $document);
    }

    try {
      $response = $this->getClient()->request('POST', $uri, [
        'body' => $this->getFormatterHandler()->encode($document),
      ]);
      if ($response->getStatusCode() === 201) {
        $data = $this->getResponseData($response);
        $doc = new \stdClass();
        $doc->_id = $data->id;
        $doc->_rev = $data->rev;
        return new Document($doc);
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
        return new Document($this->getResponseData($response));
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
    $this->validateResourceSchema($resource_schema);

    // Be sure we use the ID coming from the backend, not from the document.
    $document->deleteMetadata('_id');
    $id = $this->getBackendContentId($document);
    // @todo: throw an exception?
    if (!$doc = $this->read($resource_schema, $id)) {
      return FALSE;
    }
    $uri = $this->getResourceUri($resource_schema) . "/$id";
    try {
      $response = $this->getClient()->request('PUT', $uri, [
        'body' => $this->getFormatterHandler()->encode($document),
      ]);
      if ($response->getStatusCode() === 201) {
        $data = $this->getResponseData($response);
        $doc = new \stdClass();
        $doc->_id = $data->id;
        $doc->_rev = $data->rev;
        return new Document($doc);
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
  public function delete($resource_schema, $id) {
    $this->validateResourceSchema($resource_schema);

    if (!$doc = $this->read($resource_schema, $id)) {
      return FALSE;
    }
    try {
      $uri = $this->getResourceUri($resource_schema)
        . "/$id?rev=" . $doc->getMetaData('_rev');
      $response = $this->getClient()->request('DELETE', $uri);
      if ($response->getStatusCode() === 200) {
        return TRUE;
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
  public function getBackendContentId(DocumentInterface $document) {
    $producer = $document->getMetadata('producer');
    $producer_content_id = $document->getMetadata('producer_content_id');
    if ($producer && $producer_content_id) {
      $base_url = $this->getConfiguration()->getPluginSetting('backend.base_url');
      // @todo: would be good to be able to use tokens within backend.id_endpoint value,
      // e.g. /getid/{producer}/{entity_id}
      $id_endpoint = $this->getConfiguration()->getPluginSetting('backend.id_endpoint');
      $uri = $base_url . $id_endpoint . '/' . $producer . '/' . $producer_content_id;
      try {
        $response = $this->getClient()->request('GET', $uri);
        if ($response->getStatusCode() === 200) {
          $result = $this->getResponseData($response);
          // @todo: Should we return the first or last item, or throw an exception
          // if more than 1 item?
          if (isset($result->rows[0])) {
            return $result->rows[0]->id;
          } else {
            return FALSE;
          }
        } else {
          return FALSE;
        }
      } catch (\GuzzleHttp\Exception\RequestException $e) {
        return FALSE;
      }
    }
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
    return $base_url . $endpoint;
  }

  /**
   * Get response data, decoded by the formatter.
   *
   * @param GuzzleHttp\Psr7\Response $response
   *    A response returned by a request.
   *
   * @return mixed
   *    Decoded response body.
   */
  protected function getResponseData($response) {
    $body = (string) $response->getBody();
    return $this->getFormatterHandler()->decode($body);
  }

}
