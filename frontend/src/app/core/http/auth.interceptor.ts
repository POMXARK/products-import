import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from '../auth/auth.service';
import { API_URL } from '../api';

/**
 * ТЗ: «HTTP-interceptor добавляет заголовки авторизации».
 * Токен уходит только на собственный API, чтобы не утечь на сторонние адреса.
 */
export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const token = inject(AuthService).token();
  if (token === null || !request.url.startsWith(inject(API_URL))) {
    return next(request);
  }
  return next(request.clone({ setHeaders: { Authorization: `Bearer ${token}` } }));
};
