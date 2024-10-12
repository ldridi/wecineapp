<?php

namespace App\Controller;

use App\Form\SearchType;
use App\Service\TmdbApiServiceInterface;
use App\Service\ValidationServiceInterface;
use App\Service\ExceptionHandlerServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private TmdbApiServiceInterface $tmdbService;
    private ValidationServiceInterface $validationService;
    private ExceptionHandlerServiceInterface $exceptionHandler;

    public function __construct(
        TmdbApiServiceInterface $tmdbService,
        ValidationServiceInterface $validationService,
        ExceptionHandlerServiceInterface $exceptionHandler
    ) {
        $this->tmdbService = $tmdbService;
        $this->validationService = $validationService;
        $this->exceptionHandler = $exceptionHandler;
    }

    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        $form = $this->createForm(SearchType::class);

        try {
            $topMovieData = $this->tmdbService->getTopRatedMovieWithVideos();
        } catch (\Exception $e) {
            $this->exceptionHandler->handleException($e);
            $topMovieData = [];
        }

        return $this->render('movie/index.html.twig', [
            'top_movie' => $topMovieData['movie'] ?? null,
            'top_movie_videos' => $topMovieData['videos'] ?? [],
            'search_form' => $form->createView(),
        ]);
    }

    #[Route('/api/movies', name: 'api_movies', methods: ['GET'])]
    public function getMoviesList(Request $request): Response
    {
        $genreIdParam = $request->query->get('genre_id', null);
        $genreId = is_numeric($genreIdParam) ? (int) $genreIdParam : null;
        $pageParam = $request->query->get('page', 1);
        $page = is_numeric($pageParam) ? (int) $pageParam : 1;

        try {
            $moviesResponse = $this->tmdbService->getMoviesByGenre($genreId, $page);
            return $this->json($moviesResponse);
        } catch (\Exception $e) {
            $this->exceptionHandler->handleException($e, ['genre_id' => $genreId, 'page' => $page]);
            return $this->json(['error' => 'An error occurred while fetching movies.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/genres', name: 'genre_list', methods: ['GET'])]
    public function getGenreList(): Response
    {
        try {
            $genres = $this->tmdbService->getGenres();
            return $this->json($genres);
        } catch (\Exception $e) {
            $this->exceptionHandler->handleException($e);
            return $this->json(['error' => 'An error occurred while fetching genres.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/movie/{id}', name: 'movie_details', methods: ['GET'])]
    public function getMovieDetails(int $id): Response
    {
        if (!$this->validationService->isValidId($id, 'movie')) {
            return $this->json(['error' => 'Invalid movie ID provided.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $movie = $this->tmdbService->getMovieDetails($id);
            return $this->json($movie);
        } catch (\Exception $e) {
            $this->exceptionHandler->handleException($e, ['movie_id' => $id]);
            return $this->json(['error' => 'An error occurred while fetching movie details.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/search', name: 'search_movies', methods: ['GET'])]
    public function searchMovies(Request $request): Response
    {
        $query = trim($request->query->get('q', ''));

        if (!$this->validationService->isValidSearchQuery($query)) {
            return $this->json(['error' => 'Search query cannot be empty.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $results = $this->tmdbService->searchMovies($query);
            return $this->json($results);
        } catch (\Exception $e) {
            $this->exceptionHandler->handleException($e, ['query' => $query]);
            return $this->json(['error' => 'An error occurred while searching for movies.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
