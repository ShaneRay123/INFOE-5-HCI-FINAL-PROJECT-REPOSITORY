-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql102.infinityfree.com
-- Generation Time: Jan 08, 2026 at 03:25 AM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 7.2.22
CREATE DATABASE IF NOT EXISTS `if0_40312270_db_userdata`;
USE `if0_40312270_db_userdata`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40312270_db_userdata`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `counselor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','accepted','declined','cancelled','successful','reschedule') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `title`, `description`, `created_by`, `created_at`) VALUES
(10, 'SCHOOL-BASED WELL-BEING INSTRUMENTS', 'DATA PRIVACY NOTICE AND CONSENT FORM FOR PSYCHOLOGICAL TESTING\\\\\\\\r\\\\\\\\n\\\\\\\\r\\\\\\\\nUniversity of Antique (UA) collects information from the students such as name, sex, contact number, and degree program/grade level/education. UA collects, uses and/or processes the information through the Answer Document/Sheet and Psychological Test Results. The collected information will be utilized as a basis for providing data for more effective counseling, determining the status of each student&amp;amp;#039;s mental ability, personality, aptitude and/or ability, development of appropriate guidance programs to address issues/concerns of students, or for other purposes declared by the test taker. The information will be used solely for record and monitoring purposes. The collected information will not be shared by UA with external parties. Only authorized UA personnel are granted access to personal information collected by UA. Personal information collected through these preliminary registration forms are kept in a secured data facility. We use reasonable security safeguards to protect information loss, unauthorized access. use or disclosure. Under the Data Privacy Act of 2012, data subject refers to an individual whose personal information is collected and processed. We are bound to observe and respect your privacy rights, including your right to information, right to access, ight to correct, right to remove, right to damages and right to data portability. By indicating and submitting your personal information in this preliminary registration form, you authorize UA to collect and store your personal information, for the period allowed under the applicable law and regulations, for the aforementioned purposes. You acknowledge that the collection and processing of your personal information is necessary for such purposes. You are aware of your rights under Data Privacy Act, including the right to be informed, to access, to object, to erasure or blocking, to damages, to file a complaint, to rectify and to data portability, and understand that there are procedures, conditions and exceptions to be complied with in order to our Data Protection Officer at doolantiquespride.edu.ph.\\\\\\\\r\\\\\\\\n\\\\\\\\r\\\\\\\\nThe undersigned is fully aware of the foregoing declarations, and understands the necessity for UA to collect, process and store information to endure the genuine and legal interests as an educational institution and its ability to fully and effectively carry out its responsibilities are met.\\\\\\\\r\\\\\\\\n\\\\\\\\r\\\\\\\\nFurther, the undersigned abides and consents to the foregoing freely and voluntarily.\\\\\\\\r\\\\\\\\n\\\\\\\\r\\\\\\\\nDirection: Kindly answer the following items honestly and completely as possible for us to accurately assess your mental health and well-being status so that we can design an appropriate support and intervention for various mental health needs.', 8, '2025-11-18 22:37:34');

-- --------------------------------------------------------

--
-- Table structure for table `counselor_availability`
--

