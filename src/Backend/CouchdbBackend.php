<?php

namespace Drupal\integration_couchdb\Backend;

use Drupal\integration\Backend\AbstractBackend;
use Drupal\integration\Document\Document;
use Drupal\integration\Document\DocumentInterface;
use Drupal\integration\Exceptions\BackendException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;

/**
 * Class CouchdbBackend.
 *
 * Simple REST CouchDB backend using Guzzle.
 *
 * @package Drupal\integration\Backend
 */
class CouchdbBackend extends AbstractBackend {

  /**
   * HTTP client.
   *
   * @var GuzzleHttp\Client
   */
  protected $client;

  /**
   * Cookie jar that stores cookies.
   *
   * @var GuzzleHttp\Cookie\CookieJar
   */
  protected $cookies;

  /**
   * Default find method limit.
   *
   * @var int
   */
  protected $limit = 1000;

  /**
   * Set the HTTP client.
   *
   * @param GuzzleHttp\Client $client
   *    The HTTP client.
   *
   * @return CouchdbBackend
   *    Set client and return backend instance.
   */
  public function setClient(GuzzleClient $client) {
    $this->client = $client;
    return $this;
  }

  /**
   * Get HTTP client.
   *
   * @return GuzzleHttp\Client
   *    The HTTP client.
   */
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
          }
          else {
            throw new BackendException();
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
    }
    else {
      try {
        $limit = isset($args['limit']) ? (int) $args['limit'] : $this->limit;
        $endpoint = $this->getConfiguration()
          ->getPluginSetting("resource_schema.$resource_schema.all_docs_endpoint");
        if ($endpoint) {
          // Custom endpoint.
          $uri = $this->getConfiguration()->getPluginSetting('backend.base_url') . $endpoint;
        }
        else {
          // Default endpoint.
          $uri = $this->getResourceUri($resource_schema) . "/_all_docs?limit=$limit";
        }

        // Active debug Mode
        $params = [];
        if (variable_get('integration_debug', FALSE)) {
          $params['debug'] = true;
        }
        $response = $this->getClient()->request('GET', $uri, $params);

        if ($response->getStatusCode() === 200) {
          $result = $this->getResponseData($response);
          foreach ($result->rows as $item) {
            $out[] = $item->id;
          }
        }
        else {
          throw new BackendException();
        }
      }
      catch (RequestException $e) {
        throw new BackendException($e->getMessage());
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

    if ($this->getBackendContentId($document)) {
      return $this->update($resource_schema, $document);
    }

    try {
      $params = [
        'body' => $this->getFormatterHandler()->encode($document),
      ];
      // Active debug Mode
      if (variable_get('integration_debug', FALSE)) {
        $params['debug'] = true;
      }
      $response = $this->getClient()->request('POST', $uri, $params);
      if ($response->getStatusCode() === 201) {
        $data = $this->getResponseData($response);
        $doc = new \stdClass();
        $doc->_id = isset($data->id) ? $data->id : NULL;
        $doc->_rev = isset($data->rev) ? $data->rev : NULL;
        return new Document($doc);
      }
      else {
        throw new BackendException();
      }
    }
    catch (RequestException $e) {
      throw new BackendException($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function read($resource_schema, $id) {
    $this->validateResourceSchema($resource_schema);

    try {
      $uri = $this->getResourceUri($resource_schema) . "/$id";
      $params = [];
      // Active debug Mode
      if (variable_get('integration_debug', FALSE)) {
        $params['debug'] = true;
      }
      $response = $this->getClient()->request('GET', $uri, $params);
      if ($response->getStatusCode() === 200) {
        return new Document($this->getResponseData($response));
      }
      else {
        throw new BackendException();
      }
    }
    catch (RequestException $e) {
      throw new BackendException($e->getMessage());
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
      $params = [
        'body' => $this->getFormatterHandler()->encode($document),
      ];
      // Active debug Mode
      if (variable_get('integration_debug', FALSE)) {
        $params['debug'] = true;
      }
      $response = $this->getClient()->request('PUT', $uri, $params);
      if ($response->getStatusCode() === 201) {
        $data = $this->getResponseData($response);
        $doc = new \stdClass();
        $doc->_id = isset($data->id) ? $data->id : NULL;
        $doc->_rev = isset($data->rev) ? $data->rev : NULL;
        return new Document($doc);
      }
      else {
        throw new BackendException();
      }
    }
    catch (RequestException $e) {
      throw new BackendException($e->getMessage());
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
      $params = [];
      // Active debug Mode
      if (variable_get('integration_debug', FALSE)) {
        $params['debug'] = true;
      }
      $response = $this->getClient()->request('DELETE', $uri, $params);
      if ($response->getStatusCode() === 200) {
        return TRUE;
      }
      else {
        throw new BackendException();
      }
    }
    catch (RequestException $e) {
      throw new BackendException($e->getMessage());
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
        $params = [];
        // Active debug Mode
        if (variable_get('integration_debug', FALSE)) {
          $params['debug'] = true;
        }
        $response = $this->getClient()->request('GET', $uri, $params);
        if ($response->getStatusCode() === 200) {
          $result = $this->getResponseData($response);
          // @todo: Should we return the first or last item, or throw an exception
          // if more than 1 item?
          if (isset($result->rows[0])) {
            return $result->rows[0]->id;
          }
          else {
            return FALSE;
          }
        }
        else {
          throw new BackendException();
        }
      }
      catch (RequestException $e) {
        throw new BackendException($e->getMessage());
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
      $params = [];
      // Active debug Mode
      if (variable_get('integration_debug', FALSE)) {
        $params['debug'] = true;
      }
      $response = $this->getClient()->request('GET', $base_url, $params);
      return $response->getStatusCode() === 200;
    }
    catch (RequestException $e) {
      throw new BackendException($e->getMessage());
    }
  }

  /**
   * Get the list of changes.
   *
   * @param string $resource_schema
   *    Machine name of a resource schema configuration object.
   *
   * @return \stdClass
   *    Object containing the list of changes and last change sequence number.
   */
  public function getChanges($resource_schema) {
    $uri = $this->getChangesUri($resource_schema);
    try {
      $params = [];
      // Active debug Mode
      if (variable_get('integration_debug', FALSE)) {
        $params['debug'] = true;
      }
      $response = $this->getClient()->request('GET', $uri, $params);
      if ($response->getStatusCode() === 200) {
        return $this->getResponseData($response);
      }else{
        throw new BackendException($this->getResponseData($response));
      }
    }
    catch (RequestException $e) {
      throw new BackendException($e->getMessage());
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
    $endpoint = $this->getConfiguration()
      ->getPluginSetting("resource_schema.$resource_schema.endpoint");
    return $base_url . $endpoint;
  }

  /**
   * Get resource changes URI.
   *
   * @param string $resource_schema
   *    Machine name of a resource schema configuration object.
   *
   * @return string
   *    Resource changes URI.
   */
  protected function getChangesUri($resource_schema) {
    $base_url = $this->getConfiguration()->getPluginSetting('backend.base_url');
    $endpoint = $this->getConfiguration()
      ->getPluginSetting("resource_schema.$resource_schema.changes_endpoint");
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
  protected function getResponseData(Response $response) {
    $body = (string) $response->getBody();
    return $this->getFormatterHandler()->decode($body);
  }

}
