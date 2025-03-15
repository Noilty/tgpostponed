<?php

declare (strict_types = 1);

#!/usr/bin/php

/**
 * tgpostponed
 * Это консольный PHP-скрипт, принимающий один аргумент.
 *
 * cd \public $ php -f index.php
 */

if ('cli' !== PHP_SAPI) {
    // dd(php_sapi_name(), PHP_SAPI);
    throw new Exception('Этот скрипт может быть запущен только из командной строки.');
}

if (2 !== $argv && ! in_array($env = $argv[1], $envs = ['dev', 'prod'])) {
    throw new Exception('Использование: php -f index.php ' . implode('|', $envs));
}

# ----------------------------------------------------------------------------------------------------------------------

require '../vendor/autoload.php';
require "../constants.$env.php";

use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

$client = new Client([
    'base_uri' => $baseUri = strReplaceAssoc([
        ':token' => TG_CHANNEL_BOT_TOKEN,
    ], 'https://api.telegram.org/bot:token/'),
]);

$folders = getData(FTP_DIR);

for ($postKey = 0; $postKey < POST_COUNT; $postKey++) {
    $randomFolder = getRandomElem($folders);

    $dirRandom = FTP_DIR . $randomFolder;
    $dirPublished = "$dirRandom/published";
    $dirUnpublished = "$dirRandom/unpublished";

    $imgName = getRandomElem(getData($dirUnpublished));
    $imgPath = "$dirUnpublished/$imgName";
    $imgInfo = pathinfo($imgPath);

    if (! file_exists($imgPath)) {
        throw new Exception('Ошибка: исходный файл не найден.');
    }

    [$imgDirname, $imgBasename, $imgExtension, $imgFilename] = array_values($imgInfo);

    $imgUuid = prepareUuid($imgFilename);

    try {
        $res = $client->post('sendPhoto', [
            'multipart' => prepareMultipart([
                'chat_id' => TG_CHANNEL,
                'photo' => fopen($imgPath, 'r'),
                'caption' => "`" . strtoupper($imgUuid) . "`" . "\r\n🏷 #$randomFolder" . TG_CHANNEL,
                'disable_notification' => true,
                'parse_mode' => 'markdown',
            ]),
        ]);
    } catch (\Throwable $th) {
        throw new Exception($th->getMessage());
    }

    if (200 !== $statusCode = $res->getStatusCode()) {
        throw new Exception("Ошибка: фото не было отправлено, статус код: $statusCode.");
    }

    $from = $imgPath;
    $to = "$dirPublished/$imgUuid.$imgExtension";
    if (rename($from, $to)) {
        echo "|-- Файл успешно перемещен и переименован.\r\n|   |-- $from\r\n|   |-- $to" . PHP_EOL;
        sleep(rand(0, 3));
        continue;
    }

    throw new Exception('Ошибка: не удалось переместить файл.');
}

echo 'Скрипт успешно завершил свою работу.';

# ----------------------------------------------------------------------------------------------------------------------

/**
 * Получает список файлов из указанной директории, отфильтровывая служебные элементы.
 *
 * Функция проверяет, существует ли указанная директория, и сканирует её содержимое. 
 * Возвращается массив файлов, отсортированных в порядке убывания, исключая такие элементы, 
 * как `.` (текущая директория), `..` (родительская директория) и `_from_sort`.
 *
 * @param string $dir Путь к директории, содержимое которой нужно получить.
 * @return array Возвращает массив имен файлов, отфильтрованных от служебных элементов.
 * @throws \Exception Если директория недоступна или не существует.
 */
function getData(string $dir): array
{
    if (! is_dir($dir)) {
        throw new Exception('Путь недоступен.');
    }

    $data = scandir($dir, SCANDIR_SORT_DESCENDING);

    return array_filter($data, function (string $file): bool {
        return ! in_array($file, ['.', '..', '_from_sort']);
    });
}

/**
 * Возвращает случайный элемент из массива.
 *
 * Эта функция принимает массив `$elems` и возвращает один случайный элемент из него.
 *
 * @param array $elems Массив строк, из которого выбирается случайный элемент.
 * @return string Случайный элемент массива.
 * @throws \ValueError Если массив пуст (PHP выбросит ошибку при вызове array_rand).
 */
