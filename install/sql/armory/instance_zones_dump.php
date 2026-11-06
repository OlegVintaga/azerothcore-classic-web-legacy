<?php
// ============================================================
// Генератор справочников инстансов/зон для armory (MBA):
//   armory_instance_template   — справочник инстансов
//   armory_instance_data       — существо/GO -> инстанс (тултипы лута боссов)
//   creature_consistent_zones  — существо -> зона (подпись "Area:" у world-лута)
//   go_consistent_zones        — gameobject -> зона
//
// Источники (ТОЛЬКО ЧТЕНИЕ):
//   - AzerothCore world DB: instance_template, creature/gameobject спавны,
//     creature_template(+locale), gameobject_template(+locale), creature_loot_template
//   - клиентские DBC (WotLK 3.3.5a): LFGDungeons.dbc (map/type/уровни/имя инстанса),
//     AreaTable.dbc (имена зон). Язык имен = язык клиента (DBC одноязычны).
//
// Колонки — какие читает код armory (configuration/functions.php,
// source/character-sheet.php):
//   armory_instance_data:     id, lootid_1..4, name_id, type ('npc'|'object'),
//                             instance_id, name_<loc>
//   armory_instance_template: id, name_<loc>, is_heroic, partySize, expansion, raid
//   *_consistent_zones:       id, Name, ZoneId (запрос по ZoneId тоже есть)
// name_<loc>: en_gb читается кодом всегда; ru_ru/de_de/fr_fr — по LANGUAGE (conf_lang).
// Конвенция генератора (как в SPP-MBA): name_en_gb и name_ru_ru = основной язык
// (русский, если есть локаль/DBC-строка, иначе английский) — потому что
// functions.php в тултипах жёстко читает name_en_gb; name_de_de/name_fr_fr =
// английские имена (из instance_template.script / creature_template.name).
//
// Использование (CLI, PHP >= 8.0 + pdo_mysql):
//   php instance_zones_dump.php <host> <port> <user> <pass> <world_db> <path data\dbc>
// Выход: armory_instances.sql рядом со скриптом.
// ============================================================

if (PHP_SAPI !== 'cli') die('CLI only' . PHP_EOL);
[$self, $host, $port, $user, $pass, $worldDb, $dbcDir] = array_pad($argv ?? [], 7, null);
if (!$worldDb || !$dbcDir) {
    die("Usage: php instance_zones_dump.php <host> <port> <user> <pass> <world_db> <path to client data\\dbc>" . PHP_EOL);
}
$DBC_DIR = rtrim($dbcDir, '\\/');

function q(string $s): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
}
function read_wdbc(string $path): array
{
    $b = @file_get_contents($path);
    if ($b === false) die("Не удалось прочитать $path" . PHP_EOL);
    if (substr($b, 0, 4) !== 'WDBC') die("$path: не WDBC" . PHP_EOL);
    $h = unpack('Vrecords/Vfields/VrecSize/VstrBlock', substr($b, 4, 16));
    $strPos = 20 + $h['records'] * $h['fields'] * 4;
    $sb = substr($b, $strPos, $h['strBlock']);
    $rows = [];
    for ($r = 0; $r < $h['records']; $r++)
        $rows[] = array_values(unpack('V' . $h['fields'], substr($b, 20 + $r * $h['recSize'], $h['recSize'])));
    return [$h, $rows, $sb];
}
function wstr(string $sb, int $o): string
{
    if ($o <= 0 || $o >= strlen($sb)) return '';
    $e = strpos($sb, "\0", $o);
    if ($e === false) $e = strlen($sb);
    return substr($sb, $o, $e - $o);
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$worldDb;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    die("Не подключился к $worldDb: {$e->getMessage()}" . PHP_EOL);
}

// ---------- DBC: имена зон и справочник LFG ----------
// AreaTable.dbc (3.3.5): поле 0 ID, поле 19 — имя зоны (язык клиента; позиция
// проверена валидацией "строка начинается после NUL" — 2307/2307).
[, $atRows, $atSb] = read_wdbc("$DBC_DIR/AreaTable.dbc");
$zoneName = [];
foreach ($atRows as $r) $zoneName[$r[0]] = wstr($atSb, $r[19]);

// LFGDungeons.dbc (3.3.5): 9 имя, 22 макс.уровень, 23 instanceMapID, 26 type
// (1=подземелье, 2=рейд, 4=героическое). Позиции проверены эталонами:
// 'Мертвые копи'->map 36, 'Наксрамас'->map 533.
[, $lfgRows, $lfgSb] = read_wdbc("$DBC_DIR/LFGDungeons.dbc");
$lfgByName = [];   // map => ['name'=>, 'type'=>, 'maxlv'=>]
foreach ($lfgRows as $r) {
    $map = $r[23];
    if ($map <= 0) continue;
    if (!isset($lfgByName[$map]) || $r[26] == 2) // рейд-запись важнее (общее имя)
        $lfgByName[$map] = ['name' => wstr($lfgSb, $r[9]), 'type' => $r[26], 'maxlv' => $r[22]];
}

