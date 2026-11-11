import { createAction, props } from '@ngrx/store';
import { Page, ProductQuery, ProductSummary } from '../../models';

/** ТЗ: запрос страницы списка товаров с текущими фильтрами и пагинацией. */
export const loadProducts = createAction('[Products Page] Load Products', props<{ query: ProductQuery }>());

/** Страница товаров получена от API. */
export const loadProductsSuccess = createAction(
  '[Products API] Load Products Success',
  props<{ page: Page<ProductSummary> }>(),
);

/** Запрос списка завершился ошибкой (сообщение для пользователя). */
export const loadProductsFailure = createAction('[Products API] Load Products Failure', props<{ error: string }>());
