import { createFeature, createReducer, on } from '@ngrx/store';
import { DEFAULT_PRODUCT_QUERY, PageMeta, ProductQuery, ProductSummary } from '../../models';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

/** Состояние списка товаров: текущая страница, параметры запроса, загрузка и ошибка. */
export interface ProductsState {
  items: ProductSummary[];
  meta: PageMeta | null;
  query: ProductQuery;
  loading: boolean;
  error: string | null;
}

export const initialProductsState: ProductsState = {
  items: [],
  meta: null,
  query: DEFAULT_PRODUCT_QUERY,
  loading: false,
  error: null,
};

/**
 * Срез store «products». Пока идёт загрузка новой страницы, старые строки остаются на экране:
 * таблица не «моргает» при листании.
 */
export const productsFeature = createFeature({
  name: 'products',
  reducer: createReducer(
    initialProductsState,
    on(loadProducts, (state, { query }): ProductsState => ({ ...state, query, loading: true, error: null })),
    on(loadProductsSuccess, (state, { page }): ProductsState => ({
      ...state,
      items: page.items,
      meta: page.meta,
      loading: false,
    })),
    on(loadProductsFailure, (state, { error }): ProductsState => ({ ...state, items: [], meta: null, loading: false, error })),
  ),
});
