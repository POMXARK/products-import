import { DEFAULT_PRODUCT_QUERY } from '../../models';
import { productPage } from '../../testing/fixtures';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { initialProductsState, productsFeature } from './products.reducer';

describe('products reducer', () => {
  const { reducer } = productsFeature;
  const query = { ...DEFAULT_PRODUCT_QUERY, page: 2, name: 'шорты' };

  it('запоминает запрос и включает загрузку, не стирая текущие строки', () => {
    const loaded = reducer(initialProductsState, loadProductsSuccess({ page: productPage() }));
    const state = reducer(loaded, loadProducts({ query }));

    expect(state.loading).toBeTrue();
    expect(state.query).toEqual(query);
    expect(state.items.length).toBe(1);
    expect(state.error).toBeNull();
  });

  it('кладёт страницу и метаданные пагинации', () => {
    const page = productPage(undefined, 3);
    const state = reducer({ ...initialProductsState, loading: true }, loadProductsSuccess({ page }));

    expect(state.items).toEqual(page.items);
    expect(state.meta).toEqual(page.meta);
    expect(state.loading).toBeFalse();
  });

  it('при ошибке очищает список и сохраняет текст ошибки', () => {
    const loaded = reducer(initialProductsState, loadProductsSuccess({ page: productPage() }));
    const state = reducer(loaded, loadProductsFailure({ error: 'Сервер недоступен' }));

    expect(state.items).toEqual([]);
    expect(state.meta).toBeNull();
    expect(state.error).toBe('Сервер недоступен');
    expect(state.loading).toBeFalse();
  });
});
