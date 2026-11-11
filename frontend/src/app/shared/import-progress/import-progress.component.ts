import { AsyncPipe, DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, input, output } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { MatChipsModule } from '@angular/material/chips';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { Observable, catchError, map, of, startWith, switchMap, tap } from 'rxjs';
import { errorMessage } from '../../core/http/problem';
import { IMPORT_STATUS_LABELS, ImportJob, isFinalStatus } from '../../models';
import { ImportService } from '../../services/import.service';

type ProgressState = { kind: 'loading' } | { kind: 'ready'; job: ImportJob } | { kind: 'error'; message: string };

/**
 * ТЗ: «индикатор прогресса импорта (polling)» и «отображение отчёта об ошибках после импорта».
 * Опрашивает задачу, пока она не завершится, показывает процент, счётчики и строки с ошибками.
 * Используется на странице импорта и в карточке товара (последний импорт товара).
 */
@Component({
  selector: 'app-import-progress',
  imports: [AsyncPipe, DatePipe, MatChipsModule, MatIconModule, MatProgressBarModule, MatTableModule],
  templateUrl: './import-progress.component.html',
  styleUrl: './import-progress.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ImportProgressComponent {
  private readonly imports = inject(ImportService);

  /** UUID задачи импорта. */
  readonly importId = input.required<string>();
  /** Показывать ли таблицу отчёта (в карточке товара её можно скрыть). */
  readonly showReport = input(true);
  /** Задача дошла до финального статуса: страница может обновить историю или список. */
  readonly finished = output<ImportJob>();

  readonly issueColumns = ['row', 'severity', 'field', 'externalCode', 'message'];
  readonly statusLabels = IMPORT_STATUS_LABELS;

  /** Смена importId перезапускает опрос; ошибка опроса показывается вместо индикатора. */
  readonly state$: Observable<ProgressState> = toObservable(this.importId).pipe(
    switchMap((id) =>
      this.imports.watch(id).pipe(
        tap((job) => isFinalStatus(job.status) && this.finished.emit(job)),
        map((job): ProgressState => ({ kind: 'ready', job })),
        catchError((error: unknown) => of<ProgressState>({ kind: 'error', message: errorMessage(error) })),
        startWith<ProgressState>({ kind: 'loading' }),
      ),
    ),
  );
}
