<?php
// ============================================================
// Установщик веб-обвязки (AzerothCore 3.3.5)
// Требуется PHP >= 8.0, mysqli.
// Шаг 1: форма подключения + данные сайта. Шаг 2: проверка + установка.
//
// Что делает установка:
//  - ПРОВЕРЯЕТ (только чтение!) игровые базы AzerothCore:
//    auth (account с salt/verifier), characters, world.
//  - В веб-базе (по умолчанию `site`) создаёт таблицы из
//    sql/site.sql и VIEW realmlist/uptime на auth-базу.
//  - Импортирует в веб-базу справочники Armory (sql/armory/*.sql:
//    MBA main/en_us/cache + сгенерированные titles/instances/dbc_*).
//  - Заполняет website_realm_settings/realm_settings по realmlist.
//  - Пишет config/config-protected.php ($realmd=auth, $websitedb).
//  - Обновляет config/config.xml: название сайта, email отправителя,
//    имя cookie, expansion; очищает temp (адрес сайта определяется
//    самим сайтом автоматически при первом открытии).
//  - Создаёт install/DISABLE_INSTALLER.php (для переустановки
//    удалить этот файл).
// В игровые базы записи НЕ производятся.
// ============================================================
if (file_exists(__DIR__ . '/DISABLE_INSTALLER.php')) {
    die('Function disabled. To enable delete file DISABLE_INSTALLER.php from /install folder. Readd this file if you\'re done!');
}
if (version_compare(PHP_VERSION, '8.0', '<')) {
    die('This installer requires PHP 8.0 or newer. Current: ' . PHP_VERSION);
}
set_time_limit(0); // импорт armory-дампов (~25 МБ) может идти дольше дефолтного лимита

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function import_sql_file(mysqli $con, string $path, ?string $authDb = null): array
{
    // Покосточный разбор дампов: пустые строки и «-- » пропускаем,
    // «;» в конце строки = конец запроса (дампы экранируют переводы строк в данных).
    $text = @file_get_contents($path);
    if ($text === false) {
        return array(0, 'Не удалось прочитать файл ' . $path);
    }
    // UTF-8 BOM ( блокнот/PowerShell) иначе липнет к первому запросу/комментарию
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }
    if ($authDb !== null) {
        $text = str_replace('{{AUTH_DB}}', $authDb, $text);
    }
    $ok = 0;
    $buf = '';
    foreach (explode("\n", $text) as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '--')) {
            continue;
        }
        $buf .= $line . "\n";
        if (str_ends_with($t, ';')) {
            if (!mysqli_query($con, $buf)) {
                return array($ok, mysqli_error($con) . ' :: ' . mb_substr(trim($buf), 0, 120));
            }
            $ok++;
            $buf = '';
        }
    }
    if (trim($buf) !== '' && !mysqli_query($con, $buf)) {
        return array($ok, mysqli_error($con) . ' :: ' . mb_substr(trim($buf), 0, 120));
    }
    return array($ok, '');
}

function postv(array $src, string $key, string $def = ''): string
{
    return isset($src[$key]) && $src[$key] !== '' ? trim((string)$src[$key]) : $def;
}

$def = array(
    'db_host'     => '127.0.0.1',
    'db_port'     => '3306',
    'db_username' => 'acore',
    'db_password' => '',
    'auth_db'     => 'acore_auth',
    'char_db'     => 'acore_characters',
    'world_db'    => 'acore_world',
    'web_db'      => 'site',
    // данные сайта (пишутся в config/config.xml)
    'site_title'  => 'AzerothCore',
    'site_email'  => '',
    'site_cookie' => 'acoreWeb',
    'expansion'   => '2',
);
$f = $def;
foreach ($def as $k => $v) {
    $f[$k] = postv($_POST, $k, $v);
}
$f['db_password'] = postv($_POST, 'db_password', '');

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$errors = array();
$done   = array();

