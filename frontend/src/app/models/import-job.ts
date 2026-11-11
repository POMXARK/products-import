/** Статусы асинхронного импорта: в очереди, обрабатывается, завершён, упал. */
export type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed';

/** Подписи статусов для интерфейса. */
export const IMPORT_STATUS_LABELS: Record<ImportStatus, string> = {
  queued: 'В очереди',
  processing: 'Обрабатывается',
  completed: 'Завершён',
  failed: 'Ошибка',
};

/** Запись отчёта об ошибках: номер строки Excel, серьёзность, текст, код товара и колонка. */
export interface ImportIssue {
  row: number;
  severity: 'error' | 'warning';
  message: string;
  externalCode: string | null;
  field: string | null;
}

/** Задача импорта в истории загрузок (без отчёта). */
export interface ImportJobSummary {
  id: string;
  status: ImportStatus;
  filename: string;
  /** Процент 0–100; 100 только у завершённой задачи. */
  progress: number;
  totalRows: number | null;
  processedRows: number;
  createdCount: number;
  updatedCount: number;
  failedCount: number;
  failureReason: string | null;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

/** Задача импорта с отчётом об ошибках по строкам. */
export interface ImportJob extends ImportJobSummary {
  issues: ImportIssue[];
  /** Отчёт обрезан до первых 1000 записей. */
  issuesTruncated: boolean;
}

/** Завершённую задачу больше не нужно опрашивать. */
export function isFinalStatus(status: ImportStatus): boolean {
  return status === 'completed' || status === 'failed';
}