CREATE TABLE `counselor_availability` (
  `id` int(11) UNSIGNED NOT NULL,
  `counselor_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Available','Blocked') DEFAULT 'Available',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `counselor_availability`
--

INSERT INTO `counselor_availability` (`id`, `counselor_id`, `available_date`, `start_time`, `end_time`, `status`, `created_at`) VALUES
(2, 8, '2025-11-25', '08:00:00', '11:00:00', 'Available', '2025-11-22 05:24:27'),
(3, 8, '2025-11-25', '13:00:00', '17:00:00', 'Available', '2025-11-22 05:24:35'),
(4, 8, '2025-11-27', '08:00:00', '11:00:00', 'Available', '2025-11-22 07:59:21'),
(5, 8, '2025-11-27', '15:00:00', '16:30:00', 'Available', '2025-11-22 07:59:49'),
(6, 8, '2025-12-17', '08:30:00', '16:30:00', 'Available', '2025-12-17 06:19:49'),
(7, 8, '2025-12-18', '08:00:00', '14:30:00', 'Available', '2025-12-17 06:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','text','scale','section_header') NOT NULL,
  `options` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `assessment_id`, `question_text`, `question_type`, `options`) VALUES
(91, 10, 'How likely is it that you will attempt suicide someday? (select one only)', 'multiple_choice', '[\"0. Never\",\"1. No chance at all\",\"2. Rather unlikely\",\"3. Unlikely\",\"4. Likely\",\"5. Rather likely\",\"6. Very likely\"]'),
(12, 5, '33333333333', 'scale', NULL),
(13, 5, '333', 'scale', NULL),
(89, 10, 'How often have you thought about killing yourself in the past year? (select one only)', 'multiple_choice', '[\"1. Never\",\"2. Rarely (1 time)\",\"3. Sometimes (2 times)\",\"4. Often (3-4 times)\",\"5. Very Often (5 or more times)\"]'),
(90, 10, 'Have you ever told someone that you were going to commit suicide, or that you might do it? (select one only)', 'multiple_choice', '[\"1. No\",\"2a. Yes, at one time, but did not really want to die\",\"2b. Yes, at one time, and really wanted to die\",\"3a. Yes, more than once, but did not want to do it\",\"3b. Yes, more than once, and really wanted to do it\"]'),
(88, 10, 'Have you ever thought about or attempted to kill yourself? (select one only)', 'multiple_choice', '[\"1. Never\",\"2. It was just a brief passing thought\",\"3a. I have had a plan at least once to kill myself but did not try to do it\",\"3b. I have had a plan at least once to kill myself and really wanted to die\",\"4a. I have attempted to kill myself, but did not want to die\",\"4b. I have attempted to kill myself, and really hoped to die\"]'),
(80, 10, 'Worrying too much about different things', 'scale', NULL),
(81, 10, 'Trouble relaxing', 'scale', NULL),
(82, 10, 'Being so restless that it is hard to sit still', 'scale', NULL),
(83, 10, 'Becoming easily annoyed or irritabl', 'scale', NULL),
(84, 10, 'Feeling afraid, as if something awful might happen', 'scale', NULL),
(85, 10, 'If you checked off any problems, how difficult have these made it for you to do your work, take care of things at home, or get along with other people! (Select one)', 'multiple_choice', '[\"Not difficult at all\",\"Somewhat difficult\",\"Very Difficult\",\"Extremely Difficult\"]'),
(86, 10, 'SBQ-R SUICIDE BEHAVIORS QUESTIONNAIRE-REVISED', 'section_header', 'Instructions: Please check the number beside the statement or phrase that best applies to you.'),
(79, 10, 'Not being able to stop or control worrying', 'scale', NULL),
(78, 10, 'Feeling nervous, anxious, or on edge', 'scale', NULL),
(76, 10, 'GENERALIZED ANXIETY DISORDER-7', 'section_header', 'Over the last 2 weeks, how often have you been bothered by any of the following problems? Please rate each of the following items on a rating scale of 0-3.'),
(73, 10, 'Moving or speaking so slowly that other people could have noticed. Or the opposite being so fidgety or restless that you have been moving around a lot more than usual.', 'scale', NULL),
(74, 10, 'Thoughts that you would be better off dead, or of hurting yourself in some way.', 'scale', NULL),
(75, 10, 'If you checked off any problems, how difficult have these made it for you to do your work, take care of things at home, or get along with other people! (Select one)', 'multiple_choice', '[\"Not difficult at all\",\"Somewhat difficult\",\"Very Difficult\",\"Extremely Difficult\"]'),
(71, 10, 'Feeling bad about yourself or that you are a failure or have let yourself or your family down.', 'scale', NULL),
(72, 10, 'Trouble concentrating on things, such as reading the newspaper or watching television.', 'scale', NULL),
(66, 10, 'Little Interest or pleasure in doing things.', 'scale', NULL),
(67, 10, 'Feeling down, depressed, or hopeless.', 'scale', NULL),
(68, 10, 'Trouble falling or staying asleep, or sleeping too much.', 'scale', NULL),
(69, 10, 'Feeling tired or having little energy.', 'scale', NULL),
(70, 10, 'Poor appetite or overeating.', 'scale', NULL),
(64, 10, 'PATIENT HEALTH QUESTIONNAIRE (PHQ-9)', 'section_header', 'Over the last 2 weeks, how often have you been bothered by any of the following problems? Please rate each of the following items on a rating scale of 0-3.'),
(92, 10, 'Thank you very much.', 'section_header', 'Note: If you are feeling distressed and anxious today, you may seek professional help. You may visit the Guidance Center for assistance. Thank you very much.');

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `student_assessment_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `response_text` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`id`, `student_assessment_id`, `question_id`, `response_text`) VALUES
(46, 13, 22, 'Poorly'),
(45, 13, 21, 'Not sure'),
(44, 13, 20, 'Almost always'),
(43, 13, 19, '2'),
(42, 13, 18, 'Somewhat'),
(41, 13, 17, 'Sometimes'),
(40, 13, 16, 'Very poor'),
(39, 13, 15, 'Often'),
(38, 13, 14, '3'),
(47, 13, 23, 'Rarely'),
(48, 15, 14, '3'),
(49, 15, 15, 'Never'),
(50, 15, 17, 'Never'),
(51, 15, 18, 'No'),
(52, 15, 19, '5'),
(53, 15, 20, 'Rarely'),
(54, 15, 21, 'Mostly'),
(55, 15, 22, 'Very well'),
(56, 15, 23, 'Rarely'),
(57, 16, 6, '4'),
(58, 16, 7, '5'),
(59, 16, 9, '3'),
(60, 16, 10, '8'),
(61, 16, 11, '7'),
(62, 16, 25, '3'),
(63, 17, 14, '2'),
(64, 17, 15, 'Sometimes'),
(65, 17, 17, 'Rarely'),
(66, 17, 18, 'No'),
(67, 17, 19, '5'),
(68, 17, 20, 'Never'),
(69, 17, 21, 'No'),
(70, 17, 23, 'Sometimes'),
(71, 19, 27, '3'),
(72, 19, 28, '8'),
(73, 19, 30, '6'),
(74, 19, 31, '8'),
(75, 19, 32, '7'),
(76, 20, 33, '3'),
(77, 20, 34, '2'),
(78, 22, 33, '1'),
(79, 22, 34, '2'),
(80, 21, 33, '2'),
(81, 21, 34, '3'),
(82, 24, 33, '1'),
(83, 24, 34, '3'),
(84, 24, 36, '1'),
(85, 24, 37, '2'),
(86, 23, 33, '3'),
(87, 23, 34, '3'),
(88, 23, 36, '2'),
(89, 23, 37, '2'),
(90, 25, 39, '1'),
(91, 25, 40, '1'),
(92, 25, 41, '3'),
(93, 25, 42, '2'),
(94, 25, 43, '0'),
(95, 25, 44, '3'),
(96, 25, 45, '3'),
(97, 25, 46, '3'),
(98, 25, 47, '1'),
(99, 25, 48, 'Very Difficult'),
(100, 25, 50, '2'),
(101, 25, 51, '2'),
(102, 25, 52, '2'),
(103, 25, 53, '2'),
(104, 25, 54, '3'),
(105, 25, 55, '2'),
(106, 25, 56, '3'),
(107, 25, 57, 'Very Difficult'),
(108, 25, 59, '2. It was just a brief passing thought'),
(109, 25, 60, '5. Very Often (5 or more times)'),
(110, 25, 61, '1. No'),
(111, 25, 62, '6. Very likely');

