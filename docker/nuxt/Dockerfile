FROM node:22-alpine
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci
COPY frontend/ ./
CMD ["sh", "-c", "npm run generate && rm -rf /dist/* && cp -r .output/public/* /dist/"]
