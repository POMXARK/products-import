import { productPage } from '../../testing/fixtures';
import { initialProductsState } from './products.reducer';
import { selectAllProducts, selectProductsLoading, selectTotalPages } from './products.selectors';

describe('products selectors', () => {
  const page = productPage(undefined, 7);
  const root = { products: { ...initialProductsState, items: page.items, meta: page.meta, loading: true } };

  it('selectAllProducts отдаёт товары текущей страницы', () => {
    expect(selectAllProducts(root)).toEqual(page.items);
  });

  it('selectProductsLoading отражает загрузку', () => {
    expect(selectProductsLoading(root)).toBeTrue();
  });

  it('selectTotalPages берёт число страниц из meta и равен 0 без данных', () => {
    expect(selectTotalPages(root)).toBe(7);
    expect(selectTotalPages({ products: initialProductsState })).toBe(0);
  });
});
