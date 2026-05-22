# Stage 1: Build
FROM php:8.4-cli AS builder
...
# Stage 2: Production
FROM php:8.4-fpm-alpine
RUN useradd -m -s /bin/bash appuser
USER appuser
ENV APP_ENV=production APP_DEBUG=false
