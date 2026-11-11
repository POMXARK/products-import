import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { authGuard, guestGuard } from './auth.guards';
import { AuthService } from './auth.service';

describe('AuthService и guards', () => {
  let auth: AuthService;
  let backend: HttpTestingController;

  const inFuture = () => new Date(Date.now() + 3_600_000).toISOString();
  const runGuard = (guard: typeof authGuard, url = '/products?page=2') =>
    TestBed.runInInjectionContext(() => guard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot));

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    auth = TestBed.inject(AuthService);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => localStorage.clear());

  function login(expiresAt = inFuture()): void {
    auth.login({ username: 'admin', password: 'secret' }).subscribe();
    backend.expectOne('/api/auth/login').flush({ token: 'jwt', tokenType: 'Bearer', expiresAt });
  }

  it('после входа хранит токен и переживает перезагрузку вкладки', () => {
    login();

    expect(auth.token()).toBe('jwt');
    expect(localStorage.getItem('products-import.session')).toContain('jwt');
  });

  it('просроченный токен считается отсутствующим', () => {
    login(new Date(Date.now() - 1000).toISOString());

    expect(auth.isAuthenticated()).toBeFalse();
  });

  it('logout стирает сессию', () => {
    login();
    auth.logout();

    expect(auth.token()).toBeNull();
    expect(localStorage.getItem('products-import.session')).toBeNull();
  });

  it('authGuard без сессии уводит на /login и запоминает исходный адрес', () => {
    const result = runGuard(authGuard) as UrlTree;

    expect(TestBed.inject(Router).serializeUrl(result)).toBe('/login?returnUrl=%2Fproducts%3Fpage%3D2');
  });

  it('authGuard пропускает вошедшего, guestGuard отправляет его к товарам', () => {
    login();

    expect(runGuard(authGuard)).toBeTrue();
    expect(TestBed.inject(Router).serializeUrl(runGuard(guestGuard) as UrlTree)).toBe('/products');
  });
});
