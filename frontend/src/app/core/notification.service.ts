import { Injectable, inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';

/** Короткие уведомления внизу экрана: глобальные ошибки (500, нет сети) и результат действий. */
@Injectable({ providedIn: 'root' })
export class NotificationService {
  private readonly snackBar = inject(MatSnackBar);

  /** Сообщение об ошибке, видимое несколько секунд. */
  error(message: string): void {
    this.snackBar.open(message, 'Закрыть', { duration: 6000, panelClass: 'snack-error' });
  }

  /** Информационное сообщение. */
  info(message: string): void {
    this.snackBar.open(message, undefined, { duration: 3000 });
  }
}
