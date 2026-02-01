# Search Engine Service

A production-ready search engine service built with **Symfony 6.4**, implementing hexagonal/clean architecture with async provider integration, event-driven scoring via Messenger, and custom Redis FIFO caching.

## 🎯 Features

- **Unified Content Search**: Aggregates content from multiple providers (JSON & XML formats)
- **Async Provider Fetching**: Parallel HTTP requests using Symfony HttpClient
- **Event-Driven Scoring**: Background score calculation via Symfony Messenger
- **Custom Redis Cache**: FIFO eviction strategy (max 10 unique queries)
- **API Rate Limiting**: IP-based throttling (100 requests/hour) with Redis storage
- **Modern Dashboard**: Server-side rendered UI with glassmorphism design
- **REST API**: JSON endpoints for programmatic access
- **Hexagonal Architecture**: Clean separation between domain, application, and infrastructure layers

## 🏗️ Architecture

```
src/
├── Domain/              # Business entities and interfaces (framework-agnostic)
│   ├── Entity/          # Content entity with denormalized score
│   ├── ValueObject/     # ContentType enum
│   ├── DTO/             # Data transfer objects
│   └── Repository/      # Repository interfaces
├── Infrastructure/      # Framework adapters
│   ├── Provider/        # JSON & XML providers with async fetching
│   ├── Repository/      # Doctrine implementations
│   └── Cache/           # Custom Redis FIFO adapter
├── Application/         # Business logic
│   ├── Service/         # Scoring, Search, Ingestion services
│   ├── Message/         # Messenger messages
│   └── MessageHandler/  # Async workers
└── Presentation/        # Controllers and views
    └── Controller/      # API & Dashboard controllers
```

## 🔧 Technology Stack

### Backend
- **PHP 8.2+** with modern features (enums, readonly properties, attributes)
- **Symfony 6.4** - Framework for enterprise applications
- **Doctrine ORM** - Database abstraction with entity attributes
- **Symfony Messenger** - Async message processing

### Infrastructure
- **PostgreSQL 16** - Primary database with JSON support
- **Redis 7** - Cache and message broker
- **Nginx** - Web server
- **Docker & Docker Compose** - Containerization

### Why These Choices?

**Symfony over Laravel**: Better suited for enterprise applications with complex business logic. Messenger component provides production-ready async capabilities.

**PostgreSQL over MySQL**: Superior JSON handling, better indexing strategies, and ACID compliance.

**Hexagonal Architecture**: Ensures testability, maintainability, and allows easy swapping of infrastructure components (e.g., switching from Redis to Memcached).

**Event-Driven Scoring**: Decouples expensive calculations from HTTP requests, improving response times and scalability.

## 📦 Installation

### Prerequisites
- Docker & Docker Compose
- Git

### Setup

1. **Build and start Docker containers**
```bash
docker compose up -d --build
```

2. **Install dependencies**
```bash
docker compose exec php composer install
```

3. **Run database migrations**
```bash
docker compose exec php bin/console doctrine:migrations:migrate -n
```

4. **Start Messenger worker (in a separate terminal)**
```bash
docker compose exec php bin/console messenger:consume async -vv
```

5. **Ingest content from providers**
```bash
docker compose exec php bin/console app:ingest
```

## 🚀 Usage

### Access Points

- **Dashboard**: http://localhost:8080
- **API**: http://localhost:8080/api/search
- **pgAdmin**: http://localhost:5050
  - Email: `admin@admin.com`
  - Password: `admin`

### Dashboard Features

The dashboard includes modern UX features:

- **AJAX Search**: Search without page reload using JavaScript fetch API
- **Real-time Results**: Instant updates as you search
- **Loading Indicator**: Visual feedback during search
- **URL Sync**: Search queries reflected in browser URL for sharing
- **Modern UI**: Glassmorphism design with animations
- **Content Filtering**: Filter by type (video/article)
- **Pagination**: Browse through results

### pgAdmin Database Management

1. Open pgAdmin at http://localhost:5050
2. Login with credentials above
3. Right-click "Servers" → Register → Server
4. Fill in connection details:
   - **Name**: Search Engine
   - **Host**: `postgres` (container name)
   - **Port**: `5432`
   - **Username**: `app`
   - **Password**: `!ChangeMe!`
   - **Database**: `search_engine`
