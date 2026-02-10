-- phpMyAdmin SQL Dump
-- Struktura bazy danych dla projektu CarRental
-- Wersja oczyszczona z danych (Schema only)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Baza danych: `if0_40498177_carrental`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Address`
--

CREATE TABLE `Address` (
  `address_id` int(11) NOT NULL,
  `street` varchar(100) NOT NULL,
  `number` varchar(10) NOT NULL,
  `city` varchar(50) NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `country` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Customer`
--

CREATE TABLE `Customer` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `registration_date` date NOT NULL,
  `account_status` enum('active','suspended','inactive') DEFAULT 'active',
  `driver_license_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Employee`
--

CREATE TABLE `Employee` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `position` enum('admin','manager','service_technician','rental_agent') NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `hire_date` date NOT NULL,
  `address_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Insurance`
--

CREATE TABLE `Insurance` (
  `insurance_id` int(11) NOT NULL,
  `policy_number` varchar(50) NOT NULL,
  `insurer` varchar(100) NOT NULL,
  `insurance_type` enum('liability','comprehensive','collision','theft') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `premium` decimal(8,2) NOT NULL,
  `insurance_period` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Location`
--

CREATE TABLE `Location` (
  `location_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `location_type` enum('main_warehouse','rental_branch','service_center') NOT NULL,
  `address_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Payment`
--

CREATE TABLE `Payment` (
  `payment_id` int(11) NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `payment_type` enum('cash','card','transfer','online') NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `rental_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Penalty`
--

CREATE TABLE `Penalty` (
  `penalty_id` int(11) NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `reason` varchar(200) NOT NULL,
  `damage_description` text DEFAULT NULL,
  `imposition_date` date NOT NULL,
  `payment_deadline` date NOT NULL,
  `penalty_status` enum('pending','paid','cancelled','overdue') DEFAULT 'pending',
  `damage_photos` varchar(500) DEFAULT NULL,
  `rental_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Rental`
--

CREATE TABLE `Rental` (
  `rental_id` int(11) NOT NULL,
  `rental_date` datetime DEFAULT current_timestamp(),
  `planned_return_date` date NOT NULL,
  `actual_return_date` date DEFAULT NULL,
  `rental_status` enum('active','completed','cancelled','overdue') DEFAULT 'active',
  `rental_cost` decimal(8,2) NOT NULL,
  `deposit` decimal(8,2) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `pickup_location_id` int(11) NOT NULL,
  `return_location_id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Reservation`
--

CREATE TABLE `Reservation` (
  `reservation_id` int(11) NOT NULL,
  `reservation_date` datetime DEFAULT current_timestamp(),
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reservation_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Service`
--

CREATE TABLE `Service` (
  `service_id` int(11) NOT NULL,
  `service_type` enum('repair','maintenance','inspection','accident_repair') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `cost` decimal(8,2) DEFAULT 0.00,
  `service_status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `mileage_at_service` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `vehicle_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Users`
--

CREATE TABLE `Users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('admin','customer') NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Poland',
  `language` varchar(10) NOT NULL DEFAULT 'pl'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `Vehicle`
--

CREATE TABLE `Vehicle` (
  `vehicle_id` int(11) NOT NULL,
  `production_year` year(4) NOT NULL,
  `license_plate` varchar(15) NOT NULL,
  `vin` varchar(17) NOT NULL,
  `color` varchar(30) NOT NULL,
  `mileage` int(11) NOT NULL DEFAULT 0,
  `technical_condition` enum('excellent','good','fair','poor') DEFAULT 'good',
  `fuel_type` enum('petrol','diesel','electric','hybrid') DEFAULT NULL,
  `transmission` enum('manual','automatic') DEFAULT NULL,
  `status` enum('available','rented','reserved','maintenance','archived') DEFAULT 'available',
  `purchase_date` date NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `model_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `VehicleModel`
--

CREATE TABLE `VehicleModel` (
  `model_id` int(11) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `vehicle_type` enum('sedan','suv','hatchback','coupe','convertible','van','truck') NOT NULL,
  `engine_capacity` decimal(3,1) NOT NULL,
  `fuel_type` enum('petrol','diesel','electric','hybrid') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `VehicleReturnInfo`
--

CREATE TABLE `VehicleReturnInfo` (
  `return_id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `fuel_level` enum('full','3/4','1/2','1/4','empty') NOT NULL,
  `vehicle_condition` enum('excellent','good','fair','poor','damaged') NOT NULL,
  `requires_repair` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `inspector_notes` text DEFAULT NULL,
  `return_date` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `Address`
--
ALTER TABLE `Address`
  ADD PRIMARY KEY (`address_id`);

--
-- Indeksy dla tabeli `Customer`
--
ALTER TABLE `Customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `driver_license_number` (`driver_license_number`),
  ADD KEY `idx_customer_email` (`email`);

--
-- Indeksy dla tabeli `Employee`
--
ALTER TABLE `Employee`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `address_id` (`address_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indeksy dla tabeli `Insurance`
--
ALTER TABLE `Insurance`
  ADD PRIMARY KEY (`insurance_id`),
  ADD UNIQUE KEY `policy_number` (`policy_number`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indeksy dla tabeli `Location`
--
ALTER TABLE `Location`
  ADD PRIMARY KEY (`location_id`),
  ADD UNIQUE KEY `address_id` (`address_id`);

--
-- Indeksy dla tabeli `Payment`
--
ALTER TABLE `Payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `rental_id` (`rental_id`);

--
-- Indeksy dla tabeli `Penalty`
--
ALTER TABLE `Penalty`
  ADD PRIMARY KEY (`penalty_id`),
  ADD KEY `rental_id` (`rental_id`),
  ADD KEY `idx_penalty_status` (`penalty_status`);

--
-- Indeksy dla tabeli `Rental`
--
ALTER TABLE `Rental`
  ADD PRIMARY KEY (`rental_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `pickup_location_id` (`pickup_location_id`),
  ADD KEY `return_location_id` (`return_location_id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `idx_rental_dates` (`rental_date`,`planned_return_date`);

--
-- Indeksy dla tabeli `Reservation`
--
ALTER TABLE `Reservation`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_reservation_dates` (`start_date`,`end_date`);

--
-- Indeksy dla tabeli `Service`
--
ALTER TABLE `Service`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `idx_service_status` (`service_status`);

--
-- Indeksy dla tabeli `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_users_email` (`email`);

--
-- Indeksy dla tabeli `Vehicle`
--
ALTER TABLE `Vehicle`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD UNIQUE KEY `vin` (`vin`),
  ADD KEY `model_id` (`model_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `idx_vehicle_status` (`status`);

--
-- Indeksy dla tabeli `VehicleModel`
--
ALTER TABLE `VehicleModel`
  ADD PRIMARY KEY (`model_id`);

--
-- Indeksy dla tabeli `VehicleReturnInfo`
--
ALTER TABLE `VehicleReturnInfo`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `rental_id` (`rental_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT dla tabeli `Address`
--
ALTER TABLE `Address`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Customer`
--
ALTER TABLE `Customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Employee`
--
ALTER TABLE `Employee`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Insurance`
--
ALTER TABLE `Insurance`
  MODIFY `insurance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Location`
--
ALTER TABLE `Location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Payment`
--
ALTER TABLE `Payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Penalty`
--
ALTER TABLE `Penalty`
  MODIFY `penalty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Rental`
--
ALTER TABLE `Rental`
  MODIFY `rental_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Reservation`
--
ALTER TABLE `Reservation`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Service`
--
ALTER TABLE `Service`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Users`
--
ALTER TABLE `Users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `Vehicle`
--
ALTER TABLE `Vehicle`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `VehicleModel`
--
ALTER TABLE `VehicleModel`
  MODIFY `model_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `VehicleReturnInfo`
--
ALTER TABLE `VehicleReturnInfo`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ograniczenia dla zrzutów tabel
--

--
-- Ograniczenia dla tabeli `Employee`
--
ALTER TABLE `Employee`
  ADD CONSTRAINT `Employee_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `Address` (`address_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Employee_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `Location` (`location_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Insurance`
--
ALTER TABLE `Insurance`
  ADD CONSTRAINT `Insurance_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `Vehicle` (`vehicle_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Location`
--
ALTER TABLE `Location`
  ADD CONSTRAINT `Location_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `Address` (`address_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Payment`
--
ALTER TABLE `Payment`
  ADD CONSTRAINT `Payment_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `Rental` (`rental_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Penalty`
--
ALTER TABLE `Penalty`
  ADD CONSTRAINT `Penalty_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `Rental` (`rental_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Rental`
--
ALTER TABLE `Rental`
  ADD CONSTRAINT `Rental_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `Customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Rental_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `Vehicle` (`vehicle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Rental_ibfk_3` FOREIGN KEY (`pickup_location_id`) REFERENCES `Location` (`location_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Rental_ibfk_4` FOREIGN KEY (`return_location_id`) REFERENCES `Location` (`location_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Rental_ibfk_5` FOREIGN KEY (`reservation_id`) REFERENCES `Reservation` (`reservation_id`) ON DELETE SET NULL;

--
-- Ograniczenia dla tabeli `Reservation`
--
ALTER TABLE `Reservation`
  ADD CONSTRAINT `Reservation_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `Customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Reservation_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `Vehicle` (`vehicle_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Service`
--
ALTER TABLE `Service`
  ADD CONSTRAINT `Service_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `Vehicle` (`vehicle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Service_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `Location` (`location_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Users`
--
ALTER TABLE `Users`
  ADD CONSTRAINT `Users_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `Customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Users_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `Employee` (`employee_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `Vehicle`
--
ALTER TABLE `Vehicle`
  ADD CONSTRAINT `Vehicle_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `VehicleModel` (`model_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Vehicle_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `Location` (`location_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;