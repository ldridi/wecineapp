<?php

namespace App\Service;

interface TmdbApiServiceInterface
{
    public function getGenres(): array;
    public function getTopRatedMovieWithVideos(): array;
    public function getMoviesByGenre(?int $genreId, int $page = 1): array;
    public function getMovieDetails(int $movieId): array;
    public function searchMovies(string $query): array;
}
