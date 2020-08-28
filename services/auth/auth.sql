SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `access_codes`
--

CREATE TABLE `access_codes` (
  `ID` int(11) NOT NULL,
  `access_code` varchar(16) NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 - access code is valid, 0 - access code is invalid',
  `used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 - not used; 1 - used',
  `session_id` int(11) NOT NULL COMMENT 'ID of the session',
  `request_url` varchar(256) NOT NULL COMMENT 'the (frontend) url that requested this token',
  `audience` varchar(256) NOT NULL COMMENT 'the url of the backend this token should be for',
  `token_id` varchar(64) NOT NULL COMMENT 'the unique id of the token',
  `expire_date` datetime NOT NULL COMMENT 'the expire date of the access token'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `access_tokens_revoked`
--

CREATE TABLE `access_tokens_revoked` (
  `ID` int(11) NOT NULL,
  `token_id` varchar(64) NOT NULL COMMENT 'the token''s ID'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `ID` int(11) NOT NULL,
  `guid` varchar(36) NOT NULL COMMENT 'the unique id of the session',
  `valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'whether this local session is valid',
  `session_name` varchar(32) NOT NULL COMMENT 'name for the session',
  `user` int(11) NOT NULL COMMENT 'id of the user from the table users',
  `random_password_hash` varchar(255) NOT NULL DEFAULT '' COMMENT 'security parameter 1 of the cookie',
  `random_selector_hash` varchar(255) NOT NULL DEFAULT '' COMMENT 'security parameter 2 of the cookie',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'the date the session has been created',
  `expires_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'the date this session will expire'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------


--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_codes`
--
ALTER TABLE `access_codes`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `access_tokens_revoked`
--
ALTER TABLE `access_tokens_revoked`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `name` (`role_name`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `guid` (`guid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_to_roles`
--
ALTER TABLE `user_to_roles`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `username_role` (`user`,`role_name`) USING BTREE;

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_codes`
--
ALTER TABLE `access_codes`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `access_tokens_revoked`
--
ALTER TABLE `access_tokens_revoked`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
