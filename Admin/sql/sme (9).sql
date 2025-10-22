-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 26, 2025 at 12:11 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sme`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_business`
--

CREATE TABLE `tbl_business` (
  `id_business` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `establishment_name` varchar(255) NOT NULL,
  `capital` varchar(255) NOT NULL,
  `date_of_establishment` date NOT NULL,
  `business_type` varchar(255) NOT NULL,
  `nature_of_business` varchar(255) NOT NULL,
  `sabang_location` varchar(255) NOT NULL,
  `lot_street_business` varchar(255) NOT NULL,
  `DTI` varchar(255) NOT NULL,
  `business_permit` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_business`
--

INSERT INTO `tbl_business` (`id_business`, `id_user`, `establishment_name`, `capital`, `date_of_establishment`, `business_type`, `nature_of_business`, `sabang_location`, `lot_street_business`, `DTI`, `business_permit`) VALUES
(5, 15, 'Tonets chickery', '20000', '2025-08-27', 'Retail', 'Clothing', 'Sabang Market', 'phase', 'dti/3154_alex.png', 'business_permit/7982_alex.png'),
(6, 16, 'paramil', '20000.00', '2024-12-05', 'Retail', 'Food', 'Sabang Market', 'phase', 'dti/7712_alex.png', 'business_permit/5477_alex.png'),
(8, 18, 'Yakimix', '20000.00', '2025-09-17', 'Retail', 'Food', 'Sabang Plaza', 'lot45/33', 'temp/4326_dti testing.jpg', 'temp/4789_business permit testing.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_calendar`
--

CREATE TABLE `tbl_calendar` (
  `id_calendar` int(11) NOT NULL,
  `id_delivery` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('pending','delivered','canceled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_delivery`
--

CREATE TABLE `tbl_delivery` (
  `id_delivery` int(11) NOT NULL,
  `id_supplier` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `expected_date` date NOT NULL,
  `status` enum('pending','delivered','canceled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_delivery_items`
--

CREATE TABLE `tbl_delivery_items` (
  `id_delivery_item` int(11) NOT NULL,
  `id_delivery` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_item`
--

CREATE TABLE `tbl_item` (
  `id_item` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `name_item` varchar(255) NOT NULL,
  `description_item` text DEFAULT NULL,
  `category_item` varchar(100) DEFAULT NULL,
  `size_item` varchar(50) DEFAULT NULL,
  `color_item` varchar(50) DEFAULT NULL,
  `stock_quantity_item` int(11) DEFAULT 0,
  `purchase_price_item` decimal(12,2) NOT NULL,
  `is_new_item` tinyint(1) DEFAULT 1,
  `availability_status_item` enum('available','out_of_stock') DEFAULT 'available',
  `image_url_item` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pending_users`
--

CREATE TABLE `tbl_pending_users` (
  `id_pending` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `birthday` date NOT NULL,
  `birth_place` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `barangay` varchar(255) NOT NULL,
  `lot_street` varchar(255) NOT NULL,
  `prefix` varchar(10) NOT NULL,
  `seven_digit` varchar(7) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `account_type` varchar(50) NOT NULL DEFAULT 'pending',
  `img` varchar(255) DEFAULT NULL,
  `establishment_name` varchar(255) DEFAULT NULL,
  `capital` decimal(15,2) DEFAULT NULL,
  `date_of_establishment` date DEFAULT NULL,
  `business_type` varchar(255) DEFAULT NULL,
  `nature_of_business` varchar(255) DEFAULT NULL,
  `sabang_location` varchar(255) DEFAULT NULL,
  `lot_street_business` varchar(255) DEFAULT NULL,
  `DTI` varchar(255) DEFAULT NULL,
  `business_permit` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_pending_users`
--

INSERT INTO `tbl_pending_users` (`id_pending`, `first_name`, `middle_name`, `last_name`, `birthday`, `birth_place`, `city`, `barangay`, `lot_street`, `prefix`, `seven_digit`, `email`, `password`, `account_type`, `img`, `establishment_name`, `capital`, `date_of_establishment`, `business_type`, `nature_of_business`, `sabang_location`, `lot_street_business`, `DTI`, `business_permit`) VALUES
(19, 'Userahhhtwo', 'Panama', 'kah', '1994-07-06', 'san antonio', 'Lipa City', 'Anilao', 'lot45/33', '0817', '0875691', 'user@gmail.com', 'user', 'pending', '', 'Food', 20000.00, '2025-09-17', 'Retail', 'Food', 'Sabang Centro', 'lot45/33', 'temp/4983_alex.png', 'temp/1847_SILENT HILL DF.png'),
(20, 'Patrica Kerry', 'mmmmm', 'melan', '2000-06-06', 'san antonio', 'Lipa City', 'Duhatan', 'lot45/33', '0817', '0875691', 'kea@gmail.com', 'user', 'pending', '', 'Food', 20000.00, '2025-09-03', 'Retail', 'Food', 'Sabang Market', 'lot45/33', 'temp/dti testing.jpg', 'temp/business permit testing.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_purchase`
--

CREATE TABLE `tbl_purchase` (
  `id_purchase` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `date_time` datetime DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `status` enum('paid','canceled','refunded') DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_supplier`
--

CREATE TABLE `tbl_supplier` (
  `id_supplier` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `id_user` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `birthday` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `barangay` varchar(255) NOT NULL,
  `lot_street` varchar(255) NOT NULL,
  `prefix` varchar(255) NOT NULL,
  `seven_digit` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `attempt` varchar(255) NOT NULL,
  `log_time` varchar(255) NOT NULL,
  `account_type` varchar(255) NOT NULL,
  `img` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`id_user`, `first_name`, `middle_name`, `last_name`, `birthday`, `birth_place`, `city`, `barangay`, `lot_street`, `prefix`, `seven_digit`, `email`, `password`, `attempt`, `log_time`, `account_type`, `img`) VALUES
(8, 'Admin', 'De', 'Losreyes', '0000-00-00', NULL, 'Lipa City', 'Anilao', 'lot45/33', '0813', '0875691', 'admin@gmail.com', 'admin', '', '', '1', '0'),
(15, 'User', 'Using', 'Userining', '2004-02-12', 'San antonio ', 'Lipa City', 'Bagong Pook', 'Phase 3 lot 34/29', '0817', '0820723', 'user@gmail.com', 'user', '', '', '2', ''),
(16, 'Kirk', 'Estrada', 'Papiolek', '1996-07-10', 'San antonio ', 'Lipa City', 'Banaybanay', 'Phase 3 lot 34/29', '0905', '0820723', 'krik@gmail.com', 'user', '', '', '2', ''),
(18, 'Ingrid', 'lake', 'Campel', '1996-02-07', 'san antonio', 'Tanauan City', 'Darasa', 'lot45/33', '0905', '0875691', 'Amy@gmail.com', 'user', '', '', '2', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_business`
--
ALTER TABLE `tbl_business`
  ADD PRIMARY KEY (`id_business`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `tbl_calendar`
--
ALTER TABLE `tbl_calendar`
  ADD PRIMARY KEY (`id_calendar`),
  ADD KEY `id_delivery` (`id_delivery`);

--
-- Indexes for table `tbl_delivery`
--
ALTER TABLE `tbl_delivery`
  ADD PRIMARY KEY (`id_delivery`),
  ADD KEY `id_supplier` (`id_supplier`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `tbl_delivery_items`
--
ALTER TABLE `tbl_delivery_items`
  ADD PRIMARY KEY (`id_delivery_item`),
  ADD KEY `id_delivery` (`id_delivery`),
  ADD KEY `id_item` (`id_item`);

--
-- Indexes for table `tbl_item`
--
ALTER TABLE `tbl_item`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `tbl_pending_users`
--
ALTER TABLE `tbl_pending_users`
  ADD PRIMARY KEY (`id_pending`);

--
-- Indexes for table `tbl_purchase`
--
ALTER TABLE `tbl_purchase`
  ADD PRIMARY KEY (`id_purchase`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_item` (`id_item`);

--
-- Indexes for table `tbl_supplier`
--
ALTER TABLE `tbl_supplier`
  ADD PRIMARY KEY (`id_supplier`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_business`
--
ALTER TABLE `tbl_business`
  MODIFY `id_business` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_calendar`
--
ALTER TABLE `tbl_calendar`
  MODIFY `id_calendar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_delivery`
--
ALTER TABLE `tbl_delivery`
  MODIFY `id_delivery` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_delivery_items`
--
ALTER TABLE `tbl_delivery_items`
  MODIFY `id_delivery_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_item`
--
ALTER TABLE `tbl_item`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_pending_users`
--
ALTER TABLE `tbl_pending_users`
  MODIFY `id_pending` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `tbl_purchase`
--
ALTER TABLE `tbl_purchase`
  MODIFY `id_purchase` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_supplier`
--
ALTER TABLE `tbl_supplier`
  MODIFY `id_supplier` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_business`
--
ALTER TABLE `tbl_business`
  ADD CONSTRAINT `tbl_business_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_calendar`
--
ALTER TABLE `tbl_calendar`
  ADD CONSTRAINT `tbl_calendar_ibfk_1` FOREIGN KEY (`id_delivery`) REFERENCES `tbl_delivery` (`id_delivery`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_delivery`
--
ALTER TABLE `tbl_delivery`
  ADD CONSTRAINT `tbl_delivery_ibfk_1` FOREIGN KEY (`id_supplier`) REFERENCES `tbl_supplier` (`id_supplier`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_delivery_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_delivery_items`
--
ALTER TABLE `tbl_delivery_items`
  ADD CONSTRAINT `tbl_delivery_items_ibfk_1` FOREIGN KEY (`id_delivery`) REFERENCES `tbl_delivery` (`id_delivery`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_delivery_items_ibfk_2` FOREIGN KEY (`id_item`) REFERENCES `tbl_item` (`id_item`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_item`
--
ALTER TABLE `tbl_item`
  ADD CONSTRAINT `tbl_item_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_purchase`
--
ALTER TABLE `tbl_purchase`
  ADD CONSTRAINT `tbl_purchase_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_purchase_ibfk_2` FOREIGN KEY (`id_item`) REFERENCES `tbl_item` (`id_item`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_supplier`
--
ALTER TABLE `tbl_supplier`
  ADD CONSTRAINT `tbl_supplier_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
