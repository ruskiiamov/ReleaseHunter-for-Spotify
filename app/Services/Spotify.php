<?php

namespace App\Services;

use App\Exceptions\SpotifyRequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Exception;

class Spotify
{
    private $authUrl = 'https://accounts.spotify.com/authorize';
    private $tokenUrl = 'https://accounts.spotify.com/api/token';
    private $apiUrl = 'https://api.spotify.com/v1';
    private $scope = 'user-read-email user-read-private user-follow-read user-library-read';

    public function getAuthUrl()
    {
        $parameters = [
            'client_id' => env('SPOTIFY_CLIENT_ID'),
            'response_type' => 'code',
            'redirect_uri' => route('callback'),
            'scope' => $this->scope,
            'state' => $this->createState(),
            'show_dialog' => 'true',
        ];
        $url = $this->authUrl . '?' . http_build_query($parameters, '', '&');
        return $url;
    }

    public function getAccessToken($code)
    {
        $parameters = [
            'client_id' => env('SPOTIFY_CLIENT_ID'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('callback'),
            'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        ];

        $result = $this->request('POST', $this->tokenUrl, $parameters);

        $this->saveAccessToken($result->access_token, $result->expires_in);

        return $result;

    }

    public function getRefreshedAccessToken($refreshToken)
    {
        $parameters = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $base64 = base64_encode(env('SPOTIFY_CLIENT_ID') . ':' . env('SPOTIFY_CLIENT_SECRET'));

        $headers = ['Authorization' => 'Basic ' . $base64];

        $result = $this->request('POST', $this->tokenUrl, $parameters, $headers);

        $this->saveAccessToken($result->access_token, $result->expires_in);

        return $result->access_token;
    }

    public function getClientAccessToken()
    {
        $parameters = [
            'grant_type' => 'client_credentials',
        ];

        $base64 = base64_encode(env('SPOTIFY_CLIENT_ID') . ':' . env('SPOTIFY_CLIENT_SECRET'));

        $headers = ['Authorization' => 'Basic ' . $base64];

        for ($i = 0; $i < 3; $i++) {
            try {
                $result = $this->request('POST', $this->tokenUrl, $parameters, $headers);
                break;
            } catch (\Exception $e) {
                sleep(3);
            }
        }

        if (!isset($result)) {
            throw new \Exception('Access token not received');
        }

        $this->saveClientAccessToken($result->access_token, $result->expires_in);

        return $result->access_token;
    }

    public function getUserData($accessToken)
    {
        $headers = ['Authorization' => 'Bearer ' . $accessToken];
        return $this->request('GET', $this->apiUrl . '/me', [], $headers);
    }

    public function isFreshAccessToken()
    {
        return time() < session('expiring_time');
    }

    public function getFollowedArtists($accessToken, $after = null)
    {
        $parameters = [
            'type' => 'artist',
            'limit' => '50',
        ];

        if ($after) {
            $parameters['after'] = $after;
        }

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/me/following?', $parameters, $headers);
    }

    public function getSavedAlbums($accessToken, $offset = null)
    {
        $parameters = [
            'limit' => '50',
        ];

        if ($offset) {
            $parameters['offset'] = $offset;
        }

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/me/albums?', $parameters, $headers);
    }

    public function getLastArtistAlbum($accessToken, $artistId)
    {
        $parameters = [
            'include_groups' => 'album',
            'limit' => 1,
        ];

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . "/artists/{$artistId}/albums?", $parameters, $headers);
    }

    public function getLastArtistSingle($accessToken, $artistId)
    {
        $parameters = [
            'include_groups' => 'single',
            'limit' => 1,
        ];

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . "/artists/{$artistId}/albums?", $parameters, $headers);
    }

