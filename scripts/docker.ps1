# PowerShell script for Docker management
param (
    [Parameter(Mandatory=$false, Position=0)]
    [string]$Command = "help"
)

switch ($Command.ToLower()) {
    "up" {
        docker compose up -d
    }
    "down" {
        docker compose down
    }
    "restart" {
        docker compose restart
    }
    "logs" {
        docker compose logs -f
    }
    "ps" {
        docker compose ps
    }
    "build" {
        docker compose build --no-cache
    }
    "migrate" {
        docker compose exec app php artisan migrate
    }
    "seed" {
        docker compose exec app php artisan db:seed
    }
    "test" {
        docker compose exec app php artisan test
    }
    "bash" {
        docker compose exec -it app sh
    }
    "clean" {
        docker compose down -v
    }
    default {
        Write-Host "Usage: .\scripts\docker.ps1 [command]" -ForegroundColor Cyan
        Write-Host "Commands:"
        Write-Host "  up       - Start all containers"
        Write-Host "  down     - Stop containers"
        Write-Host "  restart  - Restart containers"
        Write-Host "  logs     - Follow logs"
        Write-Host "  ps       - List container status"
        Write-Host "  build    - Rebuild images without cache"
        Write-Host "  migrate  - Run database migrations"
        Write-Host "  seed     - Run database seeders"
        Write-Host "  test     - Run PHPUnit tests"
        Write-Host "  bash     - Open shell in app container"
        Write-Host "  clean    - Down and remove all volumes"
    }
}
