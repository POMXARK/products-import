import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { AuthToken, Credentials } from '../../models';
import { API_URL } from '../api';

const STORAGE_KEY = 'products-import.session';

/**
 * ТЗ: «Аутентификация: login-страница, JWT».
 * Хранит выданный токен и знает, действует ли он: guard по нему пускает на страницы,
 * interceptor подставляет его в заголовок Authorization.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_URL);
  private readonly session = signal<AuthToken | null>(this.restore());

  /** Вход: при успехе токен сохраняется до истечения срока, при неверном пароле API отвечает 401. */
  login(credentials: Credentials): Observable<AuthToken> {
    return this.http.post<AuthToken>(`${this.api}/auth/login`, credentials).pipe(
      tap((token) => {
        this.session.set(token);
        this.persist(token);
      }),
    );
  }

  /** Выход или истечение сессии: токен удаляется, следующий переход уведёт на login-страницу. */
  logout(): void {
    this.session.set(null);
    this.persist(null);
  }

  /** Есть ли действующий (не истёкший) токен. */
  isAuthenticated(): boolean {
    return this.token() !== null;
  }

  /** Токен для заголовка Authorization; null, если его нет или срок истёк. */
  token(): string | null {
    const session = this.session();
    if (session === null || Date.parse(session.expiresAt) <= Date.now()) {
      return null;
    }
    return session.token;
  }

  /** Восстанавливает сессию после перезагрузки страницы; недоступное хранилище означает «не вошёл». */
  private restore(): AuthToken | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? (JSON.parse(raw) as AuthToken) : null;
    } catch {
      return null;
    }
  }

  /** Сохраняет или удаляет сессию в localStorage. */
  private persist(token: AuthToken | null): void {
    try {
      if (token) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(token));
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // Приватный режим браузера: сессия живёт только в памяти вкладки.
    }
  }
}
