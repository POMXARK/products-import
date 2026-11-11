import { ImportJob, Page, ProductSummary } from '../models';

/** Товар списка для тестов. */
export function productSummary(overrides: Partial<ProductSummary> = {}): ProductSummary {
  return {
    id: 1,
    externalCode: 'SNL-1',
    name: 'Бермуды мужские',
    description: null,
    price: '1320.00',
    discount: '27.27',
    previewUrl: null,
    lastImportId: null,
    createdAt: '2026-11-11T10:00:00+00:00',
    updatedAt: '2026-11-11T10:00:00+00:00',
    ...overrides,
  };
}

/** Страница списка для тестов. */
export function productPage(items: ProductSummary[] = [productSummary()], totalPages = 1): Page<ProductSummary> {
  return { items, meta: { page: 1, limit: 20, total: items.length, totalPages } };
}

/** Задача импорта для тестов. */
export function importJob(overrides: Partial<ImportJob> = {}): ImportJob {
  return {
    id: '01a1cb21-c281-7612-9e90-91bc04fb8b9b',
    status: 'processing',
    filename: 'import.xlsx',
    progress: 50,
    totalRows: 40,
    processedRows: 20,
    createdCount: 20,
    updatedCount: 0,
    failedCount: 0,
    failureReason: null,
    createdAt: '2026-11-11T10:00:00+00:00',
    startedAt: '2026-11-11T10:00:01+00:00',
    finishedAt: null,
    issues: [],
    issuesTruncated: false,
    ...overrides,
  };
}