-- --------------------------------------------------------

--
-- Table structure for table `security_log`
--

CREATE TABLE `security_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username_attempted` varchar(100) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `security_log`
--

INSERT INTO `security_log` (`id`, `user_id`, `username_attempted`, `event_type`, `description`, `ip_address`, `timestamp`) VALUES
(1, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 19:34:23'),
(2, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 19:35:18'),
(3, NULL, 'Ray@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '103.73.58.233', '2025-11-15 19:41:21'),
(4, 23, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 19:41:26'),
(5, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 19:41:45'),
(6, 23, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 19:46:28'),
(7, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 19:46:59'),
(8, NULL, 'admin2@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '103.73.58.233', '2025-11-15 20:12:38'),
(9, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 20:12:45'),
(10, NULL, 'Ray@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '103.73.58.233', '2025-11-15 20:50:50'),
(11, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-15 20:51:06'),
(12, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-16 03:37:08'),
(13, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-16 03:51:58'),
(14, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-16 03:55:16'),
(15, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 05:09:57'),
(16, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 05:20:48'),
(17, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 05:45:04'),
(18, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 05:48:32'),
(19, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 05:56:57'),
(20, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 06:02:56'),
(21, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 06:27:51'),
(22, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-16 06:29:19'),
(23, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '131.226.108.81', '2025-11-16 18:53:26'),
(24, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '131.226.108.81', '2025-11-16 18:57:46'),
(25, NULL, 'admin2@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '131.226.111.74', '2025-11-16 19:19:59'),
(26, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '131.226.111.74', '2025-11-16 19:20:10'),
(27, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '175.176.73.102', '2025-11-16 19:20:34'),
(28, NULL, 'Ray2@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '131.226.111.74', '2025-11-16 19:21:43'),
(29, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '131.226.111.74', '2025-11-16 19:21:51'),
(30, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-17 01:42:57'),
(31, NULL, 'Ray@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '103.73.58.233', '2025-11-17 03:19:09'),
(32, NULL, 'Ray@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '103.73.58.233', '2025-11-17 03:19:12'),
(33, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-17 03:19:21'),
(34, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '180.190.53.165', '2025-11-17 18:21:28'),
(35, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '180.190.53.165', '2025-11-17 18:58:17'),
(36, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 00:12:12'),
(37, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 03:49:50'),
(38, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 14:58:32'),
(39, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 15:48:12'),
(40, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:08:03'),
(41, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:09:05'),
(42, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:11:31'),
(43, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:11:57'),
(44, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:12:43'),
(45, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:23:43'),
(46, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:24:29'),
(47, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:53:50'),
(48, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 16:57:31'),
(49, 25, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 17:18:20'),
(50, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 17:19:29'),
(51, NULL, 'admin@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '49.145.172.167', '2025-11-18 17:31:16'),
(52, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.145.172.167', '2025-11-18 17:32:45'),
(53, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:30:28'),
(54, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:48:07'),
(55, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:48:46'),
(56, 27, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:49:04'),
(57, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:49:32'),
(58, 27, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:50:45'),
(59, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:52:51'),
(60, 27, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 18:53:11'),
(61, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 19:04:25'),
(62, 27, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 19:10:57'),
(63, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 19:30:55'),
(64, 27, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 19:32:24'),
(65, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-18 19:33:58'),
(66, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 20:29:24'),
(67, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:49:15'),
(68, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:49:23'),
(69, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:50:16'),
(70, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:50:39'),
(71, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:50:55'),
(72, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '175.176.64.28', '2025-11-18 21:51:46'),
(73, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:52:20'),
(74, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:53:10'),
(75, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 21:53:45'),
(76, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '222.127.188.112', '2025-11-18 21:55:33'),
(77, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 21:55:50'),
(78, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:06:59'),
(79, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:38:44'),
(80, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:41:54'),
(81, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:43:14'),
(82, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:51:30'),
(83, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:55:31'),
(84, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:56:34'),
(85, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 22:57:25'),
(86, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 23:00:59'),
(87, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 23:01:58'),
(88, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 23:03:19'),
(89, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '222.127.188.112', '2025-11-18 23:08:50'),
(90, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-19 01:44:29'),
(91, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-19 23:52:52'),
(92, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-20 00:36:37'),
(93, 28, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-20 00:40:17'),
(94, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-20 00:44:54'),
(95, 28, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-20 00:45:42'),
(96, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-20 02:09:02'),
(97, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-20 14:04:02'),
(98, 28, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 16:24:25'),
(99, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 16:30:54'),
(100, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 16:33:28'),
(101, NULL, 'yevgeny377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '27.110.160.143', '2025-11-20 16:41:16'),
(102, NULL, 'yevgeny377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '27.110.160.143', '2025-11-20 16:41:31'),
(103, NULL, 'yevgeny377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '27.110.160.143', '2025-11-20 16:41:46'),
(104, NULL, 'yevgeny377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '27.110.160.143', '2025-11-20 16:42:00'),
(105, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 16:42:54'),
(106, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 16:49:18'),
(107, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 17:09:31'),
(108, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 17:15:04'),
(109, 26, 'yevgenylloyd377@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 17:15:34'),
(110, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 17:24:35'),
(111, 28, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 17:47:18'),
(112, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '27.110.160.143', '2025-11-20 17:49:12'),
(113, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 18:14:23'),
(114, 29, 'Shanebuarao2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 18:22:24'),
(115, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 18:25:36'),
(116, 30, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 18:28:00'),
(117, 31, 'Ray2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 18:40:12'),
(118, 32, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 21:20:48'),
(119, 33, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 22:07:10'),
(120, 34, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 22:25:19'),
(121, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 23:03:22'),
(122, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-21 23:27:46'),
(123, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '203.177.237.122', '2025-11-21 23:33:04'),
(124, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '203.177.237.122', '2025-11-21 23:33:22'),
(125, NULL, 'yevgenylloyd377@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '203.177.237.122', '2025-11-21 23:35:03'),
(126, 35, 'buton.yevgenylloyd@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '203.177.237.122', '2025-11-21 23:37:46'),
(127, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '203.177.237.122', '2025-11-21 23:49:30'),
(128, 35, 'buton.yevgenylloyd@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '203.177.237.122', '2025-11-21 23:58:03'),
(129, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '203.177.237.122', '2025-11-21 23:58:40'),
(130, 36, 'mgsebastian@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '110.54.184.52', '2025-11-22 00:05:32'),
(131, 37, 'Ray@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-22 00:10:23'),
(132, 38, 'dextertayco18@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '175.176.72.152', '2025-11-22 04:31:20'),
(133, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-11-22 14:34:44'),
(134, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '49.148.116.255', '2025-11-23 04:18:55'),
(135, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-12-04 22:08:56'),
(136, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-12-05 15:16:28'),
(137, NULL, 'shane2@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '103.73.58.233', '2025-12-05 15:42:41'),
(138, 39, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-12-05 15:42:56'),
(139, 40, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-12-05 15:49:21'),
(140, 41, 'Shanebuarao@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2025-12-05 17:21:00'),
(141, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '175.176.74.194', '2025-12-16 22:12:07'),
(142, NULL, 'admin2@gmail.com', 'LOGIN_FAILURE', 'Invalid password attempt.', '131.226.109.173', '2025-12-16 22:12:13'),
(143, NULL, 'admin@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '131.226.109.173', '2025-12-16 22:18:06'),
(144, NULL, 'admin@gmail.com', 'LOGIN_FAILURE', 'Email not found attempt.', '131.226.109.173', '2025-12-16 22:18:15'),
(145, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '131.226.109.173', '2025-12-16 22:18:27'),
(146, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2026-01-05 18:58:36'),
(147, 8, 'admin2@gmail.com', 'LOGIN_SUCCESS', 'User successfully authenticated.', '103.73.58.233', '2026-01-08 00:22:43');

-- --------------------------------------------------------

--
-- Table structure for table `student_assessments`
--

CREATE TABLE `student_assessments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `assessment_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `submitted_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `course_year` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT 'default.jpg',
  `birthday` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('student','admin') NOT NULL DEFAULT 'student',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `student_number`, `department`, `course_year`, `section`, `profile_photo`, `birthday`, `address`, `role`, `created_at`) VALUES
(8, 'admin2', '$2y$10$r8093ARCJ8uURhjEaUMlfepQpVdTR7i5Aa0tWBS.xJOVV99TOxmoa', 'admin2', 'admin2@gmail.com', NULL, NULL, NULL, NULL, 'default.jpg', NULL, NULL, 'admin', '2025-11-01 22:13:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `counselor_id` (`counselor_id`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `counselor_id` (`counselor_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_assessment_id` (`student_assessment_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `security_log`
--
ALTER TABLE `security_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_type` (`event_type`);

--
-- Indexes for table `student_assessments`
--
ALTER TABLE `student_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=310;

--
-- AUTO_INCREMENT for table `security_log`
--
ALTER TABLE `security_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `student_assessments`
--
ALTER TABLE `student_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
