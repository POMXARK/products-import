import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../core/api';
import { Page, Product, ProductQuery, ProductSummary } from '../models';

/** ТЗ: отдельный сервис сущности «товар»: компоненты не обращаются к HttpClient напрямую. */
@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_URL);

  /** Страница списка: серверная пагинация ?page=&limit= и фильтры name, price_min, price_max. */
  list(query: ProductQuery): Observable<Page<ProductSummary>> {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);
    if (query.name) {
      params = params.set('name', query.name);
    }
    if (query.priceMin) {
      params = params.set('price_min', query.priceMin);
    }
    if (query.priceMax) {
      params = params.set('price_max', query.priceMax);
    }
    return this.http.get<Page<ProductSummary>>(`${this.api}/products`, { params });
  }

  /** Карточка товара с атрибутами и изображениями. */
  get(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.api}/products/${id}`);
  }
}
