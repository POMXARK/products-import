import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth/auth.guards';

/** ТЗ: роутинг в app.routes.ts, страницы загружаются лениво, рабочая часть закрыта guard. */
export const routes: Routes = [
  {
    path: 'login',
    title: 'Вход',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/shell.component').then((m) => m.ShellComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'products' },
      { path: 'products', loadChildren: () => import('./pages/products/products.routes').then((m) => m.PRODUCTS_ROUTES) },
      { path: 'imports', title: 'Импорт товаров', loadComponent: () => import('./pages/imports/imports.page').then((m) => m.ImportsPage) },
    ],
  },
  { path: '**', redirectTo: '' },
];