// ---------- шаблон инстансов ----------
// raid: из LFG type=2 + ванильные рейды вне LFG-списков;
// expansion: по maxlv LFG (>=76 WotLK=2, >=65 BC=1, иначе 0),
// вне LFG: карта <533 -> ваниль(0), кроме ZG(569)=0; 533+ -> 2.
$VANILLA_RAIDS = [249, 409, 469, 509, 569]; // Onyxia, MC, BWL, Ruins AQ, ZG
$templates = []; // map => row
// RU-имя инстанса по доминирующей зоне спавнов на карте (zoneId у инстанс-карт != map)
$mapZone = []; // map => [zoneId, cnt]
foreach (['creature', 'gameobject'] as $t) {
    foreach ($pdo->query("SELECT map, zoneId, COUNT(*) cnt FROM $t WHERE zoneId>0 GROUP BY map, zoneId") as $r) {
        $map = (int)$r['map'];
        if (!isset($mapZone[$map]) || $mapZone[$map][1] < (int)$r['cnt'])
            $mapZone[$map] = [(int)$r['zoneId'], (int)$r['cnt']];
    }
}
// "Английское" имя из script: instance_shadowfang_keep -> Shadowfang Keep
function name_from_script(string $script): string
{
    $s = preg_replace('/^instance_/', '', $script);
    $s = ucwords(str_replace('_', ' ', trim($s)));
    return $s === '' ? '' : $s;
}
$stmt = $pdo->query("SELECT map, script FROM instance_template ORDER BY map");
foreach ($stmt as $row) {
    $map = (int)$row['map'];
    $script = (string)$row['script'];
    $zoneRu = $mapZone[$map] ?? null ? ($zoneName[$mapZone[$map][0]] ?? '') : '';
    $en = name_from_script($script);
    $lfg = $lfgByName[$map] ?? null;
    if ($lfg) {
        $exp = $lfg['maxlv'] >= 76 ? 2 : ($lfg['maxlv'] >= 65 ? 1 : 0);
        $raid = $lfg['type'] == 2 ? 1 : 0;
        $heroic = $lfg['type'] == 4 ? 1 : 0;
        $ru = $lfg['name'] ?: $zoneRu;
    } else {
        $exp = in_array($map, $VANILLA_RAIDS, true) || $map < 533 ? 0 : 2;
        $raid = in_array($map, $VANILLA_RAIDS, true) ? 1 : 0;
        $heroic = 0;
        $ru = $zoneRu;
    }
    if ($en === '') $en = $ru ?: "Map $map";
    $templates[$map] = [
        'name_en' => $en,
        // functions.php берёт имя тултипа из name_en_gb — для русскоязычной сборки
        // как в исходном SPP-MBA: en_gb = русское имя, если оно есть (см. шапку)
        'name_ru' => $ru ?: $en,
        'is_heroic' => $heroic, 'partySize' => $raid ? 25 : 5,
        'expansion' => $exp, 'raid' => $raid,
    ];
}
if (!$templates) die("instance_template пуста?!" . PHP_EOL);

// ---------- instance_data: NPC ----------
// строка на существо, заспавненное на карте инстанса и имеющее лут-шаблон.
// lootid_1..4: лут-таблицы самого существа и difficulty_entry_1..3.
$dataNpc = [];
$sql = "SELECT c.id, c.map, COUNT(*) cnt FROM creature c WHERE c.map IN ("
     . implode(',', array_keys($templates)) . ") GROUP BY c.id, c.map";
