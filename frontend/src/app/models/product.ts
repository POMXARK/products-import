/** Характеристика товара из колонки «Доп. поле: …» файла импорта (таблица product_attributes). */
export interface ProductAttribute {
  id: number;
  key: string;
  value: string;
}

/**
 * Изображение товара (таблица product_images): исходная ссылка из файла и скачанная копия.
 * publicUrl равен null, если картинку скачать не удалось: тогда ошибка есть в отчёте импорта.
 */
export interface ProductImage {
  id: number;
  url: string;
  path: string | null;
  publicUrl: string | null;
}

/** Элемент списка товаров: основные поля без атрибутов и галереи, чтобы список оставался лёгким. */
export interface ProductSummary {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  /** Цена продажи строкой DECIMAL, например "1320.00": без потери копеек во float. */
  price: string;
  /** Скидка в % = (цена продажи − закупочная) / цена продажи × 100; null без закупочной цены. */
  discount: string | null;
  previewUrl: string | null;
  /** Импорт, который последним записал товар: карточка показывает его прогресс и отчёт. */
  lastImportId: string | null;
  createdAt: string;
  updatedAt: string;
}

/** Карточка товара: все поля, дополнительные атрибуты и изображения. */
export interface Product extends ProductSummary {
  attributes: ProductAttribute[];
  images: ProductImage[];
}

/** Параметры списка товаров: серверная пагинация и фильтры по названию и диапазону цены. */
export interface ProductQuery {
  page: number;
  limit: number;
  name: string | null;
  priceMin: string | null;
  priceMax: string | null;
}

export const DEFAULT_PRODUCT_QUERY: ProductQuery = {
  page: 1,
  limit: 20,
  name: null,
  priceMin: null,
  priceMax: null,
};
