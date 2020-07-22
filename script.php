<?php 
declare(strict_types = 1);

require_once('src/RedditClient.php');
require_once('src/SpotifyClient.php');

SpotifyClient::refreshAccessToken();

$month = date('F');
$year = (int)date('Y');

$releases = RedditClient::getReleases($month, $year);
$playlist_id = SpotifyClient::createOrFetchPlaylist($month, $year);

foreach ($releases as $release_url) {
  SpotifyClient::addReleaseToPlaylist($release_url, $playlist_id);
}