function getRandomElem(array $elems): string
{
    return $elems[array_rand($elems)];
}

/**
 * Преобразует массив параметров в формат для отправки multipart-запросов.
 *
 * Эта функция принимает ассоциативный массив `$params` и преобразует его в массив, 
 * где каждый элемент представляет собой массив с ключами `name` (имя параметра) и `contents` (содержимое параметра).
 * Такой формат обычно используется для отправки данных в multipart-запросах, например, при работе с библиотекой Guzzle.
 *
 * @param array $params Ассоциативный массив параметров (ключи — имена параметров, значения — их содержимое).
 * @return array Возвращает массив в формате multipart (каждый элемент содержит `name` и `contents`).
 */
function prepareMultipart(array $params): array
{
    return array_map(
        fn($key, $value) => ['name' => $key, 'contents' => $value],
        array_keys($params),
        $params
    );
}

/**
 * Проверяет, является ли строка действительным UUID, и при необходимости генерирует новый.
 *
 * Если переданное значение `$filename` не является корректным UUID, то 
 * функция генерирует и возвращает новый UUID. В противном случае возвращается само значение `$filename`.
 *
 * @param string $filename Строка, которую нужно проверить на соответствие формату UUID.
 * @return string Возвращает строку с действительным UUID (либо переданное значение, либо сгенерированный UUID).
 * @throws \Exception Если не удалось сгенерировать UUID.
 */
function prepareUuid(string $filename): string
{
    return ! Uuid::isValid($filename) ? generateUuid() : $filename;
}

/**
 * Генерирует уникальный идентификатор UUID версии 4.
 *
 * Использует библиотеку для работы с UUID (например, `ramsey/uuid`), чтобы 
 * создать уникальный идентификатор версии 4 (случайный).
 *
 * @return string Возвращает строковое представление UUID версии 4.
 * @throws \Exception Если не удалось сгенерировать UUID.
 */
function generateUuid(): string
{
    return Uuid::uuid4()->toString();
}

/**
 * Выполняет замену строк по ассоциативному массиву в указанной строке.
 *
 * Ассоциативный массив `$replace` содержит пары ключ-значение, где ключи будут заменены
 * на соответствующие значения в строке `$subject`.
 *
 * @param array $replace Ассоциативный массив замен (ключи — что заменить, значения — на что заменить).
 * @param string $subject Строка, в которой будут выполнены замены.
 * @return string Возвращает строку с выполненными заменами.
 */
function strReplaceAssoc(array $replace, string $subject): string
{
    return str_replace(array_keys($replace), array_values($replace), $subject);
}

/**
 * Возвращает массив, содержащий только указанные ключи.
 *
 * Эта функция принимает исходный массив `$array` и массив `$keys`, 
 * содержащий ключи, которые нужно оставить. Все остальные ключи будут удалены.
 *
 * @param array $array Исходный массив.
 * @param array $keys Массив ключей, которые нужно оставить в результирующем массиве.
 * @return array Возвращает новый массив, содержащий только указанные ключи.
 */
function arrayOnly(array $array, array $keys): array
{
    return array_intersect_key($array, array_flip((array) $keys));
}

/**
 * Выводит структурированную информацию о переданных данных.
 *
 * Функция принимает произвольное количество аргументов, выводит их в удобочитаемом формате
 * с использованием `var_export` и оборачивает в тег `<pre>` для красивого отображения в браузере.
 *
 * @param mixed ...$data Произвольные данные для вывода.
 * @return void
 */
function dump(...$data): void
{
    echo '<pre>' . var_export($data, true);
}

/**
 * Выводит структурированную информацию о переданных данных и завершает выполнение скрипта.
 *
 * Функция работает аналогично `dump`, но после вывода данных вызывает `die()`, чтобы завершить выполнение скрипта.
 * Полезно для отладки.
 *
 * @param mixed ...$data Произвольные данные для вывода.
 * @return void
 */
function dd(...$data): void
{
    dump(...$data);
    die();
}
