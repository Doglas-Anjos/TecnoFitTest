FROM mysql:8.0

LABEL maintainer="TecnoFit" \
      version="1.0" \
      description="MySQL 8.0 Database Server"

# Environment variables are passed via docker-compose from .env file:
# - MYSQL_ROOT_PASSWORD
# - MYSQL_DATABASE
# - MYSQL_USER
# - MYSQL_PASSWORD

# Copy custom configuration if needed
# COPY my.cnf /etc/mysql/conf.d/custom.cnf

# Copy initialization scripts (runs on first start only)
COPY init/ /docker-entrypoint-initdb.d/

# Expose MySQL port
EXPOSE 3306
