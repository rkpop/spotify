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

  private function __construct() {}

  private static function to(string $url): self {
    $request = new self();
    $request->method = HTTPMethod::GET;
    $request->url = $url;
    $request->addHeader('User-Agent', Env::load()->get('UserAgent'));
    return $request;
  }

  public static function get(string $url): self {
    return self::to($url);
  }

  public static function post(string $url): self {
    $request = self::to($url);
    $request->method = HTTPMethod::POST;
    return $request;
  }

  public static function put(string $url): self {
    $request = self::to($url);
    $request->method = HTTPMethod::PUT;
    return $request;
  }

  public function addHeader(string $header_name, $header_value): self {
    $this->headers[] = sprintf('%s: %s', $header_name, (string)$header_value);
    return $this;
  }

  public function addPostParam(string $param_name, $param_value): self {
    $this->postParams[$param_name] = $param_value;
    return $this;
  }

  public function addQueryParam(string $param_name, $param_value): self {
    $this->queryParams[$param_name] = $param_value;
    return $this;
  }

  public function setJsonBody(string $body): self {
    $this->body = $body;
    $this->json = true;
    return $this;
  }

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