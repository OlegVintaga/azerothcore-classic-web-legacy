<?php
// ============================================================
// DBC -> SQL экстрактор для таблиц armory (MBA), которых нет
// в апстрим-дампах MBA (sql/3.3.2 из Brueggus/Mangos-Blizzlike-Armory).
//
// Читает КЛИЕНТСКИЕ .dbc (WotLK 3.3.5a, формат WDBC) и пишет SQL-дампы:
//   dbc_creaturedisplayinfo.sql      <- CreatureDisplayInfo.dbc
//   dbc_creaturedisplayinfoextra.sql <- CreatureDisplayInfoExtra.dbc
//   dbc_itemdisplayinfo_full.sql     <- ItemDisplayInfo.dbc
//   armory_titles.sql                <- CharTitles.dbc (язык = язык клиента)
//
// Использование (только чтение файлов клиента, БД не трогает):
//   php dbc_extract.php "C:\path\to\WOTLK\data\dbc"
// Выходные .sql кладутся рядом со скриптом.
//
// Имена колонок — какие читает код armory:
//   dbc_creaturedisplayinfo:            ID, ExtendedDisplayInfoID (+ остальные из DBC)
//   dbc_creaturedisplayinfoextra:       ID, NPCItemDisplayID_1..11, Flags (+ остальные)
//   dbc_itemdisplayinfo_full:           ID, ModelName1, ModelName2, ModelTexture1,
//                                       ModelTexture2, Texture1..8 (+ остальные)
//   Имена колонок генерируются в ОБОИХ вариантах: без "_" (configuration/functions.php)
//   и с "_" (source/item-info.php при conf_client.value > 1 — в апстриме стоит 4);
//   в dbc_creaturedisplayinfoextra дополнительно Equipment_1..9 (ветка CLIENT>1).
// ============================================================

if (PHP_SAPI !== 'cli') die('CLI only' . PHP_EOL);

$dbcDir  = isset($argv[1]) ? rtrim($argv[1], '\\/') : null;
$outDir  = __DIR__;
if (!$dbcDir || !is_dir($dbcDir)) {
    die("Usage: php dbc_extract.php <path to client data\\dbc>" . PHP_EOL);
}

/**
 * Разбор простого WDBC (3.3.5): заголовок 20 байт, далее записи по
 * fieldCount uint32-полей, затем блок строк. String-поля хранят смещение
 * в блоке строк (0 = пустая строка).
 */
function read_wdbc(string $path): array
{
    $b = @file_get_contents($path);
    if ($b === false) die("Не удалось прочитать $path" . PHP_EOL);
    if (substr($b, 0, 4) !== 'WDBC') die("$path: не WDBC (magic=" . substr($b, 0, 4) . ")" . PHP_EOL);
    $h = unpack('Vrecords/Vfields/VrecSize/VstrBlock', substr($b, 4, 16));
    $recSize = $h['fields'] * 4;
    if ($h['recSize'] != $recSize)
        die("$path: unexpected record size {$h['recSize']} (expected $recSize)" . PHP_EOL);
    $recPos = 20;
    $strPos = 20 + $h['records'] * $recSize;
    if (strlen($b) < $strPos + $h['strBlock']) die("$path: файл короче заголовка" . PHP_EOL);
    $strBlk = substr($b, $strPos, $h['strBlock']);

    $rows = [];
    for ($r = 0; $r < $h['records']; $r++) {
        $f = array_values(unpack('V' . $h['fields'], substr($b, $recPos + $r * $recSize, $recSize)));
        // int-поля DBC знаковые (int32): 0xFFFFFFFF = -1 (например BloodLevel/GeosetGroup).
        // Смещения строковых полей старший бит не используют — конвертация безопасна для всех.
        foreach ($f as $i => $v)
            if ($v >= 2147483648) $f[$i] = $v - 4294967296;
        $rows[] = $f;
    }
    return [$h, $rows, $strBlk];
}

function wdbc_str(string $strBlk, int $ofs): string
{
    if ($ofs <= 0 || $ofs >= strlen($strBlk)) return '';
    $e = strpos($strBlk, "\0", $ofs);
    if ($e === false) $e = strlen($strBlk);
    return substr($strBlk, $ofs, $e - $ofs);
}

function q(string $s): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
}

