<?php
declare(strict_types = 1);

require_once('Curl.php');
require_once('DB.php');
require_once('Env.php');

final class SpotifyClient {

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
      $month,
      $year,
    );
    return $spotify_id;
  }

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

  public static function createOrFetchPlaylist(
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

  public static function addReleaseToPlaylist(
    string $release_url,
    string $playlist_id
  ): void {
    if (DB::connect()->isProcessed($release_url)) {
      return;
    }

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

  public static function addAlbumToPlaylist(
    string $album_url,
    string $playlist_id
  ): void {
    if (DB::connect()->isProcessed($album_url)) {
      return;
    }

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

  private static function getTrackURI(
    string $track_id
  ): string {
    $uri = Env::load()->get('APIURI')."/tracks/$track_id";
    $response = Curl::get($uri)
      ->addHeader('Authorization', self::getBearerHeader())
      ->exec();
    return $response['uri'];
  }

  private static function getBasicHeader(): string {
    $auth_info = sprintf(
      '%s:%s', 
      Env::load()->get('ClientID'), 
      Env::load()->get('ClientSecret')
    );
    $auth_info = base64_encode($auth_info);
    return 'Basic '.$auth_info;
  }

  private static function getBearerHeader(): string {
    return 'Bearer '.Env::load()->get('AccessToken');
  }
}