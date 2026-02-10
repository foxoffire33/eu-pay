FROM nginx:1.27-alpine

LABEL description="EU Pay Nginx — HTTPS reverse proxy with mkcert"

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Copy our config
COPY docker/nginx/eupay.conf /etc/nginx/conf.d/eupay.conf

# Certs are mounted at runtime via docker-compose volume
# /etc/nginx/certs/eupay.pem + eupay-key.pem

EXPOSE 80 443
