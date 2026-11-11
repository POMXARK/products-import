/** Ошибка API в формате RFC 7807 (application/problem+json); errors: поле → сообщение для 422. */
export interface Problem {
  type?: string;
  title: string;
  status: number;
  detail?: string;
  errors?: Record<string, string>;
}
