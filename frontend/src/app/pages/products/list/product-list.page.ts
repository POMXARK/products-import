import { AsyncPipe, DecimalPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, effect, inject, input } from '@angular/core';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { Router, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { DEFAULT_PRODUCT_QUERY, ProductQuery } from '../../../models';
import {
  loadProducts,
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsTotal,
  selectTotalPages,
} from '../../../store/products';

/** Цена: число с точностью до копеек, как принимает API. */
const MONEY_PATTERN = /^\d{1,10}([.,]\d{1,2})?$/;
const PAGE_SIZES = [10, 20, 50, 100];

/** Нижняя граница цены не может быть больше верхней. */
function priceRangeValidator(group: AbstractControl): ValidationErrors | null {
  const min = toNumber(group.get('priceMin')?.value);
  const max = toNumber(group.get('priceMax')?.value);
  return min !== null && max !== null && min > max ? { priceRange: true } : null;
}

/** Строку цены из формы или URL переводит в число; пустое значение даёт null. */
function toNumber(value: unknown): number | null {
  if (typeof value !== 'string' || value.trim() === '') {
    return null;
  }
  const n = Number(value.replace(',', '.'));
  return Number.isFinite(n) ? n : null;
}

/** Положительное целое из query-параметра, иначе значение по умолчанию. */
function positiveInt(value: string | undefined, fallback: number, max = Number.MAX_SAFE_INTEGER): number {
  const n = Number(value);
  return Number.isInteger(n) && n >= 1 && n <= max ? n : fallback;
}

/**
 * ТЗ, страница «Список товаров»: серверная пагинация, поиск по name и диапазону price,
 * каждый товар ведёт на карточку. Данные приходят из NgRx store через store.select() и async pipe.
 * Фильтры и страница хранятся в URL: ссылку можно отправить, а кнопка «назад» возвращает предыдущую выборку.
 */
@Component({
  selector: 'app-product-list-page',
  imports: [
    AsyncPipe,
    DecimalPipe,
    ReactiveFormsModule,
    RouterLink,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatPaginatorModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  templateUrl: './product-list.page.html',
  styleUrl: './product-list.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductListPage {
  private readonly store = inject(Store);
  private readonly router = inject(Router);

  // Query-параметры URL (связываются через withComponentInputBinding).
  readonly page = input<string>();
  readonly limit = input<string>();
  readonly name = input<string>();
  readonly priceMinParam = input<string | undefined>(undefined, { alias: 'price_min' });
  readonly priceMaxParam = input<string | undefined>(undefined, { alias: 'price_max' });

  readonly products$ = this.store.select(selectAllProducts);
  readonly loading$ = this.store.select(selectProductsLoading);
  readonly total$ = this.store.select(selectProductsTotal);
  readonly totalPages$ = this.store.select(selectTotalPages);
  readonly error$ = this.store.select(selectProductsError);

  readonly columns = ['preview', 'externalCode', 'name', 'price', 'discount'];
  readonly pageSizes = PAGE_SIZES;

  readonly filters = inject(NonNullableFormBuilder).group(
    {
      name: ['', Validators.maxLength(255)],
      priceMin: ['', Validators.pattern(MONEY_PATTERN)],
      priceMax: ['', Validators.pattern(MONEY_PATTERN)],
    },
    { validators: priceRangeValidator },
  );

  /** Запрос к API, собранный из URL. */
  readonly query = computed<ProductQuery>(() => ({
    page: positiveInt(this.page(), DEFAULT_PRODUCT_QUERY.page),
    limit: positiveInt(this.limit(), DEFAULT_PRODUCT_QUERY.limit, 100),
    name: this.name()?.trim() || null,
    priceMin: this.priceMinParam() || null,
    priceMax: this.priceMaxParam() || null,
  }));

  constructor() {
    // Любая смена URL (фильтр, страница, «назад») загружает соответствующую страницу.
    effect(() => {
      const query = this.query();
      this.store.dispatch(loadProducts({ query }));
      this.filters.setValue(
        { name: query.name ?? '', priceMin: query.priceMin ?? '', priceMax: query.priceMax ?? '' },
        { emitEvent: false },
      );
    });
  }

  /** Применить фильтры: поиск всегда начинается с первой страницы. */
  applyFilters(): void {
    if (this.filters.invalid) {
      this.filters.markAllAsTouched();
      return;
    }
    const { name, priceMin, priceMax } = this.filters.getRawValue();
    this.navigate({
      page: null,
      name: name.trim() || null,
      price_min: priceMin.replace(',', '.') || null,
      price_max: priceMax.replace(',', '.') || null,
    });
  }

  /** Сбросить все фильтры. */
  resetFilters(): void {
    this.navigate({ page: null, name: null, price_min: null, price_max: null });
  }

  /** Пагинатор Material считает страницы с 0, API с 1. */
  onPage(event: PageEvent): void {
    this.navigate({
      page: event.pageIndex > 0 ? event.pageIndex + 1 : null,
      limit: event.pageSize !== DEFAULT_PRODUCT_QUERY.limit ? event.pageSize : null,
    });
  }

  /** Открыть карточку товара по клику на строку. */
  open(id: number): void {
    void this.router.navigate(['/products', id]);
  }

  /** Меняет query-параметры текущего URL; null убирает параметр. */
  private navigate(params: Record<string, string | number | null>): void {
    void this.router.navigate([], { queryParams: params, queryParamsHandling: 'merge' });
  }
}
