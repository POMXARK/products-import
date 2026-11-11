<?php

declare(strict_types=1);

namespace App\Domain\Import;

/**
 * Задача импорта: статус, прогресс и отчёт о проблемных строках.
 * Прогресс во время обработки пишется отдельным UPDATE (см. ImportJobRepository::saveProgress),
 * чтобы не смешивать его с транзакцией записи товара.
 */
class ImportJob
{
    /** Ограничение размера отчёта: файл из миллиона битых строк не должен раздуть JSON-колонку. */
    public const int MAX_ISSUES = 1000;

    private ImportStatus $status = ImportStatus::Queued;

    private ?int $totalRows = null;

    private int $processedRows = 0;

    private int $createdCount = 0;

    private int $updatedCount = 0;

    private int $failedCount = 0;

    private bool $issuesTruncated = false;

    /** @var list<array{row: int, severity: string, message: string, externalCode: ?string, field: ?string}> */
    private array $issues = [];

    private ?string $failureReason = null;

    private ?\DateTimeImmutable $startedAt = null;

    private ?\DateTimeImmutable $finishedAt = null;

    private \DateTimeImmutable $createdAt;

    /**
     * Новая задача создаётся при загрузке файла в статусе queued: файл уже сохранён,
     * обработку выполнит воркер. createdBy фиксирует, кто запустил импорт.
     */
    public function __construct(
        private string $id,
        private string $originalFilename,
        private string $storedPath,
        private ?int $createdBy = null,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->createdAt = $now ?? new \DateTimeImmutable();
    }

    /**
     * Повторная доставка сообщения после падения воркера допустима:
     * upsert по external_code делает перезапуск безопасным, счётчики начинаются заново.
     */
    public function start(?\DateTimeImmutable $now = null): void
    {
        if ($this->status->isFinal()) {
            throw InvalidImportTransition::from($this->id, $this->status, ImportStatus::Processing);
        }

        $this->status = ImportStatus::Processing;
        $this->startedAt = $now ?? new \DateTimeImmutable();
        $this->totalRows = null;
        $this->processedRows = 0;
        $this->createdCount = 0;
        $this->updatedCount = 0;
        $this->failedCount = 0;
        $this->issues = [];
        $this->issuesTruncated = false;
    }

    /** Общее число строк файла известно только после чтения; нужно для процента прогресса в карточке задачи. */
    public function setTotalRows(int $totalRows): void
    {
        $this->assertProcessing();
        $this->totalRows = $totalRows;
    }

    /** Строка с новым external_code: товар создан. */
    public function recordCreated(): void
    {
        $this->assertProcessing();
        ++$this->createdCount;
        ++$this->processedRows;
    }

    /** Строка с уже существующим external_code: товар обновлён (upsert по ТЗ). */
    public function recordUpdated(): void
    {
        $this->assertProcessing();
        ++$this->updatedCount;
        ++$this->processedRows;
    }

    /** Строка не прошла валидацию или запись: товар пропущен, причина уходит в отчёт об ошибках. */
    public function recordFailed(): void
    {
        $this->assertProcessing();
        ++$this->failedCount;
        ++$this->processedRows;
    }

    /** Добавляет ошибку или предупреждение в отчёт; сверх MAX_ISSUES отчёт помечается усечённым. */
    public function addIssue(ImportIssue $issue): void
    {
        if (\count($this->issues) >= self::MAX_ISSUES) {
            $this->issuesTruncated = true;

            return;
        }
        $this->issues[] = $issue->toArray();
    }

    /**
     * Файл обработан до конца: processing переходит в completed. Битые строки не делают импорт failed,
     * они видны в отчёте.
     */
    public function complete(?\DateTimeImmutable $now = null): void
    {
        if (ImportStatus::Processing !== $this->status) {
            throw InvalidImportTransition::from($this->id, $this->status, ImportStatus::Completed);
        }
        $this->status = ImportStatus::Completed;
        $this->finishedAt = $now ?? new \DateTimeImmutable();
    }

    /**
     * Импорт не удалось выполнить целиком (файл не читается, исчерпаны повторы): переход в failed с причиной.
     * Из финального статуса перейти нельзя: completed не может стать failed задним числом.
     */
    public function fail(string $reason, ?\DateTimeImmutable $now = null): void
    {
        if ($this->status->isFinal()) {
            throw InvalidImportTransition::from($this->id, $this->status, ImportStatus::Failed);
        }
        $this->status = ImportStatus::Failed;
        $this->failureReason = mb_substr($reason, 0, 2000);
        $this->finishedAt = $now ?? new \DateTimeImmutable();
    }

    /** Счётчики меняются только во время обработки: защищает от записи прогресса в завершённую задачу. */
    private function assertProcessing(): void
    {
        if (ImportStatus::Processing !== $this->status) {
            throw new InvalidImportTransition(\sprintf('Import %s is not processing (status %s)', $this->id, $this->status->value));
        }
    }

    /** UUIDv7 задачи: по нему клиент опрашивает статус. */
    public function getId(): string
    {
        return $this->id;
    }

    /** Текущий статус: queued, processing, completed или failed. */
    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    /** Имя файла, как его загрузил пользователь: показывается в истории импортов. */
    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    /** Путь к сохранённому файлу в хранилище, который читает воркер. */
    public function getStoredPath(): string
    {
        return $this->storedPath;
    }

    /** Идентификатор пользователя, загрузившего файл. */
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    /** Число строк с данными в файле; null, пока воркер не начал чтение. */
    public function getTotalRows(): ?int
    {
        return $this->totalRows;
    }

    /** Сколько строк уже обработано: основа процента прогресса. */
    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    /** Сколько товаров создано. */
    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    /** Сколько товаров обновлено. */
    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    /** Сколько строк пропущено из-за ошибок. */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * Отчёт об ошибках по строкам: показывается пользователю после импорта.
     *
     * @return list<ImportIssue>
     */
    public function getIssues(): array
    {
        return array_map(ImportIssue::fromArray(...), $this->issues);
    }

    /** Признак, что отчёт обрезан до MAX_ISSUES записей. */
    public function isIssuesTruncated(): bool
    {
        return $this->issuesTruncated;
    }

    /** Причина падения всего импорта; null, если импорт не в статусе failed. */
    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /** Когда воркер взял задачу в работу. */
    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    /** Когда импорт завершился (успешно или с ошибкой). */
    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** Когда файл был загружен. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
