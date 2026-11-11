import { Page, expect, test } from '@playwright/test';
import path from 'node:path';

/** Пример из ТЗ: 40 товаров с доп. полями и ссылками на изображения. */
const EXAMPLE_FILE = path.resolve(__dirname, '../../backend/tests/Fixtures/import_example.xlsx');
const USERNAME = process.env['E2E_USERNAME'] ?? 'admin';
const PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin12345';

/** Вход через форму логина. */
async function login(page: Page, returnTo = '/products'): Promise<void> {
  await page.goto(returnTo);
  await expect(page).toHaveURL(/\/login/);
  await page.locator('input[name="username"]').fill(USERNAME);
  await page.locator('input[name="password"]').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(new RegExp(returnTo.replace('/', '\\/')));
}

test.describe('Импорт товаров из xlsx', () => {
  test('защищённые страницы без входа ведут на логин', async ({ page }) => {
    await page.goto('/imports');
    await expect(page).toHaveURL(/\/login\?returnUrl=%2Fimports/);
  });

  test('неверный пароль показывает ошибку и не пускает дальше', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[name="username"]').fill(USERNAME);
    await page.locator('input[name="password"]').fill('wrong-password');
    await page.getByRole('button', { name: 'Войти' }).click();
    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test('загрузка файла, прогресс, отчёт, список, фильтр и карточка товара', async ({ page }) => {
    await login(page, '/imports');

    // Загрузка: файл уходит в очередь, страница опрашивает статус до завершения.
    await page.getByTestId('file-input').setInputFiles(EXAMPLE_FILE);
    await page.getByTestId('upload-button').click();
    await expect(page.getByTestId('import-status')).toHaveText(/Завершён/, { timeout: 90_000 });
    await expect(page.getByTestId('import-percent')).toContainText('100');
    const processed =
      Number(await page.getByTestId('import-created').innerText()) + Number(await page.getByTestId('import-updated').innerText());
    expect(processed).toBeGreaterThan(0);

    // Список: серверная пагинация, кликабельные строки.
    await page.getByRole('link', { name: 'Товары' }).click();
    const table = page.getByTestId('products-table');
    const firstLink = table.locator('a').first();
    await expect(firstLink).toBeVisible();
    const fullName = (await firstLink.innerText()).trim();
    const needle = fullName.slice(0, 6);

    // Фильтр по названию попадает в URL и сужает список.
    await page.locator('input[name="name"]').fill(needle);
    await page.getByRole('button', { name: 'Найти' }).click();
    await expect(page).toHaveURL(/name=/);
    for (const name of await table.locator('a').allInnerTexts()) {
      expect(name.toLowerCase()).toContain(needle.toLowerCase());
    }

    // Карточка: поля, доп. атрибуты, изображения, прогресс последнего импорта.
    await table.locator('a').first().click();
    await expect(page).toHaveURL(/\/products\/\d+$/);
    await expect(page.getByTestId('product-name')).toBeVisible();
    await expect(page.getByTestId('product-attributes')).toBeVisible();
    await expect(page.getByTestId('product-images')).toBeVisible();
    // Скачанное изображение реально отдаётся nginx из /storage/images (а не битая ссылка).
    const image = page.getByTestId('product-images').locator('img').first();
    await expect.poll(() => image.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
    await expect(page.getByTestId('import-status')).toHaveText(/Завершён/);
  });

  test('глубокая ссылка на карточку открывается после перезагрузки (SPA fallback)', async ({ page }) => {
    await login(page);
    await page.getByTestId('products-table').locator('a').first().click();
    await expect(page).toHaveURL(/\/products\/\d+$/);
    const url = page.url();
    await page.reload();
    await expect(page).toHaveURL(url);
    await expect(page.getByTestId('product-name')).toBeVisible();
  });
});
