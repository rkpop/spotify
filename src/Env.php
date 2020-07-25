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
    $this->parse();
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

  /**
   * The Spotify Access Token actually changes every time we run this script.
   * So we actually need a way to write back to our env file. This method allows
   * you to set a new value for the designated key.
   *
   * Does this by parsing in the full INI file (including section headers),
   * setting the new value, and the re-building the INI file structure using
   * string concatenation. It's a little janky, but it works!
   *
   * @param string  Name of the key
   * @param mixed   Value to set for the given key
   *
   * @throws Exception  If the given key name is not in the config file
   */
  public function set(string $key_name, $new_value): void {
    if (!array_key_exists($key_name, $this->config)) {
      throw new Exception("Could not find config value with name '$key'");
    }

    $full_ini_file = parse_ini_file(self::ENV_FILE, true /* process sections */);
    foreach ($full_ini_file as $section => $configs) {
      foreach ($configs as $key => $value) {
        if ($key === $key_name) {
          $full_ini_file[$section][$key_name] = $new_value;
        }
      }
    }

    $ini_as_string = '';
    foreach ($full_ini_file as $section => $configs) {
      $ini_as_string .= "[$section]\n";
      foreach ($configs as $key => $value) {
        $ini_as_string .= "$key = '$value'\n";
      }
      $ini_as_string .= "\n";
    }

    $this->write(trim($ini_as_string));
    $this->parse();
  }

  /**
   * Private function to actually write the new INI value to the file.
   * Tries to grab a lock on the file (*just in case*) and then writes the
   * new value.
   *
   * @param string  The new, properly-formatted INI file contents
   */
  private function write(string $ini_contents): void {
    if ($file_handle = fopen(self::ENV_FILE, 'w')) {
      $start_time = microtime(true /* get as float */);
      $can_write = false;
      do {            
        $can_write = flock($file_handle, LOCK_EX);
        // If lock not obtained sleep for 0 - 100 milliseconds, 
        // to avoid collision and CPU load
        if (!$can_write) {
          usleep(round(rand(0, 100) * 1000));
        }
      } while (!$can_write && microtime(true /* as float */) - $start_time < 5);

      // File was locked so now we can store information
      if ($can_write) {            
        fwrite($file_handle, $ini_contents);
        flock($file_handle, LOCK_UN);
      }
      fclose($file_handle);
    }
  }

  /**
   * Super function to just parse and set the current value of the INI file.
   * We do this on initialization and after any time we change a key value.
   */
  private function parse(): void {
    $this->config = parse_ini_file(self::ENV_FILE);
  }
}