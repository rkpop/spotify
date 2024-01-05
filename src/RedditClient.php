<?php
declare(strict_types = 1);

require_once('DB.php');
require_once('Curl.php');
require_once('Env.php');

/**
 * Dead simple utils wrapper to interface with Reddit. We're not really using
 * anything fancy here since at-present all that we need from Reddit is the
 * contents of the releases wiki.
 */
final class RedditClient {

  /**
   * Pull up the releases wiki for the supplied month + year, find the releases
   * table, for each entry, find the Spotify URL, and hand them over.
   *
   * Reddit allows you to just add .json to the end of any URL to get the page
   * in that format which makes it SUPER easy to ingest for scripts.
   *
   * @param string          Full name of the month e.g. July
   * @param int             Full year e.g. 2020
   * @return array<string>  Array of all the Spotify URls that could be found
   */
  public static function getReleases(
    string $month,
    int $year
  ): array {
    $hour = (int)date('G');
    if ($hour === 0 || $hour === 12) {
      $uri = Env::load()->get('RedditTokenLink');
      $response = Curl::post($uri)
        ->setAuth(
          Env::load()->get('RedditClientID'),
          Env::load()->get('RedditClientSecret'),
        )
        ->addPostParam('grant_type', 'client_credentials')
        ->exec();
      $access_token = $response['access_token'];
      DB::connect()->setCredential('reddit', $access_token);
    }

    $uri = Env::load()->get('ReleasesLink')."/$year/$month.json";
    $auth_header = 'bearer '.DB::connect()->getCredential('reddit');
    $response = Curl::get($uri)
      ->addHeader('Authorization', $auth_header)
      ->exec();
    $wiki_content = $response['data']['content_md'];
    $wiki_content = str_replace("\r\n", "\n", $wiki_content);
    $rows = explode("\n", $wiki_content);

    // This looks a bit silly, but it's really just how we skip through the
    // non-table parts of the wiki.
    // Markdown tables separate their header and body with a row of |--|--...
    // So, if we just ignore rows that don't start with that, we know we
    // haven't gotten to the table yet.
    foreach ($rows as $row) {
      if (substr($row, 0, 3) !== '|--') {
        array_shift($rows);
      } else {
        break;
      }
    }
    array_shift($rows);

    $spotify_urls = [];
    foreach ($rows as $row) {
      if (substr($row, 0, 1) !== '|') {
        break;
      }

      preg_match(
        '/.*(https:\/\/(open|play).spotify.com\/(album|track)\/[A-z0-9]+).*/', 
        $row, 
        $matches
      );
      if (count($matches) < 2) {
        continue;
      }
      $spotify_urls[] = $matches[1];
    }

    return $spotify_urls;
  }

}
