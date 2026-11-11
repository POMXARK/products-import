<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Upload;

use App\Application\Shared\ValidationException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * ТЗ: "валидация входящего файла: тип MIME, размер, расширение".
 *
 * Проверка идёт до постановки в очередь, чтобы клиент сразу получил 422, а не узнал о
 * неверном файле из статуса задачи. MIME определяется по содержимому (finfo), а не по
 * заголовку от браузера, и дополнительно проверяется сигнатура ZIP: xlsx это ZIP-архив.
 */
final readonly class XlsxUploadValidator
{
    private const array ALLOWED_MIME = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    /** Максимальный размер файла из IMPORT_MAX_FILE_BYTES. */
    public function __construct(private int $maxBytes)
    {
    }

    /** Возвращает путь к временному файлу, если он прошёл все проверки, иначе бросает ValidationException (422). */
    public function validate(?UploadedFileInterface $file): string
    {
        if (null === $file || \UPLOAD_ERR_NO_FILE === $file->getError()) {
            throw new ValidationException(['file' => 'Файл не передан'], 'Файл не передан');
        }
        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw $this->tooLarge();
        }
        if (\UPLOAD_ERR_OK !== $file->getError()) {
            throw new ValidationException(['file' => 'Файл загружен не полностью'], 'Ошибка загрузки файла');
        }

        $size = $file->getSize() ?? 0;
        if ($size <= 0) {
            throw new ValidationException(['file' => 'Файл пустой'], 'Файл пустой');
        }
        if ($size > $this->maxBytes) {
            throw $this->tooLarge();
        }

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), \PATHINFO_EXTENSION));
        if ('xlsx' !== $extension) {
            throw new ValidationException(['file' => 'Допустимо только расширение .xlsx'], 'Неверное расширение файла');
        }

        $path = $file->getStream()->getMetadata('uri');
        if (!\is_string($path) || !is_file($path)) {
            throw new ValidationException(['file' => 'Файл недоступен'], 'Файл недоступен');
        }

        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->file($path);
        if (!\in_array($mime, self::ALLOWED_MIME, true) || !$this->hasZipSignature($path)) {
            throw new ValidationException(['file' => 'Содержимое файла не является xlsx'], 'Неверный тип файла');
        }

        return $path;
    }

    /** xlsx начинается с сигнатуры ZIP "PK\x03\x04": отсекает переименованные csv и txt. */
    private function hasZipSignature(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            return false;
        }
        $signature = fread($handle, 4);
        fclose($handle);

        return "PK\x03\x04" === $signature;
    }

    /** Ошибка превышения размера с лимитом в мегабайтах для понятного сообщения. */
    private function tooLarge(): ValidationException
    {
        $mb = round($this->maxBytes / 1024 / 1024, 1);

        return new ValidationException(['file' => \sprintf('Размер файла больше %s МБ', $mb)], 'Файл слишком большой');
    }
}
