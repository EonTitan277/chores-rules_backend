FROM mysql:8.0

# Bake initialization files into the image instead of bind-mounting them.
# This avoids host filesystem ACLs preventing the mysql user from reading the
# directory on Docker Desktop for Windows and on some Linux/WSL setups.
COPY docker/init/ /docker-entrypoint-initdb.d/

RUN chmod 0644 /docker-entrypoint-initdb.d/*.sql \
    && chmod 0755 /docker-entrypoint-initdb.d/*.sh