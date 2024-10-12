<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TmdbApiService implements TmdbApiServiceInterface
{
    public const CACHE_GENRES_KEY = 'tmdb_genres';
    private const CACHE_GENRES_EXPIRY = 3600;
    public const CACHE_TOP_RATED_MOVIES_KEY_PREFIX = 'tmdb_top_rated_movies_page_';
    private const CACHE_TOP_RATED_MOVIES_EXPIRY = 1800;

    private ApiClientService $apiClient;
    private string $apiUrl;
    private string $language;
    private LoggerInterface $logger;
    private CacheInterface $cache;

    public function __construct(
        ApiClientService $apiClient,
        string $apiUrl,
        string $language,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->apiClient = $apiClient;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->language = $language;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    public function getGenres(): array
    {
        return $this->cache->get(self::CACHE_GENRES_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_GENRES_EXPIRY);
            $endpoint = '/genre/movie/list';

            try {
                $genres = $this->makeApiCall($endpoint, [], 'genres');
                $this->logger->info('Fetched genres successfully.', ['count' => count($genres)]);
                return $genres;
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch genres from TMDB.', [
                    'exception' => $e->getMessage(),
                ]);
                throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to fetch genres.');
            }
        }) ?? [];
    }

    public function getTopRatedMovieWithVideos(): array
    {
        try {
            $topRatedMovies = $this->getTopRatedMovies();
            if (!empty($topRatedMovies)) {
                $topMovie = $topRatedMovies[0];
                $topMovieVideos = $this->getMovieVideos($topMovie['id']);
                $this->logger->info('Fetched top movie videos.', [
                    'movie_id' => $topMovie['id'],
                    'videos_count' => count($topMovieVideos),
                ]);

                return [
                    'movie' => $topMovie,
                    'videos' => $topMovieVideos
                ];
            }
            return [];
        } catch (\Exception $e) {
            $this->logger->error('Error fetching top-rated movies or their videos.', [
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to fetch top-rated movies.');
        }
    }

    public function getMoviesByGenre(?int $genreId, int $page = 1): array
    {
        $endpoint = '/discover/movie';
        $params = [
            'page' => $page,
            'sort_by' => 'popularity.desc',
            'include_adult' => 'false',
            'include_video' => 'false',
        ];

        if ($genreId !== null) {
            $params['with_genres'] = $genreId;
        }

        try {
            $movies = $this->makeApiCall($endpoint, $params, 'results');
            $this->logger->info('Fetched movies for genre.', [
                'genre_id' => $genreId,
                'page' => $page,
                'count' => count($movies),
            ]);

            return $movies;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch movies for genre.', [
                'genre_id' => $genreId,
                'page' => $page,
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to fetch movies by genre.');
        }
    }

    public function getMovieDetails(int $movieId): array
    {
        $endpoint = "/movie/{$movieId}";
        $params = [
            'append_to_response' => 'videos',
        ];

        try {
            $movieDetails = $this->makeApiCall($endpoint, $params);
            $this->logger->info('Fetched movie details successfully.', [
                'movie_id' => $movieId,
                'details_count' => count($movieDetails),
            ]);

            return $movieDetails;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch movie details from TMDB.', [
                'movie_id' => $movieId,
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to fetch movie details.');
        }
    }

    public function searchMovies(string $query): array
    {
        $endpoint = '/search/movie';
        $params = [
            'query' => $query,
            'include_adult' => 'false',
        ];

        try {
            $results = $this->makeApiCall($endpoint, $params, 'results');
            $this->logger->info('Performed movie search successfully.', [
                'query' => $query,
                'count' => count($results),
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Failed to perform movie search.', [
                'query' => $query,
                'exception' => $e->getMessage(),
            ]);
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to search for movies.');
        }
    }

    private function getTopRatedMovies(int $page = 1): array
    {
        $endpoint = '/movie/top_rated';
        $params = [
            'page' => $page,
        ];

        $cacheKey = self::CACHE_TOP_RATED_MOVIES_KEY_PREFIX . $page;

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $params) {
                $item->expiresAfter(self::CACHE_TOP_RATED_MOVIES_EXPIRY);
                return $this->makeApiCall($endpoint, $params, 'results');
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch top-rated movies from TMDB.', [
                'page' => $page,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getMovieVideos(int $movieId): array
    {
        $endpoint = "/movie/{$movieId}/videos";

        try {
            $videos = $this->makeApiCall($endpoint, [], 'results');
            return $videos;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch movie videos from TMDB.', [
                'movie_id' => $movieId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function makeApiCall(string $endpoint, array $params = [], ?string $key = null): array
    {
        $params['language'] = $this->language;
        $fullUrl = $this->apiUrl . $endpoint;

        try {
            $response = $this->apiClient->makeRequest('GET', $fullUrl, $params);
            return $key ? ($response[$key] ?? []) : $response;
        } catch (\Exception $e) {
            $this->logger->error('TMDB API call failed.', [
                'endpoint' => $endpoint,
                'params' => $params,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
