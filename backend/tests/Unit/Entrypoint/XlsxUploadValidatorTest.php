<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entrypoint;

use App\Application\Shared\ValidationException;
use App\Entrypoint\Http\Upload\XlsxUploadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

/** ТЗ: валидация загружаемого файла по MIME, размеру и расширению. */
final class XlsxUploadValidatorTest extends TestCase
{
    private const string SAMPLE = __DIR__.'/../../Fixtures/import_example.xlsx';

    /** @var list<string> */
    private array $tmp = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->tmp, is_file(...)));
    }

    private function upload(string $content, string $name, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);
        $this->tmp[] = $path;

        return new UploadedFile((new StreamFactory())->createStreamFromFile($path), $name, null, \strlen($content), $error);
    }

    public function testAcceptsRealXlsx(): void
    {
        $file = $this->upload((string) file_get_contents(self::SAMPLE), 'товары.xlsx');

        self::assertFileExists((new XlsxUploadValidator(10 * 1024 * 1024))->validate($file));
    }

    /** @return iterable<string, array{string, string, int, string}> */
    public static function invalidUploads(): iterable
    {
        yield 'нет файла' => ['', 'a.xlsx', \UPLOAD_ERR_NO_FILE, 'Файл не передан'];
        yield 'лимит php' => ['x', 'a.xlsx', \UPLOAD_ERR_INI_SIZE, 'Файл слишком большой'];
        yield 'пустой' => ['', 'a.xlsx', \UPLOAD_ERR_OK, 'Файл пустой'];
        yield 'чужое расширение' => ['PK', 'a.csv', \UPLOAD_ERR_OK, 'Неверное расширение файла'];
        yield 'переименованный текст' => ['just text', 'a.xlsx', \UPLOAD_ERR_OK, 'Неверный тип файла'];
    }

    #[DataProvider('invalidUploads')]
    public function testRejectsInvalidUploads(string $content, string $name, int $error, string $title): void
    {
        try {
            (new XlsxUploadValidator(1024 * 1024))->validate($this->upload($content, $name, $error));
            self::fail('Ожидалась ошибка валидации');
        } catch (ValidationException $e) {
            self::assertSame($title, $e->getMessage());
            self::assertArrayHasKey('file', $e->getErrors());
        }
    }

    public function testRejectsFileOverLimit(): void
    {
        $file = $this->upload((string) file_get_contents(self::SAMPLE), 'a.xlsx');

        $this->expectException(ValidationException::class);
        (new XlsxUploadValidator(100))->validate($file);
    }
}
