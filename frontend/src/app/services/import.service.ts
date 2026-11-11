import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, exhaustMap, map, takeWhile, timer } from 'rxjs';
import { API_URL } from '../core/api';
import { ImportJob, ImportJobSummary, isFinalStatus } from '../models';

/** Интервал опроса статуса импорта: прогресс обновляется часто, но без лишней нагрузки. */
export const IMPORT_POLL_INTERVAL_MS = 1500;

/**
 * ТЗ: отдельный сервис сущности «импорт».
 * Импорт асинхронный: загрузка сразу возвращает задачу, а прогресс и отчёт получаются опросом.
 */
@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_URL);

  /** Отправляет .xlsx на сервер: ответ 202 с задачей в статусе queued, обработку выполнит воркер. */
  upload(file: File): Observable<ImportJob> {
    const form = new FormData();
    form.append('file', file, file.name);
    return this.http.post<ImportJob>(`${this.api}/imports`, form);
  }

  /** Текущее состояние задачи: статус, процент, счётчики и отчёт об ошибках. */
  get(id: string): Observable<ImportJob> {
    return this.http.get<ImportJob>(`${this.api}/imports/${id}`);
  }

  /** История последних загрузок для страницы импорта. */
  latest(): Observable<ImportJobSummary[]> {
    return this.http.get<{ items: ImportJobSummary[] }>(`${this.api}/imports`).pipe(map((r) => r.items));
  }

  /**
   * ТЗ: «индикатор прогресса импорта: polling статуса задачи».
   * Опрашивает задачу до финального статуса и отдаёт последнее состояние, после чего завершается.
   * exhaustMap не шлёт новый запрос, пока не пришёл ответ на предыдущий.
   */
  watch(id: string, intervalMs = IMPORT_POLL_INTERVAL_MS): Observable<ImportJob> {
    return timer(0, intervalMs).pipe(
      exhaustMap(() => this.get(id)),
      takeWhile((job) => !isFinalStatus(job.status), true),
    );
  }
}
