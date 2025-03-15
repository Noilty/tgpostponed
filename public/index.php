<?php

declare(strict_types=1);

#!/usr/bin/php

/**
 * tgpostponed
 * 
 * cd \public $ php -f index.php
 */

require '../vendor/autoload.php';
require '../constants.php';

use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

if ('cli' !== PHP_SAPI) {
    // dd(php_sapi_name(), PHP_SAPI);
    throw new Exception('tgpostponed - это консольное приложение.');
}

$client = new Client(['base_uri' => 'https://api.telegram.org/bot' . TG_CHANNEL_BOT_TOKEN . '/']);

$folders = getData(FTP_DIR);

for ($postKey = 0; $postKey < POST_COUNT; $postKey++) {
    $randomFolder = getRandomElem($folders);

    $dirRandom = FTP_DIR . $randomFolder;
    $dirPublished = $dirRandom . '/published';
    $dirUnpublished = $dirRandom . '/unpublished';

    $imgName = getRandomElem(getData($dirUnpublished));
    $imgPath = $dirUnpublished . '/' . $imgName;
    $imgInfo = pathinfo($imgPath);

    [$imgDirname, $imgBasename, $imgExtension, $imgFilename] = array_values($imgInfo);

    $uuid = prepareUuid($imgFilename);

    try {
        $res = $client->post('sendPhoto', [
            'multipart' => prepareMultipart([
                'chat_id' => TG_CHANNEL,
                'photo' => fopen($imgPath, 'r'),
                'caption' => "`". strtoupper($uuid) . "`" . "\r\n🏷 #$randomFolder" . TG_CHANNEL,
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

    if (! file_exists($imgPath)) {
        throw new Exception("Ошибка: исходный файл не найден.");
    }

    // if (! is_dir($newDir)) {
    //     mkdir($newDir, 0777, true);
    // }

    $from = $imgPath;
    $to = $dirPublished . '/' . $uuid . '.' . $imgExtension;
    // if (rename($from = $imgPath, $to = $dirPublished . '/' . $uuid . '.' . $imgExtension)) {    
        echo "Файл успешно перемещен и переименован.\r\n$from\r\n$to" . PHP_EOL;
        // continue;
    // }
    
    // echo "Ошибка: не удалось переместить файл.";
    // return;

    sleep(rand(0, 3));
}

// --------------------------------------------------------------------------------------------------

function getData(string $dir): array
{
    if (! is_dir($dir)) {
        throw new Exception('Путь недоступен');
    }

    $data = scandir($dir, SCANDIR_SORT_DESCENDING);

    return array_filter($data, function (string $file) {
        return ! in_array($file, ['.', '..', '_from_sort']);
    });
}

function getRandomElem(array $folders): string
{
    return $folders[array_rand($folders)];
}

function prepareMultipart(array $params): array
{
    return array_map(
        fn($key, $value) => ['name' => $key, 'contents' => $value],
        array_keys($params),
        $params
    );
}

function prepareUuid(string $filename): string
{
    return ! Uuid::isValid($filename) ? generateUuid() : $filename;
}

function generateUuid(): string
{
    return Uuid::uuid4()->toString();
}

function dump(...$data): void
{
    echo '<pre>' . var_export($data);
}

function dd(...$data): void
{
    dump(...$data);
    die();
}
