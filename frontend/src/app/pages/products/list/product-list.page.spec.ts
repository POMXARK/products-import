import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { Router, provideRouter } from '@angular/router';
import { MockStore, provideMockStore } from '@ngrx/store/testing';
import { DEFAULT_PRODUCT_QUERY } from '../../../models';
import { initialProductsState, loadProducts } from '../../../store/products';
import { productPage, productSummary } from '../../../testing/fixtures';
import { ProductListPage } from './product-list.page';

describe('ProductListPage', () => {
  let fixture: ComponentFixture<ProductListPage>;
  let store: MockStore;
  let router: Router;

  beforeEach(async () => {
    const page = productPage([productSummary({ id: 7, name: 'Шорты' })], 1);
    await TestBed.configureTestingModule({
      imports: [ProductListPage],
      providers: [
        provideRouter([]),
        provideNoopAnimations(),
        provideMockStore({ initialState: { products: { ...initialProductsState, items: page.items, meta: page.meta } } }),
      ],
    }).compileComponents();
    store = TestBed.inject(MockStore);
    router = TestBed.inject(Router);
    spyOn(store, 'dispatch');
    spyOn(router, 'navigate').and.resolveTo(true);
    fixture = TestBed.createComponent(ProductListPage);
  });

  it('загружает страницу из параметров URL через store', () => {
    fixture.componentRef.setInput('page', '3');
    fixture.componentRef.setInput('name', ' шорты ');
    fixture.componentRef.setInput('price_min', '100');
    fixture.detectChanges();

    expect(store.dispatch).toHaveBeenCalledWith(
      loadProducts({ query: { ...DEFAULT_PRODUCT_QUERY, page: 3, name: 'шорты', priceMin: '100' } }),
    );
  });

  it('некорректные page/limit из URL заменяет значениями по умолчанию', () => {
    fixture.componentRef.setInput('page', '-1');
    fixture.componentRef.setInput('limit', '100000');
    fixture.detectChanges();

    expect(fixture.componentInstance.query()).toEqual(DEFAULT_PRODUCT_QUERY);
  });

  it('рисует строки из selectAllProducts со ссылкой на карточку', () => {
    fixture.detectChanges();

    const link = fixture.nativeElement.querySelector('[data-testid="products-table"] a') as HTMLAnchorElement;
    expect(link.textContent?.trim()).toBe('Шорты');
    expect(link.getAttribute('href')).toBe('/products/7');
  });

  it('не применяет фильтр, если минимальная цена больше максимальной', () => {
    fixture.detectChanges();
    fixture.componentInstance.filters.setValue({ name: '', priceMin: '500', priceMax: '100' });
    fixture.componentInstance.applyFilters();

    expect(fixture.componentInstance.filters.hasError('priceRange')).toBeTrue();
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('применяет фильтр через URL и сбрасывает страницу', () => {
    fixture.detectChanges();
    fixture.componentInstance.filters.setValue({ name: ' бермуды ', priceMin: '99,5', priceMax: '' });
    fixture.componentInstance.applyFilters();

    expect(router.navigate).toHaveBeenCalledWith([], {
      queryParams: { page: null, name: 'бермуды', price_min: '99.5', price_max: null },
      queryParamsHandling: 'merge',
    });
  });
});
