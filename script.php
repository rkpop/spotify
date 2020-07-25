<?php 
declare(strict_types = 1);

require_once('src/RedditClient.php');
require_once('src/SpotifyClient.php');

SpotifyClient::refreshAccessToken();

$minute = (int)date('i');
$hour = (int)date('G');
$day = date('j');
$month = date('F');
$year = (int)date('Y');

// The script currently runs every 15 minutes. So, if the current run is
// within the first 15 minutes of the first day of a month, we have changed
// months!
//
// If it's a new month, it means we get to clear out the "Current" month playlist
// This is a special playlist that is a copy of the separate playlist created
// for the month we're in. It does not delete the original month's playlist.
// But in order for the "Current" plalist to stay current, when a new month
// comes around, all the old entries need to be wiped.
if ($day === 1 && $hour === 0 && $minute < 15) {
  SpotifyClient::clearOutCurrentPlaylist();
}

$releases = RedditClient::getReleases($month, $year);
$month_playlist_id = SpotifyClient::createOrFetchPlaylist($month, $year);
$current_playlist_id = SpotifyClient::createOrFetchCurrentPlaylist();

foreach ($releases as $release_url) {
  if (DB::connect()->isProcessed($release_url)) {
    continue;
  }

  SpotifyClient::addReleaseToPlaylist($release_url, $month_playlist_id);
  SpotifyClient::addReleaseToPlaylist($release_url, $current_playlist_id);
  
  DB::connect()->markAsProcessed($release_url);
}