/**
 $defs: [ [ 'файл.dbc', 'table', [ colName => ['s'|'i', индекс поля], ... ],
           function(array $f, string $strBlk): array строка значений ] ] — упрощённо ниже.
*/
function dump_table(string $dbcFile, string $table, array $cols, callable $rowFn, string $outDir): void
{
    [$h, $rows, $strBlk] = read_wdbc($dbcFile);
    $ddlCols = [];
    foreach ($cols as $name => $type)
        $ddlCols[] = "`$name` " . ($type === 's' ? 'varchar(255) NOT NULL DEFAULT \'\''
                     : ($type === 'f' ? 'float NOT NULL DEFAULT 0' : 'int(11) NOT NULL DEFAULT 0'));
    $out  = "-- $table — из " . basename($dbcFile) . " (WotLK 3.3.5 DBC, сгенерировано dbc_extract.php)\n";
    $out .= "DROP TABLE IF EXISTS `$table`;\n";
    $out .= "CREATE TABLE `$table` (\n  " . implode(",\n  ", $ddlCols) . ",\n  PRIMARY KEY (`" . array_key_first($cols) . "`)\n"
          . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
    $n = 0;
    foreach ($rows as $f) {
        $vals = $rowFn($f, $strBlk);
        $out .= ($n % 2000 === 0 ? "\nINSERT INTO `$table` VALUES\n" : ",\n")
              . '(' . implode(',', $vals) . ')';
        if ($n % 2000 === 1999) $out .= ';';
        $n++;
    }
    if ($n % 2000 !== 0) $out .= ';';
    $out .= "\n";
    $file = $outDir . DIRECTORY_SEPARATOR . "$table.sql";
    file_put_contents($file, $out);
    echo "$table: $n строк -> $file (" . round(strlen($out) / 1024) . " KiB)\n";
}

$cdi = $dbcDir . '/CreatureDisplayInfo.dbc';
$cdie = $dbcDir . '/CreatureDisplayInfoExtra.dbc';
$idi = $dbcDir . '/ItemDisplayInfo.dbc';
foreach ([$cdi, $cdie, $idi] as $p) if (!file_exists($p)) die("Нет файла $p" . PHP_EOL);

// CreatureDisplayInfo.dbc: 16 полей, порядок как в ACo creaturedisplayinfo_dbc
dump_table($cdi, 'dbc_creaturedisplayinfo', [
    'ID' => 'i', 'ModelID' => 'i', 'SoundID' => 'i', 'ExtendedDisplayInfoID' => 'i',
    'CreatureModelScale' => 'f', 'CreatureModelAlpha' => 'i',
    'TextureVariation_1' => 's', 'TextureVariation_2' => 's', 'TextureVariation_3' => 's',
    'PortraitTextureName' => 's', 'BloodLevel' => 'i', 'BloodID' => 'i',
    'NPCSoundID' => 'i', 'ParticleColorID' => 'i', 'CreatureGeosetData' => 'i',
    'ObjectEffectPackageID' => 'i',
], function (array $f, string $sb): array {
    return [
        $f[0], $f[1], $f[2], $f[3],
        rtrim(rtrim(sprintf('%.6F', current(unpack('f', pack('V', $f[4])))), '0'), '.'), // float из IEEE754-бит ($f[4] может быть отрицательным — pack маскирует)
        $f[5],
        q(wdbc_str($sb, $f[6])), q(wdbc_str($sb, $f[7])), q(wdbc_str($sb, $f[8])),
        q(wdbc_str($sb, $f[9])),
        $f[10], $f[11], $f[12], $f[13], $f[14], $f[15],
    ];
}, $outDir);

// CreatureDisplayInfoExtra.dbc: 21 поле; NPCItemDisplay1..11 -> NPCItemDisplayID_1..11 (имена кода)
// +Equipment_1..9 — то же данные под именами ветки CLIENT>1 (conf_client.value=4 в апстриме)
dump_table($cdie, 'dbc_creaturedisplayinfoextra', [
    'ID' => 'i', 'DisplayRaceID' => 'i', 'DisplaySexID' => 'i', 'SkinID' => 'i',
    'FaceID' => 'i', 'HairStyleID' => 'i', 'HairColorID' => 'i', 'FacialHairID' => 'i',
    'NPCItemDisplayID_1' => 'i', 'NPCItemDisplayID_2' => 'i', 'NPCItemDisplayID_3' => 'i',
    'NPCItemDisplayID_4' => 'i', 'NPCItemDisplayID_5' => 'i', 'NPCItemDisplayID_6' => 'i',
    'NPCItemDisplayID_7' => 'i', 'NPCItemDisplayID_8' => 'i', 'NPCItemDisplayID_9' => 'i',
    'NPCItemDisplayID_10' => 'i', 'NPCItemDisplayID_11' => 'i',
    'Equipment_1' => 'i', 'Equipment_2' => 'i', 'Equipment_3' => 'i',
    'Equipment_4' => 'i', 'Equipment_5' => 'i', 'Equipment_6' => 'i',
    'Equipment_7' => 'i', 'Equipment_8' => 'i', 'Equipment_9' => 'i',
    'Flags' => 'i', 'BakeName' => 's',
], function (array $f, string $sb): array {
    // f: 0 ID, 1 race, 2 sex, 3 skin, 4 face, 5 hair, 6 hairColor, 7 facialHair,
    //    8..18 NPCItemDisplay1..11, 19 Flags, 20 BakeName(str)
    return array_merge(
        [$f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6], $f[7]],
        [$f[8], $f[9], $f[10], $f[11], $f[12], $f[13], $f[14], $f[15], $f[16], $f[17], $f[18]],
        // Equipment_1..9 = те же NPCItemDisplay1..9 (ветка CLIENT>1: Equipment_(j-1), j=2..10)
        [$f[8], $f[9], $f[10], $f[11], $f[12], $f[13], $f[14], $f[15], $f[16]],
        [$f[19], q(wdbc_str($sb, $f[20]))]
    );
}, $outDir);

