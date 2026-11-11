# Фронтенд Products Import

Angular 19 (standalone, strict) + NgRx + Angular Material. Общее описание проекта и архитектуры в [корневом README](../README.md).

```bash
npm ci
npm start              # dev-сервер на http://localhost:4200, /api и /storage проксируются на http://localhost:8080
npm run test:ci        # unit-тесты (Karma, ChromeHeadless)
npx playwright test    # e2e по поднятому стеку (make up в корне)
```
