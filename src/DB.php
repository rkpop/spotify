<?php
declare(strict_types = 1);

final class DB {

  const DB_PATH = __DIR__.'/../spotify.db';

  private static ?self $instance = null;
  private $db;

  private function __construct() {
    $this->db = new SQLite3(self::DB_PATH);
  }

  public static function connect(): self {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function addPlaylist(
    string $spotify_id,
    string $name,
    string $month,
    int $year
  ): void {
    $stm = $this->db->prepare(
      'INSERT INTO 
        playlists
      VALUES (
        :spotify_id, 
        :name, 
        :month, 
        :year
      )'
    );
    $stm->bindValue(':spotify_id', $spotify_id);
    $stm->bindValue(':name', $name);
    $stm->bindValue(':month', $month);
    $stm->bindValue(':year', $year);
    $stm->execute();
  }

  public function getPlaylistID(string $month, int $year): ?string {
    $stm = $this->db->prepare(
      'SELECT 
        `spotify_id` 
      FROM 
        playlists 
      WHERE 
        `month` = ? 
        AND `year` = ?'
    );
    $stm->bindValue(1, $month);
    $stm->bindValue(2, $year);
    $result = $stm->execute();

    $rows = $result->fetchArray(SQLITE3_ASSOC);
    if ($rows === false) {
      return null;
    }
    return $rows['spotify_id'];
  }

  public function isProcessed(string $uri): bool {
    $stm = $this->db->prepare('SELECT 1 FROM processed WHERE uri = ?');
    $stm->bindValue(1, $uri);
    $result = $stm->execute();
    return $result->fetchArray() !== false;
  }

  public function markAsProcessed(string $uri): void {
    $stm = $this->db->prepare('INSERT INTO processed VALUES (?)');
    $stm->bindValue(1, $uri);
    $stm->execute();
  }

}