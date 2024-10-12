import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import './styles/app.css';
import * as bootstrap from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const genreList = document.getElementById('genre-list');
    const movieList = document.getElementById('movie-list');
    const moviePopup = document.getElementById('movie-popup');
    const popupDetails = document.getElementById('popup-details');
    const searchInput = document.getElementById('search_q');
    const autocompleteResults = document.getElementById('autocomplete-results');
    const bootstrapModal = new bootstrap.Modal(moviePopup);

    let currentGenreId = null;
    let currentPage = 1;

    // Function to generate stars HTML based on vote average
    const generateStars = (voteAverage) => {
        const maxStars = 5;
        const rating = voteAverage / 2;
        const fullStars = Math.floor(rating);
        const halfStar = rating % 1 >= 0.5;
        let starsHtml = '';

        starsHtml += '<i class="fa fa-star" style="color:#0069D9"></i> '.repeat(fullStars);
        if (halfStar) starsHtml += '<i class="fas fa-star-half-alt" style="color:#0069D9"></i> ';
        starsHtml += '<i class="far fa-star"></i> '.repeat(maxStars - fullStars - (halfStar ? 1 : 0));

        return starsHtml;
    };

    // Function to fetch and display movie details
    const fetchAndDisplayMovieDetails = async (movieId) => {
        try {
            const response = await fetch(`/api/movie/${movieId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const movie = await response.json();

            const stars = generateStars(movie.vote_average);
            const trailerKey = movie.videos?.results[0]?.key || '';

            popupDetails.innerHTML = `
                <div class="embed-responsive embed-responsive-16by9 position-relative" style="overflow: hidden;">
                    <iframe
                        class="embed-responsive-item position-absolute"
                        id="video-trailer"
                        src="https://www.youtube.com/embed/${trailerKey}"
                        allowfullscreen
                    ></iframe>
                </div>
                <div class="separator"></div>
                <span style="font-size: 13px">
                    <strong>Film:</strong> ${movie.title}
                    <span class="stars">${stars}</span>
                    <span class="badge badge-primary" style="padding: 5px">${movie.vote_average}</span>
                    pour ${movie.vote_count} utilisateur(s)
                </span>`;

            bootstrapModal.show();
        } catch (error) {
            console.error('Error fetching movie details:', error);
            popupDetails.innerHTML = '<p>There was an error fetching the movie details. Please try again later.</p>';
        }
    };

    const fetchMoviesByGenre = async (genreId, page = 1) => {
        try {
            let url = `/api/movies?page=${page}`;
            if (genreId !== null) {
                url += `&genre_id=${genreId}`;
            }
            console.log(url)
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            const movies = await response.json();

            movieList.innerHTML = '';
            console.log(movies.length)
            if (movies.length === 0) {
                movieList.innerHTML = '<p>No movies found for the selected genre.</p>';
                return;
            }

            movies.forEach(movie => {
                const stars = generateStars(movie.vote_average);
                const year = new Date(movie.release_date).getFullYear() || 'Date invalide';
                const truncatedOverview = truncateText(movie.overview, 250);

                movieList.insertAdjacentHTML('beforeend', `
                <div class="card mb-3 movie-item" data-movie-id="${movie.id}" style="background: #f1f1f1">
                    <div class="row no-gutters">
                        <div class="col-md-4">
                            <img src="https://image.tmdb.org/t/p/w200${movie.poster_path}" alt="${movie.title}" class="img-thumbnail">
                        </div>
                        <div class="col-md-8">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title">${movie.title} <span class="stars">${stars}</span> (${movie.vote_count})</h6>
                                <p class="card-subtitle mb-2 text-muted">${year} - Disney</p>
                                <p class="card-text" style="flex-grow: 1;height: 150px">${truncatedOverview}</p>
                                <button class="btn btn-sm btn-primary mt-auto align-self-end">Lire les détails</button>
                            </div>
                        </div>
                    </div>
                </div>`);
            });


        } catch (error) {
            console.error('Error fetching movies:', error);
            movieList.innerHTML = '<p>There was an error fetching the movies. Please try again later.</p>';
        }
    };

    // Genre change event
    genreList.addEventListener('change', (e) => {
        if (e.target.name === 'genres') {
            document.querySelectorAll('input[name="genres"]').forEach(checkbox => {
                if (checkbox !== e.target) checkbox.checked = false;
            });

            if (e.target.checked) {
                currentGenreId = e.target.value;
                currentPage = 1; // Reset to first page
                movieList.innerHTML = '<p>Loading...</p>';
                fetchMoviesByGenre(currentGenreId, currentPage);
            } else {
                fetchMoviesByGenre(null, currentPage);
            }
        }
    });

    // Click event for movie list items
    movieList.addEventListener('click', (e) => {
        const movieItem = e.target.closest('.movie-item');
        if (movieItem) {
            const movieId = movieItem.dataset.movieId;
            fetchAndDisplayMovieDetails(movieId);
        }
    });

    // Search input event
    searchInput.addEventListener('input', async () => {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            autocompleteResults.innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const movies = await response.json();
            autocompleteResults.innerHTML = '';

            movies.slice(0, 5).forEach(movie => {
                const div = document.createElement('div');
                div.classList.add('list-group-item', 'list-group-item-action');
                div.textContent = movie.title;
                div.addEventListener('click', () => fetchAndDisplayMovieDetails(movie.id));
                autocompleteResults.appendChild(div);
            });
        } catch (error) {
            console.error('Error fetching search results:', error);
            autocompleteResults.innerHTML = '<p>There was an error fetching the search results. Please try again later.</p>';
        }
    });

    // Hide autocomplete results when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !autocompleteResults.contains(e.target)) {
            autocompleteResults.innerHTML = '';
        }
    });

    // Fetch and display genres
    fetch('/api/genres')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch genres.');
            }
            return response.json();
        })
        .then(genres => {
            genres.forEach(genre => {
                const listItem = document.createElement('li');
                listItem.classList.add('list-group-item');
                listItem.setAttribute('data-genre-id', genre.id);

                const formCheckDiv = document.createElement('div');
                formCheckDiv.classList.add('form-check');

                const inputElement = document.createElement('input');
                inputElement.classList.add('form-check-input');
                inputElement.type = 'checkbox';
                inputElement.name = 'genres';
                inputElement.id = `genre${genre.id}`;
                inputElement.value = genre.id;

                const labelElement = document.createElement('label');
                labelElement.classList.add('form-check-label');
                labelElement.setAttribute('for', `genre${genre.id}`);
                labelElement.textContent = genre.name;

                formCheckDiv.appendChild(inputElement);
                formCheckDiv.appendChild(labelElement);
                listItem.appendChild(formCheckDiv);
                genreList.appendChild(listItem);
            });
        })
        .catch(error => {
            console.error(error);
            genreList.innerHTML = '<li class="list-group-item">Unable to load genres.</li>';
        });

    // display movies on page load
    fetchMoviesByGenre(null, currentPage);

    function truncateText(text, maxLength) {
        if (!text) return 'No overview available.';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
});
