# ── Stage 1: Build ──────────────────────────────────
FROM node:22-alpine AS build

WORKDIR /app

COPY landing-page/package.json landing-page/package-lock.json* ./
RUN npm ci

COPY landing-page/ .
RUN npm run build

# ── Stage 2: Serve ──────────────────────────────────
FROM nginx:1.27-alpine

LABEL maintainer="EU Pay <info@eu-pay.nl>"
LABEL description="EU Pay Landing Page"

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/landing-page/landing-page.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

RUN chown -R nginx:nginx /usr/share/nginx/html \
    && chown -R nginx:nginx /var/cache/nginx \
    && chown -R nginx:nginx /var/log/nginx \
    && touch /var/run/nginx.pid \
    && chown nginx:nginx /var/run/nginx.pid

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s \
  CMD wget -qO- http://localhost/health || exit 1
