# System Wypożyczalni Samochodów (Car Rental System)

Kompletna aplikacja webowa typu Full-Stack umożliwiająca rezerwację pojazdów online. System posiada podział na panel klienta oraz rozbudowany panel administracyjny do zarządzania flotą i zamówieniami.

##  Główne funkcjonalności

### Panel Użytkownika
* **Rejestracja i logowanie:** Bezpieczny system uwierzytelniania.
* **Przegląd floty:** Lista dostępnych pojazdów ze zdjęciami i cennikiem.
* **Rezerwacja:** Proces rezerwacji wybranego pojazdu w określonym terminie.
* **Historia:** Podgląd historii własnych rezerwacji i ich statusów.
* **Odzyskiwanie hasła:** Resetowanie hasła via e-mail.

### Panel Administratora
* **Dashboard:** Statystyki wypożyczeń i podgląd aktywnych rezerwacji.
* **Zarządzanie flotą:** Dodawanie, edycja i usuwanie pojazdów (`vehicles.php`).
* **Zarządzanie użytkownikami:** Przegląd listy klientów (`customers.php`).
* **Weryfikacja:** Procesowanie zgłoszeń i zmiana statusów rezerwacji.

##  Technologie

* **Backend:** PHP (Native / Vanilla)
* **Baza danych:** MySQL
* **Frontend:** HTML5, CSS3
* **Biblioteki:** PHPMailer (obsługa powiadomień SMTP)
* **Serwer:** Apache (XAMPP/WAMP)

##  Instalacja i Konfiguracja

1.  **Baza Danych:**
    * Utwórz nową bazę danych w MySQL (np. `carrental`).
    * Zaimportuj strukturę tabel lub odwzoruj modele z kodu.
2.  **Konfiguracja połączenia:**
    * Edytuj plik `htdocs/config/database.php`:
        ```php
        $host = 'localhost';
        $db_name = 'carrental';
        $username = 'root';
        $password = '';
        ```
3.  **Konfiguracja SMTP (E-mail):**
    * Edytuj plik `htdocs/config/email_config.php` i wprowadź dane swojego serwera SMTP (np. Gmail), aby działały powiadomienia i reset hasła.
4.  **Uruchomienie:**
    * Skopiuj pliki do katalogu serwera (np. `xampp/htdocs/`).
    * Uruchom serwer Apache i MySQL.
    * Otwórz przeglądarkę pod adresem `http://localhost/carrental`.

##  Struktura Projektu

* `/admin` - Logika i widoki panelu administratora.
* `/customer` - Logika i widoki panelu klienta.
* `/config` - Pliki konfiguracyjne bazy danych i poczty.
* `/includes` - Biblioteki zewnętrzne (PHPMailer) i wspólne komponenty (header/footer).
