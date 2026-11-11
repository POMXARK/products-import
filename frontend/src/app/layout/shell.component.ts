import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../core/auth/auth.service';

/** Каркас защищённой части: шапка с навигацией по трём страницам ТЗ и выходом. */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatToolbarModule, MatButtonModule, MatIconModule],
  template: `
    <mat-toolbar>
      <a class="brand" routerLink="/products">Каталог</a>
      <nav>
        <a mat-button routerLink="/products" routerLinkActive="active">Товары</a>
        <a mat-button routerLink="/imports" routerLinkActive="active">Импорт</a>
      </nav>
      <span class="spacer"></span>
      <button mat-icon-button type="button" (click)="logout()" aria-label="Выйти" title="Выйти">
        <mat-icon>logout</mat-icon>
      </button>
    </mat-toolbar>
    <main class="content"><router-outlet /></main>
  `,
  styles: `
    mat-toolbar { position: sticky; top: 0; z-index: 2; gap: 8px; border-bottom: 1px solid var(--mat-sys-outline-variant); }
    .brand { font: var(--mat-sys-title-large); color: inherit; text-decoration: none; margin-right: 8px; }
    nav { display: flex; gap: 4px; }
    .active { background: var(--mat-sys-secondary-container); }
    .spacer { flex: 1; }
    .content { max-width: 1200px; margin: 0 auto; padding: 24px 16px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ShellComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Выход: удаляет токен и открывает login-страницу. */
  logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }
}
