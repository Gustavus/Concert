SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema concert-beta
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `concert-beta` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
USE `concert-beta` ;

-- -----------------------------------------------------
-- Table `concert-beta`.`locks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `concert-beta`.`locks` (
  `filepathHash` VARCHAR(32) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `filepath` VARCHAR(2048) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `username` VARCHAR(24) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `date` DATETIME NOT NULL,
  PRIMARY KEY (`filepathHash`),
  INDEX `username` (`username` ASC))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `concert-beta`.`sites`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `concert-beta`.`sites` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `siteRoot` VARCHAR(1024) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `concert-beta`.`permissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `concert-beta`.`permissions` (
  `site_id` INT(10) UNSIGNED NOT NULL,
  `username` VARCHAR(32) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `accessLevel` VARCHAR(24) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `includedFiles` VARCHAR(512) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL,
  `excludedFiles` VARCHAR(512) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL,
  PRIMARY KEY (`site_id`, `username`),
  INDEX `userSites_idx` (`site_id` ASC),
  CONSTRAINT `userSite`
    FOREIGN KEY (`site_id`)
    REFERENCES `concert-beta`.`sites` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `concert-beta`.`stagedFiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `concert-beta`.`stagedFiles` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `destFilepath` VARCHAR(2048) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `srcFilename` VARCHAR(40) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `username` VARCHAR(32) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL,
  `date` DATETIME NOT NULL,
  `movedDate` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `srcFilepath` (`srcFilename` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 109
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
