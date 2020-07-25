<?php
declare(strict_types = 1);

/**
 * Utils class for interacting with the SQLite DB. Provides a grab bag of
 * methods that handle actually making the queries against the DB and handling
 * responses.
 *
 * Meant to be very high-level and easy to interface with.
 */
final class DB {

  const DB_PATH = __DIR__.'/../spotify.db';

  private static ?self $instance = null;
  private $db;

  private function __construct() {
    $this->db = new SQLite3(self::DB_PATH);
  }

  /**
   * DB class is a singleton so there is only ever one connection to the database.
   * This is just a conveience so we don't need to repeatedly open and close
   * the connection over the course of the script run.
   *
   * Since this script is the only thing accessing this DB, we don't need to
   * worry about releasing the connection.
   *
   * So, if this is the first time connecting to the DB, we open a connection.
   * Otherwise, we return the existing one.
   *
   * @return DB The single existing DB instance
   */
  public static function connect(): self {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Add a newly-created Spotify playlist to the database. This is mostly to
   * keep track of the Spotify ID since that's what we need to interface with
   * the web API.
   *
   * @param string  Spotify ID for the playlist
   * @param string  Name of the playlist
   * @param month   Full name of the month e.g. July
   * @param int     Full year e.g. 2020
   */
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

  /**
   * Fetch the Spotify ID of the playlist for the given month and year.
   *
   * @param string    Full name of the month e.g. July
   * @param int       Full year e.g. 2020
   * @return ?string  Spotify Playlist ID or null if not found
   */
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

  /**
   * Identifies whether or not the given release URL has already been processed
   * by our script.
   *
   * @param string  URL of the release
   * @return bool   Whether or not we have already processed that release
   */
  public function isProcessed(string $uri): bool {
    $stm = $this->db->prepare('SELECT 1 FROM processed WHERE uri = ?');
    $stm->bindValue(1, $uri);
    $result = $stm->execute();
    return $result->fetchArray() !== false;
  }

  /**
   * Marks a given release URL as processed. We do this after adding it to all
   * the relevant playlists so that the next time we pull down the full release
   * list from Reddit we can skip over it.
   *
   * @param string  URL of the release
   */
  public function markAsProcessed(string $uri): void {
    $stm = $this->db->prepare('INSERT INTO processed VALUES (?)');
    $stm->bindValue(1, $uri);
    $stm->execute();
  }

}