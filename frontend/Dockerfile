# syntax=docker/dockerfile:1.7
# Контекст сборки: корень репозитория (нужен docker/nginx/default.conf).

# --- deps: зависимости кешируются по package-lock.json ---
FROM node:22-alpine AS deps
WORKDIR /src
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund

# --- build: production-сборка Angular ---
FROM deps AS build
COPY frontend/ ./
RUN npx ng build --configuration production

# --- runtime: только nginx и статика ---
FROM nginx:1.27-alpine AS runtime
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /src/dist/frontend/browser /usr/share/nginx/html
EXPOSE 80
