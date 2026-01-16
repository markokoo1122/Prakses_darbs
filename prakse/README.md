# LED Matrix Web Controller

This is a mock-up website for controlling an LED light matrix. It allows you to design patterns on a 16x16 grid and save them to a database.

## Setup Instructions

1.  **Database Setup**:
    *   This project is designed to work with XAMPP.
    *   The `db.php` file will attempt to automatically create the database `led_matrix_db` and the `designs` table when you first run the website.
    *   If you have a password for your MySQL `root` user, edit `db.php` and update the `$password` variable.

2.  **Running the Site**:
    *   Ensure Apache and MySQL are running in the XAMPP Control Panel.
    *   Open your browser and navigate to `http://localhost/prakse/`.

## Features

*   **Draw**: Click and drag on the grid to light up LEDs.
*   **Color**: Choose any color using the color picker.
*   **Save**: Enter a name and click "Save to Server" to store your design in the SQL database.
*   **Load**: Click on any design in the list to load it onto the grid.
*   **Clear**: Reset the grid with the "Clear Matrix" button.

## Files

*   `index.php`: The main webpage.
*   `style.css`: Styles for the dark theme and LED effects.
*   `script.js`: Handles the grid interaction and API calls.
*   `api.php`: PHP script to handle saving/loading from the database.
*   `db.php`: Database connection settings.
