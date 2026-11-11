import { HttpErrorResponse } from '@angular/common/http';
import { Problem } from '../../models';

/** Достаёт problem+json из ответа с ошибкой; для сетевых сбоев возвращает null. */
export function toProblem(error: unknown): Problem | null {
  if (error instanceof HttpErrorResponse && error.error && typeof error.error === 'object' && 'title' in error.error) {
    return error.error as Problem;
  }
  return null;
}

/** Человекочитаемый текст ошибки для показа пользователю. */
export function errorMessage(error: unknown): string {
  const problem = toProblem(error);
  if (problem) {
    return problem.detail || problem.title;
  }
  if (error instanceof HttpErrorResponse && error.status === 0) {
    return 'Сервер недоступен';
  }
  return 'Не удалось выполнить запрос';
}
