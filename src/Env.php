<?php
declare(strict_types = 1);

final class Env {

  const ENV_FILE = __DIR__.'/../.env.ini';

  static ?self $instance = null;
  private array $config = [];

  private function __construct() {
    $this->parse();
  }

  public static function load(): self {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function get(string $key_name) {
    if (!array_key_exists($key_name, $this->config)) {
      throw new Exception("Could not find config value with name '$key_name'");
    }
    return $this->config[$key_name];
  }

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

  private function write(string $ini_contents) {
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

  private function parse(): void {
    $this->config = parse_ini_file(self::ENV_FILE);
  }
}