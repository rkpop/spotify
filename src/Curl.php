<?php
declare(strict_types = 1);

require_once('Env.php');

final class HTTPMethod {
  const GET = 'GET';
  const PUT = 'PUT';
  const POST = 'POST';
  const DELETE = 'DELETE';
}

/**
 * This is just a simple cURL wrapper because I find it way easier to work with
 * than the standard procedural way of doing it.
 *
 * This class employes the Builder Design Pattern.
 * Empty requests are initialized with one of the provided HTTP method convenience
 * functions and then specific request attributes are provided one at a time.
 *
 * Example:
 *
 *  Curl::get('https://youtube.com/watch')
 *    ->addHeader('X-Dummy-Header', 'Dummy Value')
 *    ->addQueryParam('v', 'dQw4w9WgXcQ')
 *    ->exec();
 *
 *  Will execute GET https://youtube.com/watch?v=dQw4w9WgXcQ
 */
final class Curl {

  // Should really abstract this into a URL class but who has time for that.
  private string $url;
  private string $method;
  private bool $json = false;

  private array $postParams = [];
  private array $queryParams = [];
  private array $headers = [];
  private string $body = '';

  private ?string $username = null;
  private ?string $password = null;

  private function __construct(
    string $url,
    string $method = HTTPMethod::GET
  ) {
    $this->url = $url;
    $this->method = $method;
    $this->addHeader('User-Agent', Env::load()->get('UserAgent'));
  }

  /**
   * Convenience function to start a new cURL GET request.
   *
   * @param string  Destination URL
   * @return Curl   New Curl instance
   */
  public static function get(string $url): self {
    return new Curl($url);
  }

  /**
   * Convenience function to start a new cURL POST request.
   *
   * @param string  Destination URL
   * @return Curl   New Curl instance
   */
  public static function post(string $url): self {
    return new Curl($url, HTTPMethod::POST);
  }

  /**
   * Convenience function to start a new cURL PUT request.
   *
   * @param string  Destination URL
   * @return Curl   New Curl instance
   */
  public static function put(string $url): self {
    return new Curl($url, HTTPMethod::PUT);
  }

  /**
   * Convenience function to start a new cURL DELETE request.
   *
   * @param string  Destination URL
   * @return Curl   New Curl instance
   */
  public static function delete(string $url): self {
    return new Curl($url, HTTPMethod::DELETE);
  }

  /**
   * Adds a new HTTP header to the cURL call. Duplicate headers are included
   * as-is; they are not overwritten.
   *
   * @param string  Name of the header e.g. Content-Type
   * @param mixed   Value for the header e.g. application/json
   * @return Curl   Existing Curl instance
   */
  public function addHeader(string $header_name, $header_value): self {
    $this->headers[] = sprintf('%s: %s', $header_name, (string)$header_value);
    return $this;
  }

  /**
   * Adds a new POST param. Duplicate params are subsequently overwritten.
   *
   * @param string  Name of the param e.g. grant_type
   * @param mixed   Value of the param e.g. refresh_token
   * @return Curl   Existing Curl instance
   */
  public function addPostParam(string $param_name, $param_value): self {
    $this->postParams[$param_name] = $param_value;
    return $this;
  }

  /**
   * Adds a new GET query param appended to the URL. Duplicate params are
   * subsequently overwritten.
   *
   * @param string  Name of the param e.g. position
   * @param mixed   Value of the param e.g. 0
   * @return Curl   Existing Curl instance
   */
  public function addQueryParam(string $param_name, $param_value): self {
    $this->queryParams[$param_name] = $param_value;
    return $this;
  }

  /**
   * Conveniene function to add a JSON request body. Will NOT send POST
   * parameters if this function is used.
   *
   * @param string  JSON-encoded request body
   * @return Curl   Existing Curl instance
   */
  public function setJsonBody(string $body): self {
    $this->body = $body;
    $this->json = true;
    return $this;
  }

  /**
   * Conveniene function to authenticate the request using HTTP BASIC
   * authentciation.
   *
   * Subsequent invocations of the method will override prior values.
   *
   * @param string  username
   * @param string  password
   * @return Curl   Existing Curl instance
   */
  public function setAuth(string $username, string $password): self {
    $this->username = $username;
    $this->password = $password;
    return $this;
  }

  /**
   * Executes the cURL request that has been built out.
   *
   * @return array<mixed>   Request response JSON-decoded
   *
   * @throws Exception  if the cURL request fails to initiate (system error)
   * @throws Exception  if a non-2XX status code is returned
   */
  public function exec(): array {
    if (empty($this->queryParams)) {
      $request_uri = $this->url;
    } else {
      $request_uri = sprintf(
        '%s?%s',
        $this->url,
        http_build_query($this->queryParams)
      );
    }

    $ch = curl_init($request_uri);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
    if (
      $this->method === HTTPMethod::POST
      || $this->method === HTTPMethod::PUT
    ) {
      if ($this->json) {
        $value = $this->body;
      } else {
        $value = http_build_query($this->postParams);
      }
      curl_setopt($ch, CURLOPT_POSTFIELDS, $value);
    }

    if ($this->username !== null) {
      curl_setopt(
        $ch,
        CURLOPT_USERPWD,
        $this->username . ":" . $this->password,
      );
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    if ($response === false) {
      throw new Exception('Unable to make request.');
    }
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (intdiv($http_status, 100) !== 2) {
      throw new Exception(sprintf(
        '[%d] %s',
        $http_status,
        $response,
      ));
    }

    return json_decode($response, true /* associative array */);
  }

}