5. Click "Save" to connect

### API Endpoints

#### Search Content
```bash
GET /api/search?q=symfony&type=video&page=1&limit=10
```

**Parameters:**
- `q` (optional): Search keyword
- `type` (optional): Filter by `video` or `article`
- `page` (optional): Page number (default: 1)
- `limit` (optional): Results per page (default: 10, max: 100)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "provider_id": "v1",
      "title": "Symfony Async & Parallel HTTP Requests",
      "type": "video",
      "metrics": {
        "views": 15000,
        "likes": 1200,
        "duration": "15:30"
      },
      "published_at": "2024-03-15 10:00:00",
      "score": 28.5,
      "created_at": "2026-02-01 12:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 10,
    "total": 14,
    "total_pages": 2
  }
}
```

### Rate Limiting

**API endpoints** are protected by rate limiting:

- **Limit**: 100 requests per hour per IP address
- **Policy**: Sliding window algorithm
- **Storage**: Redis
- **HTTP 429 Response** when limit exceeded:

```json
{
  "error": "Rate limit exceeded. Too many requests.",
  "message": "You have exceeded the API rate limit of 100 requests per hour.",
  "retry_after": 1738433400,
  "retry_after_seconds": 3456
}
```

**Response Headers:**
```
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1738433400
Retry-After: 3456
```

## 📊 Scoring Algorithm

```
Final Score = (Base Score × Type Coefficient) + Freshness + Interaction
```

### Base Score
- **Video**: `(views / 1000) + (likes / 100)`
- **Article**: `reading_time + (reactions / 50)`

### Type Coefficient
- **Video**: 1.5
- **Article**: 1.0

### Freshness Score
- Last 7 days: +5
- Last 30 days: +3
- Last 90 days: +1
- Older: +0

### Interaction Score
- **Video**: `(likes / views) × 10`
- **Article**: `(reactions / reading_time) × 5`

## ⚙️ Commands

### Ingest Content
```bash
docker compose exec php bin/console app:ingest [--limit=30]
```
Fetches content from providers and dispatches scoring jobs.

### Start Worker
```bash
docker compose exec php bin/console messenger:consume async -vv
```
Processes async score calculations. Run this in a separate terminal.

### Database Reset
```bash
docker compose exec php bin/console doctrine:database:drop --force
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php bin/console app:ingest
```

### Scheduled Auto-Ingestion

Content ingestion runs **automatically every day at 2:00 AM** using Symfony Scheduler.

#### View Scheduled Tasks
```bash
docker compose exec php bin/console debug:scheduler
```

Output:
```
daily_ingest
------------
Trigger     Provider                                      Next Run
0 2 * * *   App\Application\Message\IngestContentMessage  Mon, 02 Feb 2026 02:00:00 +0000
```

#### Start Scheduler Worker (Production)
In production, run the scheduler worker as a background service:

```bash
docker compose exec php bin/console messenger:consume scheduler_daily_ingest -vv
```

Or add to Docker Compose as a separate service:
```yaml
worker_scheduler:
  build: .
  command: php bin/console messenger:consume scheduler_daily_ingest --time-limit=3600
  depends_on:
    - postgres
    - redis
  restart: always
```

#### Test Scheduled Ingestion
To test the scheduled ingestion immediately without waiting for 2 AM:

```bash
# Dispatch the scheduled message manually
docker compose exec php bin/console app:schedule:test-ingest

# Then consume it with the worker
docker compose exec php bin/console messenger:consume async --limit=1 -vv
```

You should see logs like:
```
[info] Scheduled content ingestion started
[info] Starting content ingestion
[info] All providers fetched
[info] Ingestion completed
[info] Scheduled content ingestion completed
```

#### Change Schedule Time
Edit `src/Application/Schedule/DailyContentIngestSchedule.php`:

```php
// Run at different time (e.g., 6:00 AM)
RecurringMessage::cron('0 6 * * *', new IngestContentMessage())