if ($isPost) {
    // имена баз подставляются в SQL бэктиками — только латиница/цифры/подчёркивание
    foreach (array('auth_db', 'char_db', 'world_db', 'web_db') as $k) {
        if (!preg_match('/^[A-Za-z0-9_]{1,60}$/', $f[$k])) {
            $errors[] = "Недопустимое имя базы в поле «$k»: только латиница, цифры, подчёркивание.";
        }
    }
    if (!preg_match('/^\d{1,5}$/', $f['db_port'])) {
        $errors[] = 'Недопустимый порт.';
    }
    // --- данные сайта ---
    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $f['site_cookie'])) {
        $errors[] = 'Имя cookie: только латиница, цифры, «-» и «_» (без пробелов).';
    }
    if ($f['site_email'] !== '' && !filter_var($f['site_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Недопустимый email сайта.';
    }
    if (!in_array($f['expansion'], array('0', '1', '2'), true)) {
        $errors[] = 'Expansion должен быть 0, 1 или 2 (для 3.3.5 — 2).';
    }

    $con = null;
    if (!$errors) {
        $con = @mysqli_connect($f['db_host'], $f['db_username'], $f['db_password'], '', (int)$f['db_port']);
        if (!$con) {
            $errors[] = 'Не удалось подключиться к MySQL: ' . mysqli_connect_error();
        }
    }

    if ($con) {
        mysqli_set_charset($con, 'utf8mb4');

        // --- игровые базы: существование и состав (только чтение) ---
        foreach (array('auth_db' => 'auth (аккаунты)', 'char_db' => 'characters', 'world_db' => 'world') as $k => $label) {
            if (!mysqli_select_db($con, $f[$k])) {
                $errors[] = "База «{$f[$k]}» ($label) не найдена: " . mysqli_error($con);
            }
        }

        if (!$errors) {
            // auth: таблица account с salt+verifier = AzerothCore
            $st = mysqli_prepare($con, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='account' AND column_name IN ('salt','verifier')");
            mysqli_stmt_bind_param($st, 's', $f['auth_db']);
            mysqli_stmt_execute($st);
            mysqli_stmt_bind_result($st, $cnt);
            mysqli_stmt_fetch($st);
            mysqli_stmt_close($st);
            if ((int)$cnt !== 2) {
                $errors[] = "В базе «{$f['auth_db']}» нет таблицы account с колонками salt/verifier — это не AzerothCore auth-база.";
            }

            $st = mysqli_prepare($con, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name='characters'");
            mysqli_stmt_bind_param($st, 's', $f['char_db']);
            mysqli_stmt_execute($st);
            mysqli_stmt_bind_result($st, $cnt);
            mysqli_stmt_fetch($st);
            mysqli_stmt_close($st);
            if ((int)$cnt !== 1) {
                $errors[] = "В базе «{$f['char_db']}» нет таблицы characters — это не AzerothCore characters-база.";
            }

            $st = mysqli_prepare($con, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name='creature_template'");
            mysqli_stmt_bind_param($st, 's', $f['world_db']);
            mysqli_stmt_execute($st);
            mysqli_stmt_bind_result($st, $cnt);
            mysqli_stmt_fetch($st);
            mysqli_stmt_close($st);
            if ((int)$cnt !== 1) {
                $errors[] = "В базе «{$f['world_db']}» нет таблицы creature_template — это не AzerothCore world-база.";
            }
        }

        // --- веб-база ---
        $web = $f['web_db'];
        if (!$errors) {
            if (mysqli_query($con, "CREATE DATABASE IF NOT EXISTS `$web` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                $done[] = "Веб-база «{$web}» готова (создана или уже существовала).";
            } else {
                $errors[] = 'CREATE DATABASE: ' . mysqli_error($con);
            }
        }

        if (!$errors && !mysqli_select_db($con, $web)) {
            $errors[] = 'Не удалось выбрать веб-базу: ' . mysqli_error($con);
        }

        if (!$errors) {
            $sqlFile = __DIR__ . '/sql/site.sql';
            if (!is_readable($sqlFile)) {
                $errors[] = 'Не найден файл схемы: install/sql/site.sql';
            } else {
                list($ok, $err) = import_sql_file($con, $sqlFile, $f['auth_db']);
                if ($err !== '') {
                    $errors[] = 'Ошибка импорта схемы: ' . $err;
                } else {
                    $done[] = "Схема импортирована в «{$web}» ($ok запросов).";

                    // realm_settings (обе таблицы) по realmlist из auth-базы
                    $auth = $f['auth_db'];
                    $realms = array();
                    $q = mysqli_query($con, "SELECT `id` FROM `$auth`.`realmlist` ORDER BY `id`");
                    if ($q) {
                        while ($r = mysqli_fetch_assoc($q)) {
                            $realms[] = (int)$r['id'];
                        }
                    } else {
                        $errors[] = "Не удалось прочитать realmlist из «$auth»: " . mysqli_error($con);
                    }
                    if (!$errors && !$realms) {
                        $realms = array(1);
                        $done[] = 'realmlist пуст — записан realm_settings для id_realm=1.';
                    }
                    if (!$errors) {
                        foreach (array('website_realm_settings', 'realm_settings') as $tbl) {
                            $st = mysqli_prepare($con, "REPLACE INTO `$web`.`$tbl` (id_realm, dbuser, dbpass, dbhost, dbport, dbname, chardbname) VALUES (?,?,?,?,?,?,?)");
                            mysqli_stmt_bind_param($st, 'issssss', $rid, $f['db_username'], $f['db_password'], $f['db_host'], $f['db_port'], $f['world_db'], $f['char_db']);
                            foreach ($realms as $rid) {
                                if (!mysqli_stmt_execute($st)) {
                                    $errors[] = "$tbl: " . mysqli_stmt_error($st);
                                    break;
                                }
                            }
                            mysqli_stmt_close($st);
                            if ($errors) {
                                break;
                            }
                        }
                        if (!$errors) {
                            $done[] = 'website_realm_settings/realm_settings заполнены для realm(ов): ' . implode(', ', $realms) . '.';
                        }
                    }
                }
            }
        }

        // --- Armory (MBA): справочные таблицы в веб-базу ---
        // Дампы: upstream MBA (main/en_us/cache) + сгенерированные под ACo 3.3.5
        // (armory_titles, armory_instances, dbc_*; генераторы лежат рядом).
        if (!$errors) {
            $armoryFiles = array(
                'armory_3.3.2_main.sql',
                'armory_3.3.2_en_us.sql',
                'armory_cache.sql',
                'armory_titles.sql',
                'armory_instances.sql',
                'dbc_creaturedisplayinfo.sql',
                'dbc_creaturedisplayinfoextra.sql',
                'dbc_itemdisplayinfo_full.sql',
            );
            $armoryQueries = 0;
            foreach ($armoryFiles as $af) {
                $afPath = __DIR__ . '/sql/armory/' . $af;
                if (!is_readable($afPath)) {
                    $errors[] = "Не найден файл Armory: install/sql/armory/$af";
                    break;
                }
                list($aq, $aerr) = import_sql_file($con, $afPath);
                if ($aerr !== '') {
                    $errors[] = "Armory ($af): $aerr";
                    break;
                }
                $armoryQueries += $aq;
            }
            if (!$errors) {
                $done[] = "Armory: импортировано файлов: " . count($armoryFiles) . " ($armoryQueries запросов).";
            }
            // Дампы выполняют SET NAMES utf8 — возвращаем соединению utf8mb4
            mysqli_set_charset($con, 'utf8mb4');
        }

        // --- config-protected.php ---
        if (!$errors) {
            $confPath = dirname(__DIR__) . '/config/config-protected.php';
            if (!is_dir(dirname($confPath))) {
                mkdir(dirname($confPath), 0777, true);
            }
            $esc = function (string $s): string {
                return str_replace("'", "\\'", $s);
            };
            $dbBlock = function (string $var, string $dbname) use ($f, $esc): string {
                $b  = "\$$var = array(\n";
                $b .= "'db_type'         => 'mysql',\n";
                $b .= "'db_host'         => '" . $esc($f['db_host']) . "',\n";
                $b .= "'db_port'         => '" . $esc($f['db_port']) . "',\n";
                $b .= "'db_username'     => '" . $esc($f['db_username']) . "',\n";
                $b .= "'db_password'     => '" . $esc($f['db_password']) . "',\n";
                $b .= "'db_name'         => '" . $esc($dbname) . "',\n";
                $b .= "'db_encoding'     => 'utf8mb4',\n";
                $b .= ");\n";
                return $b;
            };
            $build  = "<?php\n";
            $build .= "// Сгенерирован установщиком " . date('Y-m-d H:i:s') . "\n";
            $build .= "// AzerothCore: \$realmd — auth-база, \$websitedb — веб-база сайта\n";
            $build .= $dbBlock('realmd', $f['auth_db']);
            $build .= $dbBlock('websitedb', $f['web_db']);
            $build .= "?>";
            if (file_put_contents($confPath, $build) === false) {
                $errors[] = "Не удалось записать $confPath (проверь права на папку config).";
            } else {
                $done[] = 'Записан config/config-protected.php.';
            }
        }

        // --- config.xml: данные сайта ---
        if (!$errors) {
            $xmlPath = dirname(__DIR__) . '/config/config.xml';
            $xml = @simplexml_load_file($xmlPath);
            if (!$xml) {
                $errors[] = "Не удалось прочитать $xmlPath";
            } else {
                if ($f['site_title'] !== '') {
                    $xml->generic->site_title = $f['site_title'];
                }
                if ($f['site_email'] !== '') {
                    $xml->generic->site_email = $f['site_email'];
                }
                $xml->generic->site_cookie = $f['site_cookie'];
                $xml->generic->expansion   = (int)$f['expansion'];
                // temp-адреса (site_href/site_domain/base_href/email_href) очищаются:
                // сайт определит их сам из первого обращения и перезапишет
                foreach (array('site_href', 'site_domain', 'email_href', 'base_href') as $tk) {
                    if (isset($xml->temp->$tk)) {
                        $xml->temp->$tk = '';
                    }
                }
                if (@file_put_contents($xmlPath, $xml->asXML()) === false) {
                    $errors[] = "Не удалось записать $xmlPath (права на папку config). Поля в config.xml: site_title, site_email, site_cookie, expansion; temp можно очистить вручную.";
                } else {
                    $done[] = 'Обновлён config/config.xml (название сайта, email, cookie, expansion; temp очищен — адрес сайт определит сам).';
                }
            }
        }

        // --- запечатать установщик ---
        if (!$errors) {
            if (@file_put_contents(__DIR__ . '/DISABLE_INSTALLER.php', '<?php // Установщик выполнен ' . date('Y-m-d H:i:s') . '. Для переустановки удали этот файл.')) {
                $done[] = 'Установщик запечатан (install/DISABLE_INSTALLER.php).';
            } else {
                $done[] = 'ВНИМАНИЕ: не удалось создать DISABLE_INSTALLER.php — удали папку install вручную после проверки.';
            }
        }

        mysqli_close($con);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SITE Installer — AzerothCore</title>
<style>
body { background:#101014; color:#cfcfcf; font-family:Segoe UI,Arial,sans-serif; margin:0; }
.wrap { width:760px; margin:30px auto; background:#1b1b22; border:1px solid #333; padding:24px 30px; }
h1 { color:#c8a44d; font-size:20px; margin-top:0; }
table td { padding:4px 8px 4px 0; }
input { background:#26262e; color:#eee; border:1px solid #444; padding:4px 8px; width:260px; }
.hint { color:#888; font-size:12px; }
.err { color:#ff7b72; }
.ok  { color:#7ee787; }
.note { color:#9a9a9a; }
input[type=submit] { width:auto; background:#3a3a1e; border:1px solid #c8a44d; color:#c8a44d; padding:6px 18px; cursor:pointer; }
</style>
</head>
<body>
<div class="wrap">
<?php if (!$isPost): ?>
<h1>Установщик — шаг 1/2: MySQL и данные сайта</h1>
<p class="note">Игровые базы AzerothCore только проверяются и читаются — изменения в них не вносятся.<br>
Веб-таблицы сайта будут созданы в отдельной веб-базе.</p>
<form action="index.php" method="post">
<table>
<tr><td>Host:</td><td><input name="db_host" value="<?= e($f['db_host']) ?>"></td><td class="hint">обычно 127.0.0.1</td></tr>
<tr><td>Port:</td><td><input name="db_port" value="<?= e($f['db_port']) ?>"></td><td class="hint">у MySQL 8 по умолчанию 3306</td></tr>
<tr><td>Username:</td><td><input name="db_username" value="<?= e($f['db_username']) ?>"></td><td class="hint"></td></tr>
<tr><td>Password:</td><td><input type="password" name="db_password" value=""></td><td class="hint"></td></tr>
<tr><td colspan="3"><br><b>Имена баз:</b></td></tr>
<tr><td>Auth db:</td><td><input name="auth_db" value="<?= e($f['auth_db']) ?>"></td><td class="hint">аккаунты, SRP6, realmlist</td></tr>
<tr><td>Characters db:</td><td><input name="char_db" value="<?= e($f['char_db']) ?>"></td><td class="hint"></td></tr>
<tr><td>World db:</td><td><input name="world_db" value="<?= e($f['world_db']) ?>"></td><td class="hint"></td></tr>
<tr><td>Web db:</td><td><input name="web_db" value="<?= e($f['web_db']) ?>"></td><td class="hint">создастся, если её нет; в ней будут website_* таблицы</td></tr>
<tr><td colspan="3"><br><b>Данные сайта</b> (записываются в config/config.xml):</td></tr>
<tr><td>Название сайта:</td><td><input name="site_title" value="<?= e($f['site_title']) ?>"></td><td class="hint">site_title — заголовок страниц и писем</td></tr>
<tr><td>Email сайта:</td><td><input name="site_email" value="<?= e($f['site_email']) ?>"></td><td class="hint">отправитель писем (активация, восстановление пароля)</td></tr>
<tr><td>Cookie name:</td><td><input name="site_cookie" value="<?= e($f['site_cookie']) ?>"></td><td class="hint">site_cookie — уникальное имя cookie сайта</td></tr>
<tr><td>Expansion:</td><td>
<select name="expansion">
<option value="2"<?= $f['expansion']==='2' ? ' selected' : '' ?>>2 — WotLK 3.3.5 (AzerothCore)</option>
<option value="1"<?= $f['expansion']==='1' ? ' selected' : '' ?>>1 — The Burning Crusade</option>
<option value="0"<?= $f['expansion']==='0' ? ' selected' : '' ?>>0 — Vanilla</option>
</select></td><td class="hint">для AzerothCore 3.3.5 оставляй 2</td></tr>
<tr><td colspan="3" class="hint">Адрес/домен сайта указывать не нужно: сайт определит их автоматически при первом открытии.</td></tr>
</table>
<br>
<input type="submit" value="Проверить и установить">
</form>
<?php else: ?>
<h1>Установщик — шаг 2/2: результат</h1>
<?php if ($errors): ?>
<p class="err"><b>Остановлено с ошибками:</b></p>
<ul class="err"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul>
<?php endif; ?>
<?php if ($done): ?>
<ul class="ok"><?php foreach ($done as $d) echo '<li>' . e($d) . '</li>'; ?></ul>
<?php endif; ?>
<?php if (!$errors): ?>
<p class="ok"><b>Установка завершена.</b> Проверь config/config-protected.php и открывай сайт.</p>
<?php else: ?>
<p><a href="index.php" style="color:#c8a44d">&larr; вернуться назад</a></p>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
