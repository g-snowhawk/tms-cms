CREATE TABLE IF NOT EXISTS `TMS_site` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userkey` int(11) DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `mobileurl` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `lang` varchar(20) DEFAULT 'ja',
  `encoding` varchar(20) DEFAULT 'UTF-8',
  `openpath` varchar(255) DEFAULT NULL,
  `mobilepath` varchar(255) DEFAULT NULL,
  `defaultpage` varchar(32) NOT NULL DEFAULT 'index',
  `defaultextension` varchar(5) NOT NULL DEFAULT '.html',
  `styledir` varchar(32) DEFAULT NULL,
  `uploaddir` varchar(32) DEFAULT NULL,
  `maskdir` varchar(4) DEFAULT '0777',
  `maskfile` varchar(4) DEFAULT '0644',
  `maskexec` varchar(4) DEFAULT '0755',
  `maxentry` int(11) DEFAULT NULL,
  `maxcategory` int(11) DEFAULT NULL,
  `maxrevision` int(11) DEFAULT '0',
  `noroot` enum('0','1') NOT NULL DEFAULT '0',
  `type` enum('static','dynamic') NOT NULL DEFAULT 'static',
  `contract` date NOT NULL,
  `expire` int(11) NOT NULL DEFAULT '1',
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sitekey` int(11) NOT NULL,
  `title` varchar(40) NOT NULL,
  `description` text,
  `sourcecode` text,
  `kind` int(11) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `identifier` int(11) DEFAULT '0',
  `revision` int(11) DEFAULT '0',
  `active` tinyint(1) DEFAULT '0',
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `IDENTIFIER` (`identifier`,`revision`),
  UNIQUE KEY `FILEPATH` (`sitekey`,`revision`,`path`),
  KEY `sitekey` (`sitekey`),
  CONSTRAINT `TMS_template_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `TMS_site` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sitekey` int(11) NOT NULL,
  `userkey` int(11) NOT NULL,
  `template` int(11) DEFAULT NULL,
  `default_template` int(11) DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `filepath` varchar(511) DEFAULT NULL,
  `archive_format` text,
  `title` varchar(255) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `description` text,
  `priority` int(11) DEFAULT NULL,
  `inheritance` tinyint(1) NOT NULL DEFAULT '0',
  `reserved` enum('0','1') NOT NULL DEFAULT '0',
  `author_date` datetime DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  `lft` double unsigned DEFAULT '0',
  `rgt` double unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `SITEKEY` (`sitekey`),
  KEY `NESTED_LEFT` (`lft`),
  KEY `NESTED_RIGHT` (`rgt`),
  KEY `TEMPLATE` (`template`),
  KEY `DEFAULT_TEMPLATE` (`default_template`),
  CONSTRAINT `TMS_category_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `TMS_site` (`id`),
  CONSTRAINT `TMS_category_ibfk_2` FOREIGN KEY (`template`) REFERENCES `TMS_template` (`id`),
  CONSTRAINT `TMS_category_ibfk_3` FOREIGN KEY (`default_template`) REFERENCES `TMS_template` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_custom` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sitekey` int(11) NOT NULL,
  `relkey` int(11) NOT NULL,
  `kind` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `alternate` text,
  `data` text,
  `note` text,
  `option1` text,
  `option2` text,
  `option3` text,
  `sort` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `IDENT` (`sitekey`,`relkey`,`kind`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_entry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sitekey` int(11) NOT NULL,
  `userkey` int(11) NOT NULL,
  `template` int(11) DEFAULT NULL,
  `category` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `filepath` varchar(511) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `description` text,
  `body` text,
  `identifier` int(11) DEFAULT '0',
  `revision` int(11) DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(32) DEFAULT NULL,
  `release_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `author_date` datetime DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`,`revision`),
  KEY `sitekey` (`sitekey`,`category`,`path`),
  KEY `REVISION` (`revision`),
  KEY `template` (`template`),
  CONSTRAINT `TMS_entry_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `TMS_site` (`id`),
  CONSTRAINT `TMS_entry_ibfk_2` FOREIGN KEY (`template`) REFERENCES `TMS_template` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_section_level_key` (
  `level` int(11) NOT NULL DEFAULT '2',
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
LOCK TABLES `TMS_section_level_key` WRITE;
INSERT INTO `TMS_section_level_key` (`level`) VALUES (2) ON DUPLICATE KEY UPDATE `level` = '2';
INSERT INTO `TMS_section_level_key` (`level`) VALUES (3) ON DUPLICATE KEY UPDATE `level` = '3';
INSERT INTO `TMS_section_level_key` (`level`) VALUES (4) ON DUPLICATE KEY UPDATE `level` = '4';
INSERT INTO `TMS_section_level_key` (`level`) VALUES (5) ON DUPLICATE KEY UPDATE `level` = '5';
INSERT INTO `TMS_section_level_key` (`level`) VALUES (6) ON DUPLICATE KEY UPDATE `level` = '6';
UNLOCK TABLES;

CREATE TABLE IF NOT EXISTS `TMS_section` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sitekey` int(11) DEFAULT NULL,
  `entrykey` int(11) NOT NULL,
  `level` int(11) DEFAULT '2',
  `title` varchar(255) NOT NULL,
  `body` text,
  `identifier` int(11) DEFAULT '0',
  `revision` int(11) DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(32) DEFAULT NULL,
  `author_date` datetime DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  `lft` double unsigned DEFAULT '0',
  `rgt` double unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifire` (`identifier`,`revision`),
  KEY `SITEKEY` (`sitekey`),
  KEY `ENTRYKEY` (`entrykey`),
  KEY `NESTED_LEFT` (`lft`),
  KEY `NESTED_RIGHT` (`rgt`),
  KEY `level` (`level`),
  CONSTRAINT `TMS_section_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `TMS_site` (`id`),
  CONSTRAINT `TMS_section_ibfk_2` FOREIGN KEY (`entrykey`) REFERENCES `TMS_entry` (`id`),
  CONSTRAINT `TMS_section_ibfk_3` FOREIGN KEY (`level`) REFERENCES `TMS_section_level_key` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_relation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrykey` int(11) NOT NULL,
  `relkey` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifire` (`entrykey`,`relkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
