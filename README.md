# r/kpop Spotify 

On reddit.com/r/kpop, there is a wonderfully-mintained wiki that keeps track of all the musical releases from various K-Pop artists. 
The wiki is updated every month with a table full of info about the releases. [Here is a link](https://www.reddit.com/r/kpop/wiki/upcoming-releases/archive) to the wiki.

This script parses that wiki table, extracts the Spotify links to the releases, and then puts them into a playlist on Spotify. 

The playlists can be found on [Spotify.com](https://open.spotify.com/user/m0c30q17qpehqwup55yiqj0wg).

## How it works

The script basically does the following:

1. Gets the current month + year
2. Grabs the current-month's releases wiki from Reddit
3. Extracts out all the Spotify links currently in the wiki
4. Fetches the Spotify playlist for the corresponding month + year, or creates one if doens't exist
5. Inserts each of the releases into the playlist

## Requirements

The main logic is written in PHP with SQLite3 as the data store.

## Running the script

Simple as just

```php
php script.php
```

## Env file

The various modules that power the script make use of values stored in a local `.env` file. The `.env` file is NOT included in this repo as it contains secrets. A `.env.ini.example` is provided for reference as to the schema of the file.

## Database

The database is dead simple. Just two tables. 

1. `playlists` — which stores the Spotify ID for the playlist of a given month + year
2. `processed` — which is just a list of all the raw-links that we have already-handled from the reddit wiki 

Can see the specifics in the `spotify.db.schema` file.