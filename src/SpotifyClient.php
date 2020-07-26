<?php
declare(strict_types = 1);

require_once('Curl.php');
require_once('DB.php');
require_once('Env.php');

/**
 * Utils class for interacting with Spotify. Provides a grab bag of methods
 * that handle Spotify authentication, authorization, making the actual HTTP
 * requests, etc.
 *
 * Meant to be very high-level and easy to interface with to keep the main
 * script runner as simple as possible.
 */
final class SpotifyClient {

  /**
   * Spotify tokens only last for one hour. Needless to say we will need to
   * refresh this token often. This function is what does that.
   * Grabs the refresh token Spotify gave us and gets a new access token.
   * It then writes the new access token back to our Env file.
   */
  public static function refreshAccessToken(): void {
    $uri = Env::load()->get('AuthURI').'/token';
    $response = Curl::post($uri)
      ->addPostParam('grant_type', 'refresh_token')
      ->addPostParam('refresh_token', Env::load()->get('RefreshToken'))
      ->addHeader('Authorization', self::getBasicHeader())
      ->exec();

    $new_access_token = $response['access_token'];
    Env::load()->set('AccessToken', $new_access_token);

    if (array_key_exists('refresh_token', $response)) {
      $new_refresh_token = $response['refresh_token'];
      Env::load()->set('RefreshToken', $new_refresh_token);
    }
  }

