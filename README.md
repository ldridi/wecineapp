# WECINE Application

You have 2 pages; the site must list all cinema genders on the left menu with the first best movie with
his description and play video. When you select a genre, I need to see a new list of movies according to
the selected genre. When you click on a movie, I get a popup which shows information about the
movie, and give the user the possibility to rate the movie using a 5-star rating system. You can search a
movie via the search bar which an autocomplete input.

## Features

- Create a Symfony application **✅**
- Manage API authentication & logic with Symfony **✅**
- Use and manipulate JSON API data **✅**
- Implement algorithm with DRY principle **✅**
- Manage assets with Webpack **✅**
- Use docker and/or docker-compose **✅**
- Create HTML template with a clean design using stylesheets **✅**
- User registration & login is not required. Keeping user rates is not required. **❌**

## Requirements

- Docker Compose
- PHP 8.3 or higher
- Symfony 7
- Composer
- Node.js and npm for managing front assets
- [TheMovieDB API](https://www.themoviedb.org/documentation/api) and Bearer Token

## Setup Instructions

### 1. Clone the Repository

git clone https://github.com/ldridi/wecineapp.git &&
cd wecineapp

### 2. Docker Setup
```console
docker-compose up --build -d
sudo nano /etc/hosts
127.0.0.1 wecineapp-app.local
docker-compose up --build -d
```

### 3. Environment Variables
```console
cp .env .env.local
TMDB_API_KEY=your_tmdb_api_key
TMDB_BEARER_TOKEN=your_bearer_token
API_BASE_URL=https://api.themoviedb.org/3
APP_ENV=dev
```

### 4. Install PHP and Node.js Dependencies
```console
docker-compose exec app composer install
docker-compose exec app npm install
docker-compose exec app npm install bootstrap@4.6.2
docker-compose exec app npm run dev
docker-compose exec app npm run watch
```

### 5. Access the application
```console
http://wecineapp-app.local
```

### 6. Test the application
```console
docker-compose exec app php bin/phpunit
```

## Setup Instructions
### Fixing Permission Issues
```console
docker-compose exec app chown -R www-data:www-data /var/www/html
```
