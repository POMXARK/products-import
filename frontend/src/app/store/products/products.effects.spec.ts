import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideMockActions } from '@ngrx/effects/testing';
import { Action } from '@ngrx/store';
import { Observable, Subject, of, throwError } from 'rxjs';
import { DEFAULT_PRODUCT_QUERY } from '../../models';
import { ProductService } from '../../services/product.service';
import { productPage } from '../../testing/fixtures';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { ProductsEffects } from './products.effects';

describe('ProductsEffects', () => {
  let actions$: Subject<Action>;
  let service: jasmine.SpyObj<ProductService>;
  let effects: ProductsEffects;

  beforeEach(() => {
    actions$ = new Subject<Action>();
    service = jasmine.createSpyObj<ProductService>('ProductService', ['list']);
    TestBed.configureTestingModule({
      providers: [
        ProductsEffects,
        provideMockActions(() => actions$ as Observable<Action>),
        { provide: ProductService, useValue: service },
      ],
    });
    effects = TestBed.inject(ProductsEffects);
  });

  it('загружает страницу через ProductService', () => {
    const page = productPage();
    service.list.and.returnValue(of(page));
    const result: Action[] = [];
    effects.loadProducts$.subscribe((a) => result.push(a));

    actions$.next(loadProducts({ query: DEFAULT_PRODUCT_QUERY }));

    expect(service.list).toHaveBeenCalledWith(DEFAULT_PRODUCT_QUERY);
    expect(result).toEqual([loadProductsSuccess({ page })]);
  });

  it('превращает ошибку API в loadProductsFailure с текстом из problem+json', () => {
    const error = new HttpErrorResponse({ status: 422, error: { title: 'Validation failed', status: 422, detail: 'Неверный фильтр' } });
    service.list.and.returnValue(throwError(() => error));
    const result: Action[] = [];
    effects.loadProducts$.subscribe((a) => result.push(a));

    actions$.next(loadProducts({ query: DEFAULT_PRODUCT_QUERY }));

    expect(result).toEqual([loadProductsFailure({ error: 'Неверный фильтр' })]);
  });

  it('отменяет устаревший запрос, если пришёл новый (switchMap)', () => {
    const slow = new Subject<ReturnType<typeof productPage>>();
    const fresh = productPage(undefined, 5);
    service.list.and.returnValues(slow, of(fresh));
    const result: Action[] = [];
    effects.loadProducts$.subscribe((a) => result.push(a));

    actions$.next(loadProducts({ query: DEFAULT_PRODUCT_QUERY }));
    actions$.next(loadProducts({ query: { ...DEFAULT_PRODUCT_QUERY, page: 2 } }));
    slow.next(productPage());

    expect(result).toEqual([loadProductsSuccess({ page: fresh })]);
  });
});
