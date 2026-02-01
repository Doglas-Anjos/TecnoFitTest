FROM node:20-alpine

LABEL maintainer="TecnoFit" \
      version="1.0" \
      description="TecnoFit API Test Dashboard"

WORKDIR /app

# Install dependencies
COPY package.json ./
RUN npm install

# Copy application files
COPY . .

EXPOSE 3000

CMD ["node", "server.js"]
