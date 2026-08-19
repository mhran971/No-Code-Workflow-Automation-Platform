# Base Node.js image
FROM node:20-alpine AS base
WORKDIR /app

# Install dependencies
COPY package.json package-lock.json ./
RUN npm ci

# Copy application source
COPY . .

# Expose default Vite dev port
EXPOSE 8080 5173

# Default dev command
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