// ItemDisplayInfo.dbc: 25 полей; имена колонок — как в functions.php (без "_")
// + зеркальные с "_" (ModelName_1, Texture_1...) — их читает item-info.php при CLIENT>1
dump_table($idi, 'dbc_itemdisplayinfo_full', [
    'ID' => 'i', 'ModelName1' => 's', 'ModelName2' => 's', 'ModelTexture1' => 's',
    'ModelTexture2' => 's', 'InventoryIcon1' => 's', 'InventoryIcon2' => 's',
    'GeosetGroup1' => 'i', 'GeosetGroup2' => 'i', 'GeosetGroup3' => 'i',
    'Flags' => 'i', 'SpellVisualID' => 'i', 'GroupSoundIndex' => 'i',
    'HelmetGeosetVis1' => 'i', 'HelmetGeosetVis2' => 'i',
    'Texture1' => 's', 'Texture2' => 's', 'Texture3' => 's', 'Texture4' => 's',
    'Texture5' => 's', 'Texture6' => 's', 'Texture7' => 's', 'Texture8' => 's',
    'ItemVisual' => 'i', 'ParticleColorID' => 'i',
    'ModelName_1' => 's', 'ModelName_2' => 's', 'ModelTexture_1' => 's', 'ModelTexture_2' => 's',
    'Texture_1' => 's', 'Texture_2' => 's', 'Texture_3' => 's', 'Texture_4' => 's',
    'Texture_5' => 's', 'Texture_6' => 's', 'Texture_7' => 's', 'Texture_8' => 's',
], function (array $f, string $sb): array {
    // f: 0 ID, 1-2 ModelName, 3-4 ModelTexture, 5-6 InventoryIcon(str),
    //    7-9 GeosetGroup, 10 Flags, 11 SpellVisualID, 12 GroupSoundIndex,
    //    13-14 HelmetGeosetVis, 15-22 Texture1..8(str), 23 ItemVisual, 24 ParticleColorID
    $str = [];
    foreach ([1, 2, 3, 4, 5, 6, 15, 16, 17, 18, 19, 20, 21, 22] as $i)
        $str[] = q(wdbc_str($sb, $f[$i]));
    return array_merge(
        [$f[0]],
        array_slice($str, 0, 6),                    // ModelName1..InventoryIcon2
        [$f[7], $f[8], $f[9], $f[10], $f[11], $f[12], $f[13], $f[14]],
        array_slice($str, 6, 8),                    // Texture1..8
        [$f[23], $f[24]],
        // зеркало с подчёркиванием: ModelName_1,2 ModelTexture_1,2 Texture_1..8
        [$str[0], $str[1], $str[2], $str[3]],
        array_slice($str, 6, 8)
    );
}, $outDir);

// CharTitles.dbc: 37 полей (3.3.5 формат с локальными масками):
//   0 ID, 1 Condition_ID, 2..17 Name_Lang_* (муж. имя на 16 локалях), 18 Name_Lang_Mask,
//   19..34 Name1_Lang_* (жен. имя), 35 Name1_Lang_Mask, 36 Mask_ID.
// В клиентском DBC заполнен один локль (язык клиента) -> одна строка на пол.
// armory_titles: id, title_M_<loc>, title_F_<loc>, place — всеLocale-колонки
// заполняются одним значением (код выбирает title_M_/title_F_ + LANGUAGE из conf_lang).
$cti = $dbcDir . '/CharTitles.dbc';
if (!file_exists($cti)) die("Нет файла $cti" . PHP_EOL);
dump_table($cti, 'armory_titles', [
    'id' => 'i',
    'title_M_en_gb' => 's', 'title_F_en_gb' => 's',
    'title_M_ru_ru' => 's', 'title_F_ru_ru' => 's',
    'title_M_de_de' => 's', 'title_F_de_de' => 's',
    'title_M_fr_fr' => 's', 'title_F_fr_fr' => 's',
    'place' => 's',
], function (array $f, string $sb): array {
    $male = '';
    for ($i = 2; $i <= 17; $i++) {
        $s = wdbc_str($sb, $f[$i]);
        if ($s !== '') { $male = $s; break; }
    }
    $female = '';
    for ($i = 19; $i <= 34; $i++) {
        $s = wdbc_str($sb, $f[$i]);
        if ($s !== '') { $female = $s; break; }
    }
    // DBC-строки содержат '%s' (плейсхолдер имени игрока) — для вывода на сайте убираем
    $male = str_replace(' %s', '', $male);
    $female = str_replace(' %s', '', $female);
    $m = q($male); $fm = q($female);
    return [$f[0], $m, $fm, $m, $fm, $m, $fm, $m, $fm, "''" /* place: нет источника в DBC */];
}, $outDir);

echo "Готово.\n";