  /**
   * Checks the database to see if we have a playlist ID saved for the
   * "Current" playlist. "Current" playlist is an alias for the releases
   * of the current month we're in, rather than a playlist for the explicit
   * month e.g. July, 2020.
   * If we don't have a Current playlist, it creates one.
   *
   * @return string The Spotify ID of the Current playlist
   */
  public static function createOrFetchCurrentPlaylist(): string {
    // Magic values for a playlist that is perpetually set to the
    // "current" month. Will change over automatically at the start of the
    // next month.
    $playlist_id = DB::connect()->getPlaylistID('Current', 0);
    if ($playlist_id !== null) {
      return $playlist_id;
    }

    $name = 'Current Month\'s Releases';
    $description =
      'Auto-updating playlist of the current month\'s K-Pop Releases. '.
      'At the end of the month, it will be emptied out so the next month\'s '.
      'releases can start being added.';
    $body = [
      'name' => $name,
      'public' => true,
      'description' => $description,
    ];
    $uri = Env::load()->get('APIURI').'/me/playlists';
    $response = Curl::post($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->setJsonBody(json_encode($body))
      ->exec();

    $spotify_id = $response['id'];

    DB::connect()->addPlaylist(
      $spotify_id,
      $name,
      'Current',
      0,
    );
    return $spotify_id;
  }

  /**
   * At the start of a new month, the "Current" playlist needs to start fresh.
   * This method clears out all the songs in the "Current" playlist.
   *
   * @throws Exception Throws if we don't have a "Current" playlist registered
   */
  public static function clearOutCurrentPlaylist(): void {
    $playlist_id = DB::connect()->getPlaylistID('Current', 0);
    if ($playlist_id === null) {
      throw new Exception('Could not find Current playlist in database');
    }

    $body = [
      'uris' => [],
    ];
    $uri = Env::load()->get('APIURI')."/playlists/$playlist_id/tracks";
    Curl::put($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->addHeader('Content-Type', 'application/json')
      ->setJsonBody(json_encode($body))
      ->exec();
  }

  /**
   * Since per-month playlists can be a bit thrashy given how frequently they
   * change, probably a good idea to also have playlists that cover the entire
   * year!
   *
   * @param int     Full year e.g. 2020
   * @return string The Spotify ID of the playlist
   */
  public static function createOrFetchYearPlaylist(
    int $year
  ): string {
    // We use the magic value of 'All' for the 'Month' since the year is the
    // one we really care about here.
    $playlist_id = DB::connect()->getPlaylistID('All', $year);
    if ($playlist_id !== null) {
      return $playlist_id;
    }

    $name = "$year Releases";
    $description = sprintf(
      'Auto-updating playlist of K-Pop Releases over the entire year of %d',
      $year,
    );
    $body = [
      'name' => $name,
      'public' => true,
      'description' => $description,
    ];
    $uri = Env::load()->get('APIURI').'/me/playlists';
    $response = Curl::post($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->setJsonBody(json_encode($body))
      ->exec();

    $spotify_id = $response['id'];

    DB::connect()->addPlaylist(
      $spotify_id,
      $name,
      'All',
      $year,
    );
    return $spotify_id;
  }

  /**
   * Release playlists are per-month. So in order to reference a specific playlist,
   * all we need to provide is the month and year. This method checks to see if we
   * have a playlist saved for that particular month+year.
   * If we do not, it creates a new one.
   *
   * @param string  The full name of the month e.g. July
   * @param int     The current year e.g. 2020
   *
   * @return string The Spotify ID of the playlist
   */
  public static function createOrFetchMonthPlaylist(
    string $month,
    int $year
  ): string {
    $playlist_id = DB::connect()->getPlaylistID($month, $year);
    if ($playlist_id !== null) {
      return $playlist_id;
    }

    $name = "$month $year Releases";
    $description = sprintf(
      'Auto-updating playlist of the %s %d K-Pop Releases Wiki: %s',
      $month,
      $year,
      Env::load()->get('ReleasesLink')."/$year/$month",
    );
    $body = [
      'name' => $name,
      'public' => true,
      'description' => $description,
    ];
    $uri = Env::load()->get('APIURI').'/me/playlists';
    $response = Curl::post($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->setJsonBody(json_encode($body))
      ->exec();

    $spotify_id = $response['id'];

    DB::connect()->addPlaylist(
      $spotify_id,
      $name,
      $month,
      $year,
    );
    return $spotify_id;
  }

  /**
   * For a given release (track or album), add it to the given playlist.
   * By default it will be inserted at the top of the playlist so the playlist
   * is sorted with the most-recent releases on top of the playlist.
   * It distinguishes between track links and ablum links by checking the
   * URL structure (albums and tracks have different URL formats).
   *
   * @param string  The Spotify web URL of the given release (album or track)
   * @param string  The Spotify Playlist ID the release should be added to
   *
   * @throws Exception  If we can't understand the format of the provided URL
   */
  public static function addReleaseToPlaylist(
    string $release_url,
    string $playlist_id
  ): void {
    preg_match('/.*\/album\/([A-z0-9]+)/', $release_url, $matches);
    if (count($matches) > 1) {
      self::addAlbumToPlaylist($release_url, $playlist_id);
      return;
    }

    preg_match('/.*\/track\/([A-z0-9]+)/', $release_url, $matches);
    if (count($matches) > 1) {
      $track_id = $matches[1];
      $track_uri = self::getTrackURI($track_id);
      self::addTracksToPlaylist([$track_uri], $playlist_id);
      return;
    }

    throw new Exception("Could not parse release URL: $release_url");
  }

  /**
   * Takes the given album URl, fetches all of the individual tracks on the album,
   * and then adds those tracks to the given playlist. Tracks are added in the
   * same order they are on the album.
   *
   * @param string  The Spotify web URL of the album
   * @param string  The Spotify Playlist ID the album tracks should be added to
   *
   * @throws Exception  If we cannot extract the Album ID from the provided URL
   */
  private static function addAlbumToPlaylist(
    string $album_url,
    string $playlist_id
  ): void {
    preg_match('/.*\/album\/([A-z0-9]+)/', $album_url, $matches);
    if (count($matches) < 2) {
      throw new Exception('Could not extract album ID from URL: '.$album_url);
    }
    $album_id = $matches[1];

    $uri = Env::load()->get('APIURI')."/albums/$album_id/tracks";
    $response = Curl::get($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->exec();

    $track_uris = [];
    foreach ($response['items'] as $track) {
      $track_uris[] = $track['uri'];
    }

    self::addTracksToPlaylist($track_uris, $playlist_id);
  }

  /**
   * Takes a provided list of Spotify Track URIs and adds them to the given
   * playlist. Standard operation in the Spotify API.
   *
   * @param array<string> Spotify Track URIs (special format) to be added
   * @param string        Spotify Playlist ID that is being added to
   */
  private static function addTracksToPlaylist(
    array $track_uris,
    string $playlist_id
  ): void {
    $uri = Env::load()->get('APIURI')."/playlists/$playlist_id/tracks";
    Curl::post($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->addQueryParam('uris', implode(',', $track_uris))
      ->addQueryParam('position', 0)
      ->exec();
  }

  /**
   * Converts a Spotify URL track into a Spotify URI track. They are different.
   * e.g.
   *  Spotify URL: https://open.spotify.com/track/1UyYXStg3u4KoZSZix3LGF
   *  Spotify URI: spotify:track:1UyYXStg3u4KoZSZix3LGF
   *
   * @param string  Spotify track web URL
   * @return string Spotify track URI
   */
  private static function getTrackURI(
    string $track_id
  ): string {
    $uri = Env::load()->get('APIURI')."/tracks/$track_id";
    $response = Curl::get($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->exec();
    return $response['uri'];
  }

  /**
   * Returns the value for the Basic Auth header in accordance with Spotify's
   * API requirements.
   * Structure is: Basic base64(<client_id>:<client_secret>)
   *
   * @return string Header value
   */
  private static function getBasicHeader(): string {
    $auth_info = sprintf(
      '%s:%s',
      Env::load()->get('ClientID'),
      Env::load()->get('ClientSecret')
    );
    $auth_info = base64_encode($auth_info);
    return 'Basic '.$auth_info;
  }

  /**
   * Returns the value for the Bearer Auth header with the access token.
   * Structure is: Bearer <access_token>
   *
   * @return string Header value
   */
  private static function getBearerHeader(): string {
    return 'Bearer '.Env::load()->get('AccessToken');
  }
}