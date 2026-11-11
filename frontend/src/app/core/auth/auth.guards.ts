import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/**
 * ТЗ: «защита маршрутов через CanActivate guard».
 * Без действующего токена уводит на login-страницу и запоминает, куда пользователь шёл.
 */
export const authGuard: CanActivateFn = (_route, state) => {
  if (inject(AuthService).isAuthenticated()) {
    return true;
  }
  return inject(Router).createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

/** Вошедшему пользователю login-страница не нужна: сразу открываем список товаров. */
export const guestGuard: CanActivateFn = () =>
  inject(AuthService).isAuthenticated() ? inject(Router).createUrlTree(['/products']) : true;
