import { InjectionToken } from '@angular/core';

/** Базовый адрес API: nginx проксирует /api на php-fpm, в dev то же делает proxy.conf.json. */
export const API_URL = new InjectionToken<string>('API_URL', { factory: () => '/api' });