// Run every hour
RecurringMessage::cron('0 * * * *', new IngestContentMessage())

// Run every 15 minutes
RecurringMessage::cron('*/15 * * * *', new IngestContentMessage())
```

**Cron Format**: `minute hour day month weekday`
- `0 2 * * *` = Every day at 2:00 AM
- `0 */6 * * *` = Every 6 hours
- `0 0 * * 0` = Every Sunday at midnight


## 🔄 How It Works

### 1. Async Provider Fetching
```php
// Providers are called in parallel, not sequentially
$providerAggregator->fetchAll(30);
```

### 2. Event-Driven Scoring Flow
```
HTTP Request → Save Content (score=null) → Dispatch CalculateScoreMessage
                                                    ↓
                                          Worker processes message
                                                    ↓
                                          Calculate & update score
```

### 3. Custom Redis FIFO Cache
```
Search Query → Check Redis → Hit: Return cached
                           → Miss: Query DB → Store in Redis
                                            → If 11th query: Evict oldest
```

## 🧪 Testing

### Manual Testing

1. **Verify Parallel Fetching**: Check logs during ingestion
```bash
docker compose exec php bin/console app:ingest -vv
```

2. **Test Async Scoring**: Monitor worker logs
```bash
docker compose exec php bin/console messenger:consume async -vv
```

3. **Validate Cache Eviction**: Perform 11+ unique searches, check Redis
```bash
docker compose exec redis redis-cli
> ZCARD search:queries
```

4. **Dashboard**: Open browser, test search and filters
```
http://localhost:8080
```

## 📁 Project Structure

```
search-engine/
├── bin/
│   └── console              # Symfony CLI
├── config/
│   ├── packages/            # Bundle configurations
│   ├── routes.yaml          # Route definitions
│   └── services.yaml        # DI container
├── docker/
│   ├── nginx/
│   │   └── nginx.conf       # Nginx config
│   └── php/
│       └── php.ini          # PHP settings
├── migrations/              # Database migrations
├── public/
│   └── index.php            # Entry point
├── src/                     # Application code (see Architecture)
├── templates/
│   └── dashboard/           # Twig templates
├── docker-compose.yml       # Docker orchestration
├── Dockerfile               # PHP-FPM image
└── composer.json            # Dependencies
```

## 🐳 Docker Services

| Service | Port | Description |
|---------|------|-------------|
| nginx | 8080 | Web server |
| php | - | PHP-FPM application |
| postgres | 5432 | PostgreSQL 16 database |
| redis | 6379 | Cache & message broker |
| pgadmin | 5050 | Database management UI |

### Environment Variables
Edit `.env` to customize:
```env
NGINX_PORT=8080
DB_PORT=5432
REDIS_PORT=6379
DB_NAME=search_engine
DB_USER=app
DB_PASSWORD=!ChangeMe!
```

## 🔐 Production Considerations

1. **Security**
   - Change `APP_SECRET` in `.env`
   - Update database credentials
   - Enable HTTPS in Nginx
   - Add rate limiting

2. **Performance**
   - Enable OPcache (already configured)
   - Add database connection pooling
   - Scale Messenger workers horizontally
   - Implement proper Redis persistence

3. **Monitoring**
   - Add Symfony Profiler in dev
   - Integrate with monitoring tools (Prometheus, Grafana)
   - Log aggregation (ELK stack)

4. **Scaling**
   - Containerize workers separately
   - Use managed PostgreSQL (RDS, Cloud SQL)
   - Scale Redis with Redis Cluster
   - Add load balancer for multiple app instances

## 🎨 Design Decisions

### Why Denormalized Score?
Calculating scores on every search is expensive. Storing pre-calculated scores in the database enables:
- Fast sorting without complex calculations
- Better query performance with indexes
- Predictable response times

### Why Messenger over Cron?
- Immediate processing (no waiting for next cron run)
- Automatic retries on failure
- Better observability and monitoring
- Horizontal scalability

### Why Custom FIFO Cache?
Demonstrates understanding of cache eviction strategies. In production, consider:
- Redis native TTL for time-based expiration
- LRU policy for most frequently accessed
- Hybrid approach combining both