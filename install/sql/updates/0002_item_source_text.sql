-- Источник предмета (список боссов/зон/лута) для reference-лута бывает
-- длиннее 255 символов; MySQL 8 в strict-режиме отбивал REPLACE INTO
-- ("Data too long for column 'item_source'"), из-за чего поиск по предметам
-- в оружейной падал с 500. Расширяем колонку до TEXT.
ALTER TABLE `cache_item_search` MODIFY `item_source` TEXT NOT NULL;
