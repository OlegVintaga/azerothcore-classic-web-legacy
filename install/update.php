<?php
/**
 * Обновитель базы сайта.
 *
 * Файлы обновлений лежат в install/sql/updates/ с числовым префиксом схемы:
 *   0002_add_donation_table.sql
 *   0003_items_locale.sql
 * Каждый файл применяется ровно один раз: применяются все файлы с номером
 * больше текущего website_version.version, после применения номер фиксируется.
 * Плейсхолдер {{AUTH_DB}} в SQL заменяется на имя auth-базы из конфига,
 * как в установщике.
 *
 * Запуск:
 *   - браузер: http://сайт/install/update.php  (подтверждение паролем MySQL
 *     из config/config-protected.php — без него страницу может открыть любой);
 *   - CLI:     php install/update.php --yes
 *
 * Файлы обновлений НЕ удаляйте и не переименовывайте — по номеру определяется,
 * что уже применено. Старые файлы можно выкладывать заново с каждым релизом.
 */

if (!function_exists('update_apply_sql')) {

/** Покосточный разбор, как в установщике: «;» в конце строки = конец запроса. */
function update_apply_sql(mysqli $con, string $path, string $authDb): array
{
    $text = @file_get_contents($path);
    if ($text === false) {
        return array(0, 'Не удалось прочитать файл ' . $path);
    }
    // UTF-8 BOM ( блокнот/PowerShell) иначе липнет к первому запросу/комментарию
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }
    $text = str_replace('{{AUTH_DB}}', $authDb, $text);
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

}

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    echo '<!doctype html><meta charset="utf-8"><title>Site DB update</title><pre>';
}

$confPath = dirname(__DIR__) . '/config/config-protected.php';
if (!is_file($confPath)) {
    die("config/config-protected.php не найден — сначала установите сайт.\n");
}
$realmd = null;
$websitedb = null;
include $confPath;
if (!is_array($websitedb) || empty($websitedb['db_name'])) {
    die("В конфиге нет записи базы сайта (websitedb).\n");
}
$authDbName = (is_array($realmd) && !empty($realmd['db_name'])) ? (string)$realmd['db_name'] : '';

if ($isCli) {
    if (!in_array('--yes', isset($_SERVER['argv']) ? $_SERVER['argv'] : array(), true)) {
        die("Запуск из консоли: php install/update.php --yes\n");
    }
} else {
    // Веб-режим: пустить нельзя, этоDDL над базой. Требует пароль MySQL из конфига сайта.
    $passOk = isset($_POST['dbpass']) && hash_equals((string)$websitedb['db_password'], (string)$_POST['dbpass']);
    if (!$passOk) {
        ?>
        <form method="post">
        Обновление базы сайта «<?= htmlspecialchars((string)$websitedb['db_name']) ?>».<br>
        Подтвердите паролем MySQL, который сайт использует (config/config-protected.php):<br>
        <input type="password" name="dbpass" size="30"> <input type="submit" value="Обновить">
        </form>
        <?php
        die();
    }
}

$con = @mysqli_connect((string)$websitedb['db_host'], (string)$websitedb['db_username'], (string)$websitedb['db_password'],
    (string)$websitedb['db_name'], (int)$websitedb['db_port']);
if (!$con) {
    die("Не удалось подключиться к базе сайта: " . mysqli_connect_error() . "\n");
}
mysqli_set_charset($con, 'utf8mb4');

// Таблица версии: для установок до её появления — считаем текущей базовой (1).
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `website_version` (`version` INT NOT NULL) ENGINE=InnoDB");
$cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM `website_version`"));
if ((int)$cnt['c'] === 0) {
    mysqli_query($con, "INSERT INTO `website_version` (`version`) VALUES (1)");
}
$cur = (int)mysqli_fetch_assoc(mysqli_query($con, "SELECT MAX(`version`) AS v FROM `website_version`"))['v'];
echo "Текущая версия схемы базы сайта: {$cur}\n";

// Поиск новых файлов
$dir = __DIR__ . '/sql/updates';
$files = array();
foreach (is_dir($dir) ? glob($dir . '/*.sql') : array() as $p) {
    if (preg_match('/^(\d+)_/', basename($p), $m)) {
        $files[(int)$m[1]] = $p;
    }
}
ksort($files);

$applied = 0;
$failed = false;
foreach ($files as $ver => $path) {
    if ($ver <= $cur) {
        continue;
    }
    list($ok, $err) = update_apply_sql($con, $path, $authDbName);
    if ($err !== '') {
        $failed = true;
        echo "ОШИБКА в " . basename($path) . ": {$err}\n";
        echo "Обновление остановлено; применённые ранее файлы уже зафиксированы.\n";
        break;
    }
    mysqli_query($con, "DELETE FROM `website_version`");
    mysqli_query($con, "INSERT INTO `website_version` (`version`) VALUES ($ver)");
    $cur = $ver;
    $applied++;
    echo "Применено: " . basename($path) . " (запросов: $ok, версия схемы: $ver)\n";
}
if ($applied === 0 && !$failed) {
    echo "Новых обновлений нет.\n";
} else {
    echo "Готово: применено файлов — $applied, версия схемы — $cur.\n";
}
mysqli_close($con);
if (!$isCli) {
    echo "</pre>";
}
