import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';
import { errorMessage } from '../../core/http/problem';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

/** ТЗ: Effect загружает список через ProductService. */
@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly products = inject(ProductService);

  /**
   * switchMap отменяет предыдущий запрос: при быстром листании или смене фильтра
   * на экран не попадёт устаревшая страница, пришедшая позже новой.
   */
  readonly loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      switchMap(({ query }) =>
        this.products.list(query).pipe(
          map((page) => loadProductsSuccess({ page })),
          catchError((error: unknown) => of(loadProductsFailure({ error: errorMessage(error) }))),
        ),
      ),
    ),
  );
}
