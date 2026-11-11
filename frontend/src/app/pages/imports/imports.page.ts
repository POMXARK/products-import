import { AsyncPipe, DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { BehaviorSubject, Observable, Subject, catchError, map, of, startWith, switchMap, tap } from 'rxjs';
import { errorMessage, toProblem } from '../../core/http/problem';
import { IMPORT_STATUS_LABELS, ImportJobSummary } from '../../models';
import { ImportService } from '../../services/import.service';
import { ImportProgressComponent } from '../../shared/import-progress/import-progress.component';

/** Лимит размера совпадает с IMPORT_MAX_FILE_BYTES на сервере: заведомо большой файл не отправляем. */
const MAX_FILE_BYTES = 10 * 1024 * 1024;

type UploadState = { kind: 'idle' } | { kind: 'uploading' } | { kind: 'error'; message: string };

/**
 * ТЗ, страница «Импорт товаров»: кнопка выбора и загрузки .xlsx и вызов API импорта.
 * Импорт асинхронный: после загрузки страница опрашивает статус задачи и показывает отчёт.
 */
@Component({
  selector: 'app-imports-page',
  imports: [AsyncPipe, DatePipe, MatButtonModule, MatCardModule, MatIconModule, MatProgressBarModule, MatTableModule, ImportProgressComponent],
  templateUrl: './imports.page.html',
  styleUrl: './imports.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ImportsPage {
  private readonly imports = inject(ImportService);
  private readonly uploads$ = new Subject<File>();
  private readonly refreshHistory$ = new BehaviorSubject<void>(undefined);

  readonly selectedFile = signal<File | null>(null);
  readonly fileError = signal<string | null>(null);
  /** Задача, прогресс и отчёт которой показаны на странице (только что загруженная или выбранная в истории). */
  readonly activeImportId = signal<string | null>(null);
  readonly historyColumns = ['createdAt', 'filename', 'status', 'createdCount', 'updatedCount', 'failedCount'];

  /** Загрузка файла: при успехе показываем прогресс новой задачи и обновляем историю. */
  readonly upload$: Observable<UploadState> = this.uploads$.pipe(
    switchMap((file) =>
      this.imports.upload(file).pipe(
        tap((job) => {
          this.activeImportId.set(job.id);
          this.selectedFile.set(null);
          this.refreshHistory$.next();
        }),
        map((): UploadState => ({ kind: 'idle' })),
        catchError((error: unknown) => {
          const problem = toProblem(error);
          const message = problem?.errors?.['file'] ?? errorMessage(error);
          return of<UploadState>({ kind: 'error', message });
        }),
        startWith<UploadState>({ kind: 'uploading' }),
      ),
    ),
    startWith<UploadState>({ kind: 'idle' }),
  );

  /** История последних импортов. */
  readonly history$: Observable<ImportJobSummary[]> = this.refreshHistory$.pipe(
    switchMap(() => this.imports.latest().pipe(catchError(() => of([])))),
  );

  /** Выбор файла: сразу отсекаем не-.xlsx и слишком большие файлы, сервер всё равно проверит MIME и сигнатуру. */
  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.item(0) ?? null;
    input.value = '';
    this.fileError.set(null);
    this.selectedFile.set(null);
    if (file === null) {
      return;
    }
    if (!file.name.toLowerCase().endsWith('.xlsx')) {
      this.fileError.set('Нужен файл в формате .xlsx');
      return;
    }
    if (file.size > MAX_FILE_BYTES) {
      this.fileError.set('Файл больше 10 МБ');
      return;
    }
    this.selectedFile.set(file);
  }

  /** Отправка выбранного файла на импорт. */
  submit(): void {
    const file = this.selectedFile();
    if (file !== null) {
      this.uploads$.next(file);
    }
  }

  /** Подпись статуса для строки истории. */
  statusLabel(job: ImportJobSummary): string {
    return IMPORT_STATUS_LABELS[job.status];
  }

  /** Показ прогресса и отчёта импорта из истории. */
  select(job: ImportJobSummary): void {
    this.activeImportId.set(job.id);
  }

  /** Импорт завершился: обновляем счётчики в истории. */
  onFinished(): void {
    this.refreshHistory$.next();
  }
}
