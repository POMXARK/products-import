import { Routes } from '@angular/router';
import { provideEffects } from '@ngrx/effects';
import { provideState } from '@ngrx/store';
import { ProductsEffects, productsFeature } from '../../store/products';

/**
 * Lazy-раздел каталога: срез store и effects регистрируются только при первом заходе
 * на товары, а не в основном бандле.
 */
export const PRODUCTS_ROUTES: Routes = [
  {
    path: '',
    providers: [provideState(productsFeature), provideEffects(ProductsEffects)],
    children: [
      { path: '', title: 'Товары', loadComponent: () => import('./list/product-list.page').then((m) => m.ProductListPage) },
      { path: ':id', title: 'Карточка товара', loadComponent: () => import('./detail/product-detail.page').then((m) => m.ProductDetailPage) },
    ],
  },
];
