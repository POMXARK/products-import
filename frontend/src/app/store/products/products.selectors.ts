import { createSelector } from '@ngrx/store';
import { productsFeature } from './products.reducer';

const { selectProductsState } = productsFeature;

/** ТЗ: товары текущей страницы. */
export const selectAllProducts = createSelector(selectProductsState, (state) => state.items);

/** ТЗ: идёт ли загрузка (индикатор над таблицей). */
export const selectProductsLoading = createSelector(selectProductsState, (state) => state.loading);

/** ТЗ: число страниц для пагинации; 0, пока данных нет. */
export const selectTotalPages = createSelector(selectProductsState, (state) => state.meta?.totalPages ?? 0);

/** Всего найденных товаров с учётом фильтров (для пагинатора). */
export const selectProductsTotal = createSelector(selectProductsState, (state) => state.meta?.total ?? 0);

/** Текст ошибки загрузки списка. */
export const selectProductsError = createSelector(selectProductsState, (state) => state.error);

/** Параметры последнего запроса: страница, размер и фильтры. */
export const selectProductsQuery = createSelector(selectProductsState, (state) => state.query);
