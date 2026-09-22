-- Import into the existing portal database before deploying the updated code.
-- The first upgrade initializes every user to M. Re-imports preserve M/F values.
SET @portal_gender_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'gender'
);
SET @portal_gender_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'gender'
    AND COLUMN_TYPE = 'enum(''M'',''F'')' AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT = 'M'
);
SET @portal_gender_sql = IF(@portal_gender_exists = 0,
  'ALTER TABLE `user` ADD COLUMN `gender` ENUM(''M'',''F'') NOT NULL DEFAULT ''M''',
  IF(@portal_gender_ready = 0,
    'ALTER TABLE `user` MODIFY COLUMN `gender` VARCHAR(20) NULL DEFAULT ''M''',
    'DO 0'
  )
);
PREPARE portal_gender_statement FROM @portal_gender_sql;
EXECUTE portal_gender_statement;
DEALLOCATE PREPARE portal_gender_statement;

SET @portal_gender_sql = IF(@portal_gender_ready = 0,
  'UPDATE `user` SET `gender` = ''M''', 'DO 0');
PREPARE portal_gender_statement FROM @portal_gender_sql;
EXECUTE portal_gender_statement;
DEALLOCATE PREPARE portal_gender_statement;

SET @portal_gender_sql = IF(@portal_gender_ready = 0,
  'ALTER TABLE `user` MODIFY COLUMN `gender` ENUM(''M'',''F'') NOT NULL DEFAULT ''M''', 'DO 0');
PREPARE portal_gender_statement FROM @portal_gender_sql;
EXECUTE portal_gender_statement;
DEALLOCATE PREPARE portal_gender_statement;
