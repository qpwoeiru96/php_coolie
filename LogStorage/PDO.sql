
-- MYSQL 5.0
CREATE TABLE `coolie` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` int(10) unsigned NOT NULL,
  `level` enum('trace','info','warning','error') NOT NULL,
  `category` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index_task` (`task`),
  KEY `index_level` (`level`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- SQLite 3
CREATE TABLE "coolie" (
"id"  INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
"task"  INTEGER NOT NULL,
"level"  TEXT NOT NULL,
"category"  TEXT NOT NULL,
"message"  TEXT NOT NULL,
"time"  INTEGER NOT NULL
);