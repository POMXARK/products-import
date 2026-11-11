import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { DEFAULT_PRODUCT_QUERY } from '../models';
import { productPage } from '../testing/fixtures';
import { ProductService } from './product.service';

describe('ProductService', () => {
  let service: ProductService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ProductService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('передаёт пагинацию и фильтры в query-параметрах API', () => {
    service.list({ page: 2, limit: 50, name: 'шорты', priceMin: '100', priceMax: '999.5' }).subscribe();

    const req = http.expectOne((r) => r.url === '/api/products');
    expect(req.request.params.toString()).toBe('page=2&limit=50&name=%D1%88%D0%BE%D1%80%D1%82%D1%8B&price_min=100&price_max=999.5');
    req.flush(productPage());
  });

  it('не отправляет пустые фильтры', () => {
    service.list(DEFAULT_PRODUCT_QUERY).subscribe();

    const req = http.expectOne((r) => r.url === '/api/products');
    expect(req.request.params.keys()).toEqual(['page', 'limit']);
    req.flush(productPage());
  });

  it('загружает карточку по id', () => {
    let name = '';
    service.get(42).subscribe((p) => (name = p.name));

    http.expectOne('/api/products/42').flush({ id: 42, name: 'Шорты', attributes: [], images: [] });
    expect(name).toBe('Шорты');
  });
});
