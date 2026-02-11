FROM nginx:1.27-alpine

LABEL description="EU Pay Nginx — API reverse proxy"

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Copy our config
COPY docker/nginx/eupay.conf /etc/nginx/conf.d/eupay.conf

# Prepare directories for non-root
RUN chown -R nginx:nginx /var/cache/nginx /var/log/nginx \
    && touch /var/run/nginx.pid \
    && chown nginx:nginx /var/run/nginx.pid

EXPOSE 8080

USER nginx
