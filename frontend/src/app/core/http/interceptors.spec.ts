import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { AuthService } from '../auth/auth.service';
import { NotificationService } from '../notification.service';
import { authInterceptor } from './auth.interceptor';
import { errorInterceptor } from './error.interceptor';

describe('HTTP interceptors', () => {
  let http: HttpClient;
  let backend: HttpTestingController;
  let auth: jasmine.SpyObj<AuthService>;
  let notifications: jasmine.SpyObj<NotificationService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', ['token', 'logout']);
    notifications = jasmine.createSpyObj<NotificationService>('NotificationService', ['error']);
    router = jasmine.createSpyObj<Router>('Router', ['navigate'], { url: '/imports' });
    router.navigate.and.resolveTo(true);
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor, errorInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
        { provide: NotificationService, useValue: notifications },
        { provide: Router, useValue: router },
      ],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => backend.verify());

  it('добавляет Bearer-токен к запросам нашего API', () => {
    auth.token.and.returnValue('jwt-1');
    http.get('/api/products').subscribe();

    expect(backend.expectOne('/api/products').request.headers.get('Authorization')).toBe('Bearer jwt-1');
  });

  it('не отдаёт токен сторонним адресам и не шлёт его без сессии', () => {
    auth.token.and.returnValue('jwt-1');
    http.get('https://cdn.example.com/a.jpg').subscribe();
    expect(backend.expectOne('https://cdn.example.com/a.jpg').request.headers.has('Authorization')).toBeFalse();

    auth.token.and.returnValue(null);
    http.get('/api/products').subscribe();
    expect(backend.expectOne('/api/products').request.headers.has('Authorization')).toBeFalse();
  });

  it('на 401 завершает сессию и уводит на вход с returnUrl', () => {
    auth.token.and.returnValue('expired');
    let failed = false;
    http.get('/api/products').subscribe({ error: () => (failed = true) });

    backend.expectOne('/api/products').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(failed).toBeTrue();
    expect(auth.logout).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/login'], { queryParams: { returnUrl: '/imports' } });
  });

  it('401 на самом логине оставляет обработку форме входа', () => {
    auth.token.and.returnValue(null);
    http.post('/api/auth/login', {}).subscribe({ error: () => undefined });

    backend.expectOne('/api/auth/login').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(auth.logout).not.toHaveBeenCalled();
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('показывает уведомления для 429 и 5xx и пробрасывает ошибку', () => {
    auth.token.and.returnValue('jwt');
    const errors: number[] = [];
    http.post('/api/imports', null).subscribe({ error: (e) => errors.push(e.status) });
    http.get('/api/products').subscribe({ error: (e) => errors.push(e.status) });

    backend.expectOne('/api/imports').flush(null, { status: 429, statusText: 'Too Many Requests' });
    backend.expectOne('/api/products').flush(null, { status: 503, statusText: 'Unavailable' });

    expect(errors).toEqual([429, 503]);
    expect(notifications.error).toHaveBeenCalledTimes(2);
  });
});