    public function getArtistAlbums($accessToken, $artistId)
    {
        $parameters = [
            'include_groups' => 'album,single',
        ];

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . "/artists/{$artistId}/albums?", $parameters, $headers);
    }

    public function getAlbum($accessToken, $albumId)
    {
        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . "/albums/{$albumId}", [], $headers);

    }

    public function getArtist($accessToken, $artistId)
    {
        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . "/artists/{$artistId}?", [], $headers);
    }

    public function getNewReleases($accessToken, $option = 'new', $market = 'RU', $offset = null)
    {
        $parameters = [
            'q' => 'tag:' . $option,
            'type' => 'album',
            'limit' => '50',
            'market' => $market,
        ];

        if ($offset) {
            $parameters['offset'] = $offset;
        }

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/search?', $parameters, $headers);
    }

    public function getMarkets($accessToken)
    {
        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/markets', [], $headers);
    }

    public function areRequestsAvailable()
    {
        $availableSince = Cache::get('spotify-requests-available-since', 0);
        return time() > $availableSince;
    }

    public function getNewReleases2($accessToken)
    {
        $parameters = [
            'country' => 'GB',
            'limit' => '50',
            'offset' => '99',
        ];

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/browse/new-releases', $parameters, $headers);
    }

    public function getAvailableGenreSeeds($accessToken)
    {
        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/recommendations/available-genre-seeds', [], $headers);
    }

    public function getSeveralAlbums($accessToken, array $albumIds)
    {
        $ids = implode(',', $albumIds);

        $parameters = [
            'ids' => $ids
        ];

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/albums', $parameters, $headers);
    }

    public function getSeveralArtists($accessToken, array $artistIds)
    {
        $ids = implode(',', $artistIds);

        $parameters = [
            'ids' => $ids
        ];

        $headers = ['Authorization' => 'Bearer ' . $accessToken];

        return $this->request('GET', $this->apiUrl . '/artists', $parameters, $headers);
    }

    private function createState()
    {
        $state = uniqid(rand(), true);
        session(['state' => $state]);
        return $state;
    }

    private function request($method, $url, $parameters = [], $headers = [])
    {
        for ($i = 1; $i <= 3; $i++) {
            $request = Http::withHeaders($headers)->asForm();

            while (true) {
                if (RateLimiter::remaining('spotify-request', config('spotifyConfig.requestRateLimitAttempts'))) {
                    RateLimiter::hit('spotify-request', config('spotifyConfig.requestRateLimitDecay'));

                    switch ($method) {
                        case 'GET':
                            $response = $request->get($url, $parameters);
                            break;
                        case 'POST':
                            $response = $request->post($url, $parameters);
                            break;
                        default:
                            throw new \Exception('Wrong method: ' . $method);
                    }

                    break;
                } else {
                    $seconds = RateLimiter::availableIn('spotify-request');
                    Log::info('Spotify Request: Rate limiter sleep: ' . $seconds);
                    sleep($seconds);
                }
            }

            if ($response->successful()) {
                return $response->object();
            } else {
                Log::error('Spotify Request: ' . $response->status() . ' - ' . $method . ' ' . $url);
                if ($response->status() != 429) {
                    throw new SpotifyRequestException($response->status() . ' - ' . $method . ' ' . $url);
                } else {
                    $retryAfter = $response->header('Retry-After') ?? 0;
                    Log::info('Spotify Request: Retry-After=' . $retryAfter . ' seconds');
                    if ($retryAfter <= 60) {
                        sleep($retryAfter + 1);
                    } else {
                        Cache::put('spotify-requests-available-since', time() + $retryAfter);
                        throw new SpotifyRequestException('Retry-After time is too big: ' . $retryAfter . ' - ' . $method . ' ' . $url);
                    }
                }
            }
        }
        throw new SpotifyRequestException('Retry limit exceed - ' . $method . ' ' . $url);
    }

    private function saveAccessToken($accessToken, $expires_in)
    {
        session([
            'access_token' => $accessToken,
            'expiring_time' => time() + $expires_in - 10,
        ]);
    }

    private function saveClientAccessToken($accessToken, $expires_in)
    {
        try {
            Cache::put('client_access_token', $accessToken, $expires_in - 10);
        } catch (Exception $e) {
            Log::error($e->getMessage(), [
                'method' => __METHOD__
            ]);
        }
    }
}
