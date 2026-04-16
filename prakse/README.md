# LED Matrix Web Controller

This is a website for controlling an LED light matrix. It allows you to design patterns on a dynamic grid and save them to a database.

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

## latviski

# led matrica

šis kods ir domats izmantot lai kontrolētu LED matricu. Tev tas atļuj saglabat daudzu izmēra dizainus un saglabat tos datubāzē

1.  **Datubāzes Uzstādīšana**

    * Šis projekts ir paredzēts darbam ar XAMPP. 
    * Fails "db.php" mēģinās automātiski izveidot datu bāzi "led_matrix_db" un tabulu "dizainparaugi", kad pirmo reizi palaižat vietni. 
    * Ja jums ir MySQL "root" lietotāja parole, rediģējiet "db.php" un atjauniniet mainīgo "$password".

2. **Vietnes darbība**:

    * Pārliecinieties, ka Apache un MySQL darbojas XAMPP vadības panelī. 
    * Atveriet pārlūkprogrammu un dodieties uz `http://localhost/prakse/`.

## Funkcijas

* **Zīmēt**: Noklikšķiniet un velciet uz režģa, lai iedegtu gaismas diodes. 
* **Krāsa**: izvēlieties jebkuru krāsu, izmantojot krāsu atlasītāju. 
* **Saglabāt**: ievadiet nosaukumu un noklikšķiniet uz "Saglabāt serverī", lai saglabātu savu dizainu SQL datu bāzē. 
* **Ielādēt**: Noklikšķiniet uz jebkura dizaina sarakstā, lai to ielādētu režģī. 
* **Notīrīt**: atiestatiet režģi ar pogu "Notīrīt matricu".

## Faili

* `index.php`: galvenā tīmekļa lapa. 
* `style.css`: tumšā motīva un LED efektu stili. 
* `script.js`: apstrādā režģa mijiedarbību un API izsaukumus. 
* `api.php`: PHP skripts, lai apstrādātu saglabāšanu/ielādi no datu bāzes. 
* `db.php`: datu bāzes savienojuma iestatījumi.