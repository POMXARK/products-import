import { AsyncPipe, DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { errorMessage } from '../../../core/http/problem';
import { Product } from '../../../models';
import { ProductService } from '../../../services/product.service';
import { ImportProgressComponent } from '../../../shared/import-progress/import-progress.component';

type DetailState =
  | { kind: 'loading' }
  | { kind: 'ready'; product: Product }
  | { kind: 'not-found' }
  | { kind: 'error'; message: string };

/**
 * ТЗ, страница «Карточка товара»: все основные поля, дополнительные атрибуты, изображения,
 * индикатор прогресса и отчёт об ошибках импорта, который последним записал этот товар.
 */
@Component({
  selector: 'app-product-detail-page',
  imports: [AsyncPipe, DatePipe, DecimalPipe, RouterLink, MatButtonModule, MatCardModule, MatIconModule, MatProgressBarModule, ImportProgressComponent],
  templateUrl: './product-detail.page.html',
  styleUrl: './product-detail.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductDetailPage {
  private readonly products = inject(ProductService);

  /** id товара из маршрута /products/:id. */
  readonly id = input.required<string>();

  /** Загрузка карточки при каждой смене id; 404 показывается отдельным сообщением. */
  readonly state$: Observable<DetailState> = toObservable(this.id).pipe(
    switchMap((id) =>
      this.products.get(Number(id)).pipe(
        map((product): DetailState => ({ kind: 'ready', product })),
        catchError((error: unknown) =>
          of<DetailState>(
            error instanceof HttpErrorResponse && error.status === 404
              ? { kind: 'not-found' }
              : { kind: 'error', message: errorMessage(error) },
          ),
        ),
        startWith<DetailState>({ kind: 'loading' }),
      ),
    ),
  );
}
