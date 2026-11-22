-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Nov 17, 2026 at 10:28 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `barber_brew`
--

-- --------------------------------------------------------

--
-- Table structure for table `barber_profiles`
--

CREATE TABLE `barber_profiles` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `bio` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience_years` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barber_profiles`
--

INSERT INTO `barber_profiles` (`id`, `user_id`, `bio`, `experience_years`, `is_available`) VALUES
(1, 2, 'Barber untuk pengujian sistem Barber & Brew.', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `barber_working_hours`
--

CREATE TABLE `barber_working_hours` (
  `id` bigint UNSIGNED NOT NULL,
  `barber_id` bigint UNSIGNED NOT NULL,
  `weekday` tinyint UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_day_off` tinyint(1) NOT NULL DEFAULT '0'
) ;

--
-- Dumping data for table `barber_working_hours`
--

INSERT INTO `barber_working_hours` (`id`, `barber_id`, `weekday`, `start_time`, `end_time`, `is_day_off`) VALUES
(1, 1, 1, '09:00:00', '17:00:00', 0),
(2, 1, 2, '09:00:00', '17:00:00', 0),
(3, 1, 3, '09:00:00', '17:00:00', 0),
(4, 1, 4, '09:00:00', '17:00:00', 0),
(5, 1, 5, '09:00:00', '17:00:00', 0),
(6, 1, 6, '09:00:00', '17:00:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `barber_id` bigint UNSIGNED NOT NULL,
  `service_id` bigint UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `service_price_snapshot` decimal(12,2) NOT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `customer_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `customer_id`, `barber_id`, `service_id`, `booking_date`, `start_time`, `end_time`, `service_price_snapshot`, `status`, `customer_note`, `created_at`) VALUES
(1, 1, 1, 1, '2026-11-18', '10:00:00', '10:30:00', '50000.00', 'completed', 'Uji reservasi Barber & Brew', '2026-11-17 08:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `cafe_categories`
--

CREATE TABLE `cafe_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cafe_categories`
--

INSERT INTO `cafe_categories` (`id`, `name`) VALUES
(1, 'Coffee'),
(2, 'Non Coffee'),
(3, 'Snack');

-- --------------------------------------------------------

--
-- Table structure for table `cafe_products`
--

CREATE TABLE `cafe_products` (
  `id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `stock` int UNSIGNED NOT NULL DEFAULT '0',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cafe_products`
--

INSERT INTO `cafe_products` (`id`, `category_id`, `name`, `description`, `price`, `stock`, `is_available`, `created_at`) VALUES
(1, 1, 'Americano', 'Espresso dengan air.', '25000.00', 38, 1, '2026-11-15 07:32:04'),
(2, 1, 'Cafe Latte', 'Espresso dengan steamed milk.', '30000.00', 40, 1, '2026-11-15 07:32:04'),
(3, 1, 'Mocha', 'Kopi dan cokelat.', '32000.00', 30, 1, '2026-11-15 07:32:04'),
(4, 2, 'Matcha Latte', 'Matcha dengan susu.', '30000.00', 25, 1, '2026-11-15 07:32:04'),
(5, 2, 'Lemon Tea', 'Teh lemon segar.', '20000.00', 30, 1, '2026-11-15 07:32:04'),
(6, 3, 'Butter Croissant', 'Pastry renyah.', '22000.00', 19, 1, '2026-11-15 07:32:04');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint UNSIGNED NOT NULL,
  `order_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `booking_id` bigint UNSIGNED DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('awaiting_payment','paid','processing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'awaiting_payment',
  `payment_status` enum('pending','paid','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `booking_id`, `subtotal`, `total`, `status`, `payment_status`, `created_at`) VALUES
(1, 'BB26111674E561', 1, NULL, '72000.00', '72000.00', 'paid', 'paid', '2026-11-16 14:30:09'),
(2, 'BB2611170F5C2A', 1, 1, '50000.00', '50000.00', 'completed', 'paid', '2026-11-17 08:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `item_type` enum('service','cafe_product') COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `item_name_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` smallint UNSIGNED NOT NULL DEFAULT '1',
  `line_total` decimal(12,2) NOT NULL
) ;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_type`, `service_id`, `product_id`, `item_name_snapshot`, `unit_price`, `quantity`, `line_total`) VALUES
(1, 1, 'cafe_product', NULL, 1, 'Americano', '25000.00', 2, '50000.00'),
(2, 1, 'cafe_product', NULL, 6, 'Butter Croissant', '22000.00', 1, '22000.00'),
(3, 2, 'service', 1, NULL, 'Haircut Classic', '50000.00', 1, '50000.00');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `method` enum('cash','transfer','qris') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reference_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `method`, `amount`, `status`, `reference_code`, `paid_at`, `created_at`) VALUES
(1, 1, 'cash', '72000.00', 'paid', NULL, '2026-11-16 21:32:16', '2026-11-16 14:30:09'),
(2, 2, 'cash', '50000.00', 'paid', NULL, '2026-11-17 15:38:11', '2026-11-17 08:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) NOT NULL,
  `duration_minutes` smallint UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `duration_minutes`, `is_active`, `created_at`) VALUES
(1, 'Haircut Classic', 'Potongan rambut pria modern dengan finishing styling.', '50000.00', 30, 1, '2026-11-15 07:32:03'),
(2, 'Haircut + Shave', 'Potong rambut dan cukur jenggot.', '85000.00', 45, 1, '2026-11-15 07:32:03'),
(3, 'Hair Wash', 'Cuci rambut dan pijatan ringan.', '30000.00', 15, 1, '2026-11-15 07:32:03'),
(4, 'Beard Trim', 'Merapikan jenggot.', '35000.00', 20, 1, '2026-11-15 07:32:03'),
(5, 'Hair Styling', 'Finishing styling.', '25000.00', 15, 1, '2026-11-15 07:32:03'),
(6, 'Premium Package', 'Haircut, hair wash, dan styling.', '100000.00', 60, 1, '2026-11-15 07:32:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','barber','customer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Customer Demo', 'customer@barberbrew.test', '081300000001', '$2y$10$18qVMm.8v8fcpyFoSW7/j.aVRmD5UN3aqxKHDBGjlC/Oak7eHTqh.', 'customer', 1, '2026-11-16 13:42:56', '2026-11-16 13:42:56'),
(2, 'Barber Demo', 'barber@barberbrew.test', '081300000002', '$2y$10$0CbtSKHoQhtOz1gR9Zzx8Otp5wLX1ws95cjPVn60x/nKNLGZOhUgC', 'barber', 1, '2026-11-16 14:02:51', '2026-11-16 14:02:51'),
(3, 'Admin Demo', 'admin@barberbrew.test', '081300000003', '$2y$10$cItsisT7N8xTq2iKpo2Lo.MD0h0S71bLdX3vzgG3ZXYoEkER7RaRm', 'admin', 1, '2026-11-16 14:10:05', '2026-11-16 14:10:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barber_profiles`
--
ALTER TABLE `barber_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `barber_working_hours`
--
ALTER TABLE `barber_working_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_barber_weekday` (`barber_id`,`weekday`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_customer` (`customer_id`),
  ADD KEY `fk_booking_service` (`service_id`),
  ADD KEY `idx_booking_slot` (`barber_id`,`booking_date`,`start_time`,`end_time`,`status`);

--
-- Indexes for table `cafe_categories`
--
ALTER TABLE `cafe_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `cafe_products`
--
ALTER TABLE `cafe_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_product_category` (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `fk_order_customer` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_order` (`order_id`),
  ADD KEY `fk_item_service` (`service_id`),
  ADD KEY `fk_item_product` (`product_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payment_order` (`order_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barber_profiles`
--
ALTER TABLE `barber_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `barber_working_hours`
--
ALTER TABLE `barber_working_hours`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cafe_categories`
--
ALTER TABLE `cafe_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cafe_products`
--
ALTER TABLE `cafe_products`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barber_profiles`
--
ALTER TABLE `barber_profiles`
  ADD CONSTRAINT `fk_barber_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `barber_working_hours`
--
ALTER TABLE `barber_working_hours`
  ADD CONSTRAINT `fk_hours_barber` FOREIGN KEY (`barber_id`) REFERENCES `barber_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_barber` FOREIGN KEY (`barber_id`) REFERENCES `barber_profiles` (`id`),
  ADD CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_booking_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `cafe_products`
--
ALTER TABLE `cafe_products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `cafe_categories` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `cafe_products` (`id`),
  ADD CONSTRAINT `fk_item_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
