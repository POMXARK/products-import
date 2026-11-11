/** Метаданные серверной пагинации: номер и размер страницы, всего записей и страниц. */
export interface PageMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

/** Страница результатов API: элементы и метаданные пагинации. */
export interface Page<T> {
  items: T[];
  meta: PageMeta;
}
