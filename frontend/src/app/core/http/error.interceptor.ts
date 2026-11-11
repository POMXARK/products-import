import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { NotificationService } from '../notification.service';

/**
 * ТЗ: «HTTP-interceptor обрабатывает глобальные ошибки (401/500)».
 * 401 завершает сессию и ведёт на вход, 5xx, сеть и 429 показываются уведомлением.
 * Ошибка пробрасывается дальше: страница сама показывает ошибки полей (422) и 404.
 */
export const errorInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const notifications = inject(NotificationService);

  return next(request).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        const isLogin = request.url.endsWith('/auth/login');
        if (error.status === 401 && !isLogin) {
          auth.logout();
          notifications.error('Сессия истекла, войдите снова');
          void router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
        } else if (error.status === 429) {
          notifications.error('Слишком много загрузок подряд, повторите через минуту');
        } else if (error.status === 0) {
          notifications.error('Сервер недоступен, проверьте соединение');
        } else if (error.status >= 500) {
          notifications.error('Ошибка сервера, попробуйте позже');
        }
      }
      return throwError(() => error);
    }),
  );
};
