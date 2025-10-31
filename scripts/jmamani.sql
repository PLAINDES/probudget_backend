CREATE TABLE `archivos` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`nombre` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`url` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`tipo` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`bucket` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`formato` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`size` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`peso` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`embebido` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`user_id` INT(11) NULL DEFAULT '0',
	`created_at` TIMESTAMP NULL DEFAULT current_timestamp(),
	`updated_at` TIMESTAMP NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	`deleted_at` TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE
);

CREATE TABLE `planes` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`nombre` VARCHAR(255) NULL DEFAULT NULL,
	`vigencia` INT(11) NULL DEFAULT '0',
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	`updated_at` DATETIME NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	`deleted_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE
);

CREATE TABLE `permisos` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`nombre` VARCHAR(255) NULL DEFAULT NULL,
	`descripcion` VARCHAR(255) NULL DEFAULT NULL,
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	`updated_at` DATETIME NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	`deleted_at` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE
);

CREATE TABLE `planes_permisos` (
	`plan_id` INT(11) NULL DEFAULT '0',
	`permiso_id` INT(11) NULL DEFAULT '0',
	`cantidad` INT(11) NULL DEFAULT '0',
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	`updated_at` DATETIME NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	`deleted_at` DATETIME NULL DEFAULT NULL
);

ALTER TABLE users ADD COLUMN `plan_id` INT(11) NULL DEFAULT '1' AFTER `code_password`;

/** INSERTS */

INSERT INTO `planes` (`id`, `nombre`, `vigencia`, `created_at`, `updated_at`, `deleted_at`) VALUES (1, 'DEMO', -1, '2022-12-05 13:17:35', '2022-12-06 01:39:21', NULL);

INSERT INTO `permisos` (`id`, `nombre`, `descripcion`, `created_at`, `updated_at`, `deleted_at`) VALUES (1, 'PROYECTOS LIMITADOS', NULL, '2022-12-05 11:26:23', '2022-12-05 11:26:57', NULL);
INSERT INTO `permisos` (`id`, `nombre`, `descripcion`, `created_at`, `updated_at`, `deleted_at`) VALUES (2, 'PARTIDAS LIMITADAS', NULL, '2022-12-05 11:26:38', '2022-12-05 11:27:01', NULL);
INSERT INTO `permisos` (`id`, `nombre`, `descripcion`, `created_at`, `updated_at`, `deleted_at`) VALUES (3, 'ALMACENAMIENTO', NULL, '2022-12-05 11:26:49', '2022-12-05 11:27:06', NULL);

INSERT INTO `planes_permisos` (`plan_id`, `permiso_id`, `cantidad`, `created_at`, `updated_at`, `deleted_at`) VALUES (1, 1, 10, '2022-12-05 11:31:57', '2022-12-05 17:04:27', NULL);
INSERT INTO `planes_permisos` (`plan_id`, `permiso_id`, `cantidad`, `created_at`, `updated_at`, `deleted_at`) VALUES (1, 2, 100, '2022-12-05 11:32:02', '2022-12-06 01:16:36', NULL);
INSERT INTO `planes_permisos` (`plan_id`, `permiso_id`, `cantidad`, `created_at`, `updated_at`, `deleted_at`) VALUES (1, 3, 1403512, '2022-12-05 11:32:09', '2022-12-06 00:56:25', NULL);
