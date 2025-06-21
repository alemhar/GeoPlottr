# Mapp - Interactive Mapping Application

Mapp is a web-based GIS mapping application built with Laravel and Vue.js that allows users to create, save, and analyze geographical shapes and measurements.

![Mapp Application](https://via.placeholder.com/1200x600?text=Mapp+Application)

## Features

- **Interactive Map Interface**: Draw, edit, and visualize shapes on a Leaflet-powered map
- **Area and Distance Calculation**: Automatically calculate and display area and perimeter measurements
- **Shape Management**: Save, load, and organize shapes with descriptive information
- **User Location**: Center map to user's current location with a single click
- **Shape Highlighting**: Visual highlighting of selected shapes for improved user interaction
- **Side Panel Interface**: Detailed information and editing capabilities in a sliding side panel

## Technology Stack

### Backend
- PHP 8.2+
- Laravel 12.x
- MySQL Database
- RESTful API for shape data management

### Frontend
- Vue 3
- Inertia.js for SPA functionality
- Leaflet.js with Leaflet.draw for map interactions
- lucide-vue-next for vector icons

## Installation

### Prerequisites
- PHP 8.2+
- Composer
- Node.js and NPM
- MySQL

### Setup Instructions

1. Clone the repository:
   ```bash
   git clone git@github.com:alemhar/GeoPlottr.git
   cd mapp
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install JavaScript dependencies:
   ```bash
   npm install
   ```

4. Create environment file:
   ```bash
   cp .env.example .env
   ```

5. Generate application key:
   ```bash
   php artisan key:generate
   ```

6. Configure your database connection in `.env`

7. Run database migrations:
   ```bash
   php artisan migrate
   ```

8. Build frontend assets:
   ```bash
   npm run build
   ```

9. Start the development server:
   ```bash
   php artisan serve
   ```

## Development

### Running the Application in Development Mode

```bash
# Start the Laravel development server
php artisan serve

# In a separate terminal, start the Vite development server
npm run dev
```

### Creating New Shapes

1. Use the drawing tools on the map to create polygons or lines
2. Click on a shape to view its details in the side panel
3. Add a description and other attributes
4. Click the Save button to persist the shape to the database

### Map Features

- **Drawing Tools**: Access polygon and line drawing tools from the left toolbar
- **Layer Selection**: Click any shape to select it and view details
- **Locate Me**: Center the map to your current location
- **Saving**: Use the Save Session button to persist all current map shapes
- **Measurement**: Automatic calculation of area (polygons) and distance (lines)

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/measurements` | Get all measurements |
| GET | `/api/measurements/{id}` | Get a specific measurement |
| POST | `/api/measurements` | Create a new measurement |
| PUT | `/api/measurements/{id}` | Update a measurement |
| DELETE | `/api/measurements/{id}` | Delete a measurement |

## License

[MIT License](LICENSE.md)

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

Mapp was created using [Laravel Breeze](https://laravel.com/docs/12.x/starter-kits#breeze) with Vue and Inertia.js stack.
