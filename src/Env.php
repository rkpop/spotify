<?php
declare(strict_types = 1);

/**
 * Simple wrapper class for interfacing with the .env file that stores all of
 * the script's configuration values e.g. Spotify access token, API URLs, etc.
 */
final class Env {

  const ENV_FILE = __DIR__.'/../.env.ini';

  static ?self $instance = null;
  private array $config = [];

  private function __construct() {
    $this->config = parse_ini_file(self::ENV_FILE);
  }

  /**
   * This is implemented as a singleton because there's really no need to
   * open and parse the ini file multiple times over the run of the script.
   * We only need to do it once. So, singleton it is!
   *
   * @return Env  Instance of the Env class
   */
  public static function load(): self {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Retrieve the value for the specified key from the .env file.
   *
   * @param string  Name of the key to fetch the value for e.g. AccessToken
   * @return mixed  The value for that key
   *
   * @throws Exception  If the given key name is not in the config file
   */
  public function get(string $key_name) {
    if (!array_key_exists($key_name, $this->config)) {
      throw new Exception("Could not find config value with name '$key_name'");
    }
    return $this->config[$key_name];
  }
}
