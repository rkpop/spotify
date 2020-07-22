<?php
declare(strict_types = 1);

require_once('Curl.php');
require_once('Env.php');

final class RedditClient {

  public static function getReleases(
    string $month,
    int $year
  ): array {
    $uri = Env::load()->get('ReleasesLink')."/$year/$month.json";
    $response = Curl::get($uri)->exec();
    $wiki_content = $response['data']['content_md'];
    $wiki_content = str_replace("\r\n", "\n", $wiki_content);
    $rows = explode("\n", $wiki_content);

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