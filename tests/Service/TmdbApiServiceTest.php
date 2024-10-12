<?php

namespace App\Tests\Service;

use App\Service\ApiClientService;
use App\Service\TmdbApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TmdbApiServiceTest extends TestCase
{
    private TmdbApiService $tmdbApiService;
    private ApiClientService $apiClient;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    private string $apiUrl = 'https://api.themoviedb.org/3';
    private string $language = 'en-US';

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClientService::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->tmdbApiService = new TmdbApiService(
            $this->apiClient,
            $this->apiUrl,
            $this->language,
            $this->logger,
            $this->cache
        );
    }

    public function testGetGenresSuccess(): void
    {
        $expectedGenres = [
            ['id' => 28, 'name' => 'Action'],
            ['id' => 12, 'name' => 'Adventure'],
        ];

        $cacheItem = $this->createMock(ItemInterface::class);

        $this->cache->method('get')
            ->with(TmdbApiService::CACHE_GENRES_KEY, $this->anything())
            ->willReturnCallback(function ($key, $callback) use ($cacheItem, $expectedGenres) {
                return $callback($cacheItem);
            });

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->with('GET', $this->apiUrl . '/genre/movie/list', ['language' => $this->language])
            ->willReturn(['genres' => $expectedGenres]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Fetched genres successfully.', ['count' => count($expectedGenres)]);

        $genres = $this->tmdbApiService->getGenres();
        $this->assertEquals($expectedGenres, $genres);
    }

    public function testGetGenresFailure(): void
    {
        $cacheItem = $this->createMock(ItemInterface::class);

        $this->cache->method('get')
            ->with(TmdbApiService::CACHE_GENRES_KEY, $this->anything())
            ->willReturnCallback(function ($key, $callback) use ($cacheItem) {
                return $callback($cacheItem);
            });

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->willThrowException(new \Exception('API error'));

        $this->logger->expects($this->any())
            ->method('error');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unable to fetch genres.');

        $this->tmdbApiService->getGenres();
    }

    public function testGetTopRatedMovieWithVideosSuccess(): void
    {
        $expectedTopMovies = [
            ['id' => 1, 'title' => 'Top Movie'],
        ];
        $expectedVideos = [
            ['id' => 'video1', 'name' => 'Trailer 1'],
        ];

        $cacheItemTopRated = $this->createMock(ItemInterface::class);

        $this->cache->method('get')
            ->with(TmdbApiService::CACHE_TOP_RATED_MOVIES_KEY_PREFIX . '1', $this->anything())
            ->willReturnCallback(function ($key, $callback) use ($cacheItemTopRated, $expectedTopMovies) {
                return $callback($cacheItemTopRated);
            });

        $this->apiClient->expects($this->exactly(2))
            ->method('makeRequest')
            ->withConsecutive(
                [
                    'GET',
                    $this->apiUrl . '/movie/top_rated',
                    $this->callback(function ($params) {
                        return $params['page'] === 1 && $params['language'] === $this->language;
                    })
                ],
                [
                    'GET',
                    $this->apiUrl . '/movie/1/videos',
                    ['language' => $this->language]
                ]
            )
            ->willReturnOnConsecutiveCalls(
                ['results' => $expectedTopMovies],
                ['results' => $expectedVideos]
            );

        $this->logger->expects($this->any())
            ->method('info');

        $result = $this->tmdbApiService->getTopRatedMovieWithVideos();

        $expectedResult = [
            'movie' => $expectedTopMovies[0],
            'videos' => $expectedVideos
        ];

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetTopRatedMovieWithVideosFailure(): void
    {
        $cacheItemTopRated = $this->createMock(ItemInterface::class);

        $this->cache->method('get')
            ->with(TmdbApiService::CACHE_TOP_RATED_MOVIES_KEY_PREFIX . '1', $this->anything())
            ->willReturnCallback(function ($key, $callback) use ($cacheItemTopRated) {
                return $callback($cacheItemTopRated);
            });

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->willThrowException(new \Exception('API error'));

        $this->logger->expects($this->any())
            ->method('error');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unable to fetch top-rated movies.');

        $this->tmdbApiService->getTopRatedMovieWithVideos();
    }

    public function testGetMoviesByGenreSuccess(): void
    {
        $genreId = 28;
        $page = 1;
        $expectedMovies = [
            ['id' => 100, 'title' => 'Movie 1'],
            ['id' => 101, 'title' => 'Movie 2'],
        ];

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->with(
                'GET',
                $this->apiUrl . '/discover/movie',
                $this->callback(function ($params) use ($genreId, $page) {
                    return $params['with_genres'] === $genreId
                        && $params['page'] === $page
                        && $params['language'] === $this->language
                        && $params['sort_by'] === 'popularity.desc'
                        && $params['include_adult'] === 'false'
                        && $params['include_video'] === 'false';
                })
            )
            ->willReturn(['results' => $expectedMovies]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Fetched movies for genre.', [
                'genre_id' => $genreId,
                'page' => $page,
                'count' => count($expectedMovies),
            ]);

        $movies = $this->tmdbApiService->getMoviesByGenre($genreId, $page);
        $this->assertEquals($expectedMovies, $movies);
    }

    public function testGetMoviesByGenreWithNullGenreId(): void
    {
        $movies = $this->tmdbApiService->getMoviesByGenre(null);
        $this->assertEquals([], $movies);
    }

    public function testGetMoviesByGenreFailure(): void
    {
        $genreId = 28;
        $page = 1;

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->willThrowException(new \Exception('API error'));

        $this->logger->expects($this->any())
            ->method('error');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unable to fetch movies by genre.');

        $this->tmdbApiService->getMoviesByGenre($genreId, $page);
    }

    public function testGetMovieDetailsSuccess(): void
    {
        $movieId = 100;
        $expectedDetails = [
            'id' => $movieId,
            'title' => 'Movie Title',
            'videos' => ['results' => [['id' => 'video1', 'name' => 'Trailer']]]
        ];

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->with(
                'GET',
                $this->apiUrl . "/movie/{$movieId}",
                $this->callback(function ($params) {
                    return $params['append_to_response'] === 'videos'
                        && $params['language'] === $this->language;
                })
            )
            ->willReturn($expectedDetails);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Fetched movie details successfully.', [
                'movie_id' => $movieId,
                'details_count' => count($expectedDetails),
            ]);

        $details = $this->tmdbApiService->getMovieDetails($movieId);
        $this->assertEquals($expectedDetails, $details);
    }

    public function testGetMovieDetailsFailure(): void
    {
        $movieId = 100;

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->willThrowException(new \Exception('API error'));

        $this->logger->expects($this->any())
            ->method('error');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unable to fetch movie details.');

        $this->tmdbApiService->getMovieDetails($movieId);
    }

    public function testSearchMoviesSuccess(): void
    {
        $query = 'Inception';
        $expectedResults = [
            ['id' => 27205, 'title' => 'Inception'],
        ];

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->with(
                'GET',
                $this->apiUrl . '/search/movie',
                $this->callback(function ($params) use ($query) {
                    return $params['query'] === $query
                        && $params['include_adult'] === 'false'
                        && $params['language'] === $this->language;
                })
            )
            ->willReturn(['results' => $expectedResults]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Performed movie search successfully.', [
                'query' => $query,
                'count' => count($expectedResults),
            ]);

        $results = $this->tmdbApiService->searchMovies($query);
        $this->assertEquals($expectedResults, $results);
    }

    public function testSearchMoviesFailure(): void
    {
        $query = 'Inception';

        $this->apiClient->expects($this->once())
            ->method('makeRequest')
            ->willThrowException(new \Exception('API error'));

        $this->logger->expects($this->any())
            ->method('error');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unable to search for movies.');

        $this->tmdbApiService->searchMovies($query);
    }
}