$spawnMap = []; // entry => лучший map
foreach ($pdo->query($sql) as $r) {
    $e = (int)$r['id'];
    if (!isset($spawnMap[$e]) || $spawnMap[$e][1] < (int)$r['cnt'])
        $spawnMap[$e] = [(int)$r['map'], (int)$r['cnt']];
}
$entries = array_keys($spawnMap);
$names = $namesRu = $lootIds = $diff = [];
if (!$entries) die("Нет спавнов на картах инстансов?!" . PHP_EOL);
foreach ($pdo->query("SELECT entry, name, difficulty_entry_1, difficulty_entry_2, difficulty_entry_3, lootid
                      FROM creature_template WHERE entry IN (" . implode(',', $entries) . ")") as $r) {
    $e = (int)$r['entry'];
    $names[$e] = (string)$r['name'];
    $lootIds[$e] = (int)$r['lootid'] > 0 ? (int)$r['lootid'] : $e;
    $diff[$e] = array_filter([(int)$r['difficulty_entry_1'], (int)$r['difficulty_entry_2'], (int)$r['difficulty_entry_3']]);
}
foreach ($pdo->query("SELECT entry, name, locale FROM creature_template_locale WHERE locale='ruRU' AND entry IN ("
                     . implode(',', $entries) . ")") as $r)
    $namesRu[(int)$r['entry']] = (string)$r['name'];
// только существа с реальным лутом (иначе строки-шум)
$withLoot = [];
foreach ($pdo->query("SELECT DISTINCT entry FROM creature_loot_template WHERE entry IN (" . implode(',', $entries) . ")") as $r)
    $withLoot[(int)$r['entry']] = true;
// H-версии: lootid difficulty-записей
$allDiff = [];
foreach ($diff as $d) foreach ($d as $de) $allDiff[] = $de;
$diffLoot = [];
if ($allDiff) {
    foreach ($pdo->query("SELECT entry, lootid FROM creature_template WHERE entry IN (" . implode(',', array_unique($allDiff)) . ")") as $r)
        $diffLoot[(int)$r['entry']] = (int)$r['lootid'] > 0 ? (int)$r['lootid'] : (int)$r['entry'];
}
$rowsData = [];
foreach ($entries as $e) {
    if (!isset($withLoot[$e])) continue;
    $map = $spawnMap[$e][0];
    $loot = [$lootIds[$e]];
    foreach ($diff[$e] as $de) $loot[] = $diffLoot[$de] ?? $de;
    $rowsData[] = ['type' => 'npc', 'id' => $e, 'map' => $map, 'loot' => $loot,
                   'name_en' => $names[$e] ?? "Entry $e", 'name_ru' => ($namesRu[$e] ?? ($names[$e] ?? ''))];
}

// ---------- instance_data: OBJECT (сундуки/лутилки с data1=лут-таблица) ----------
$rowsObj = [];
$goStmt = $pdo->query("SELECT g.id, g.map, COUNT(*) cnt FROM gameobject g WHERE g.map IN ("
    . implode(',', array_keys($templates)) . ") GROUP BY g.id, g.map");
$goMap = [];
foreach ($goStmt as $r) {
    $e = (int)$r['id'];
    if (!isset($goMap[$e]) || $goMap[$e][1] < (int)$r['cnt'])
        $goMap[$e] = [(int)$r['map'], (int)$r['cnt']];
}
if ($goMap) {
    $goNames = $goNamesRu = [];
    foreach ($pdo->query("SELECT entry, name, data1 FROM gameobject_template WHERE type=2 AND data1>0 AND entry IN ("
                         . implode(',', array_keys($goMap)) . ")") as $r) {
        $goNames[(int)$r['entry']] = [(string)$r['name'], (int)$r['data1']];
    }
    if ($goNames) {
        foreach ($pdo->query("SELECT entry, name FROM gameobject_template_locale WHERE locale='ruRU' AND entry IN ("
                             . implode(',', array_keys($goNames)) . ")") as $r)
            $goNamesRu[(int)$r['entry']] = (string)$r['name'];
        foreach ($goNames as $e => [$nm, $data1])
            $rowsObj[] = ['type' => 'object', 'id' => $e, 'map' => $goMap[$e][0], 'loot' => [$data1],
                          'name_en' => $nm, 'name_ru' => ($goNamesRu[$e] ?? $nm)];
    }
}

// ---------- consistent zones ----------
function zones_from_spawns(PDO $pdo, string $table, array $zoneName): array
{
    $out = [];
    $sql = "SELECT id, zoneId, COUNT(*) cnt FROM $table WHERE zoneId>0 GROUP BY id, zoneId";
    foreach ($pdo->query($sql) as $r) {
        $e = (int)$r['id'];
        if (!isset($out[$e]) || $out[$e][2] < (int)$r['cnt'])
            $out[$e] = [$e, (int)$r['zoneId'], (int)$r['cnt']];
    }
    foreach ($out as $e => $v) $out[$e] = [$v[1], $zoneName[$v[1]] ?? '']; // id => [zoneId, Name]
    ksort($out);
    return $out;
}
$crZones = zones_from_spawns($pdo, 'creature', $zoneName);
$goZones = zones_from_spawns($pdo, 'gameobject', $zoneName);

// ---------- вывод SQL ----------
$out = "-- armory_instance_template / armory_instance_data / *_consistent_zones\n"
     . "-- сгенерировано instance_zones_dump.php из $worldDb + клиентских DBC $DBC_DIR\n";

$out .= "\nDROP TABLE IF EXISTS `armory_instance_template`;\n"
      . "CREATE TABLE `armory_instance_template` (\n"
      . "  `id` int(11) NOT NULL,\n"
      . "  `name_en_gb` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `name_ru_ru` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `name_de_de` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `name_fr_fr` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `is_heroic` tinyint(1) NOT NULL DEFAULT 0,\n"
      . "  `partySize` int(11) NOT NULL DEFAULT 5,\n"
      . "  `expansion` tinyint(1) NOT NULL DEFAULT 0,\n"
      . "  `raid` tinyint(1) NOT NULL DEFAULT 0,\n"
      . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
$parts = [];
foreach ($templates as $map => $t) {
    // en_gb/ru_ru = русское имя (тултипы functions.php читают en_gb; как в SPP-MBA),
    // de_de/fr_fr = английское (сгенерировано из script)
    $parts[] = "($map," . q($t['name_ru']) . ',' . q($t['name_ru']) . ',' . q($t['name_en']) . ',' . q($t['name_en']) . ",{$t['is_heroic']},{$t['partySize']},{$t['expansion']},{$t['raid']})";
}
$out .= "INSERT INTO `armory_instance_template` VALUES\n" . implode(",\n", $parts) . ";\n";

$out .= "\nDROP TABLE IF EXISTS `armory_instance_data`;\n"
      . "CREATE TABLE `armory_instance_data` (\n"
      . "  `id` int(11) NOT NULL,\n"
      . "  `type` varchar(6) NOT NULL DEFAULT 'npc',\n"
      . "  `instance_id` int(11) NOT NULL DEFAULT 0,\n"
      . "  `lootid_1` int(11) NOT NULL DEFAULT 0,\n"
      . "  `lootid_2` int(11) NOT NULL DEFAULT 0,\n"
      . "  `lootid_3` int(11) NOT NULL DEFAULT 0,\n"
      . "  `lootid_4` int(11) NOT NULL DEFAULT 0,\n"
      . "  `name_id` int(11) NOT NULL DEFAULT 0,\n"
      . "  `name_en_gb` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `name_ru_ru` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `name_de_de` varchar(255) NOT NULL DEFAULT '',\n"
      . "  `name_fr_fr` varchar(255) NOT NULL DEFAULT '',\n"
      . "  PRIMARY KEY (`id`,`type`), KEY `instance_id` (`instance_id`),\n"
      . "  KEY `lootid_1` (`lootid_1`), KEY `lootid_2` (`lootid_2`),\n"
      . "  KEY `lootid_3` (`lootid_3`), KEY `lootid_4` (`lootid_4`)\n"
      . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
$parts = [];
foreach (array_merge($rowsData, $rowsObj) as $d) {
    $l = array_pad($d['loot'], 4, 0);
    $main = $d['name_ru'] !== '' ? $d['name_ru'] : $d['name_en']; // см. шапку: en_gb = основной язык
    $parts[] = "({$d['id']},'{$d['type']}',{$d['map']},$l[0],$l[1],$l[2],$l[3],{$d['id']},"
             . q($main) . ',' . q($main) . ',' . q($d['name_en']) . ',' . q($d['name_en']) . ')';
}
$out .= $parts ? "INSERT INTO `armory_instance_data` VALUES\n" . implode(",\n", $parts) . ";\n"
               : "" ;

foreach (['creature_consistent_zones' => $crZones, 'go_consistent_zones' => $goZones] as $tbl => $zrows) {
    $out .= "\nDROP TABLE IF EXISTS `$tbl`;\n"
          . "CREATE TABLE `$tbl` (\n"
          . "  `id` int(11) NOT NULL,\n"
          . "  `Name` varchar(255) NOT NULL DEFAULT '',\n"
          . "  `ZoneId` int(11) NOT NULL DEFAULT 0,\n"
          . "  PRIMARY KEY (`id`), KEY `ZoneId` (`ZoneId`)\n"
          . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
    $parts = [];
    foreach ($zrows as $id => [$zid, $nm])
        $parts[] = "($id," . q($nm) . ",$zid)";
    if ($parts) $out .= "INSERT INTO `$tbl` VALUES\n" . implode(",\n", $parts) . ";\n";
}

$file = __DIR__ . '/armory_instances.sql';
file_put_contents($file, $out);
printf("armory_instance_template: %d строк\n", count($templates));
printf("armory_instance_data: %d строк (npc %d + object %d)\n", count($rowsData) + count($rowsObj), count($rowsData), count($rowsObj));
printf("creature_consistent_zones: %d строк\ngo_consistent_zones: %d строк\n", count($crZones), count($goZones));
echo "-> $file\n";
