# Photo2Video - AI Photo to Video Generator

A SaaS web application that transforms static photos into animated videos using AI technology. Built with pure PHP 8.2+ and vanilla JavaScript.

## 🚀 Features

- **Photo to Video**: Transform static images into animated videos using AI
- **Multiple Formats**: Support for 16:9, 9:16, and 1:1 aspect ratios
- **Custom Presets**: Cinematic, smooth, fast, and slow motion options
- **Telegram Mini App**: Full integration with Telegram for mobile users
- **User Dashboard**: Manage projects, videos, and credits
- **Public Gallery**: Share and discover community creations
- **Payment System**: Credit-based pricing with multiple payment providers
- **Admin Panel**: User management, content moderation, and analytics

## 📋 Requirements

- PHP 8.2 or higher
- MySQL 8.0 or MariaDB
- Apache 2.4+ with mod_rewrite
- PDO MySQL extension
- cURL extension (for API calls)

## 🛠️ Installation

### Option 1: Docker (Recommended)

```bash
# Clone the repository
git clone <repository-url>
cd photo2video

# Copy environment file
cp .env.example .env

# Configure your .env file (see Configuration section)

# Start containers
docker-compose up -d

# Run migrations
docker exec -it photo2video_app php /var/www/html/bin/migrate.php

# (Optional) Seed sample data
docker exec -it photo2video_app php /var/www/html/bin/seed.php
```

### Option 2: Manual Installation

```bash
# Clone the repository
git clone <repository-url>
cd photo2video

# Copy environment file
cp .env.example .env

# Configure your .env file

# Install PHP dependencies
composer install

# Run migrations
php bin/migrate.php

# (Optional) Seed sample data
php bin/seed.php

# Configure Apache virtual host
# Point DocumentRoot to /path/to/photo2video/public
```

## ⚙️ Configuration

Copy `.env.example` to `.env` and configure the following:

### Application
```env
APP_NAME="Photo2Video"
APP_URL="http://localhost:8080"
APP_ENV="development"  # or "production"
APP_DEBUG="true"       # Set to "false" in production
```

### Database
```env
DB_HOST="db"
DB_PORT="3306"
DB_DATABASE="photo2video"
DB_USERNAME="root"
DB_PASSWORD="root_password"
```

### Telegram Integration
```env
TELEGRAM_BOT_TOKEN="your_bot_token_here"
TELEGRAM_BOT_USERNAME="your_bot_username"
TELEGRAM_APP_URL="https://yourdomain.com"
TELEGRAM_MINI_APP_NAME="Photo2Video"
```

To create a Telegram bot:
1. Message @BotFather on Telegram
2. Use `/newbot` to create a new bot
3. Copy the bot token to `TELEGRAM_BOT_TOKEN`

### Kling Video Provider
```env
KLING_API_URL="https://api.kling.ai/v1"
KLING_API_KEY="your_kling_api_key"
KLING_SECRET_KEY="your_kling_secret_key"
KLING_ENABLED="true"  # Set to "true" when keys are configured
```

Sign up for Kling AI at https://www.kling.ai/ to get API credentials.

### Payments
```env
PAYMENT_PROVIDER="dummy"  # Use "telegram" for real payments
TELEGRAM_PAYMENT_TOKEN="your_payment_token"
```

For dummy mode, payments can be marked as complete manually via admin panel.

## 📁 Project Structure

```
photo2video/
├── public/                 # Web root
│   ├── index.php          # Front controller
│   ├── .htaccess          # Apache configuration
│   └── assets/            # Static assets
│       ├── css/           # Stylesheets
│       └── js/            # JavaScript files
├── app/
│   ├── Core/              # Core framework classes
│   │   ├── App.php
│   │   ├── Config.php
│   │   ├── Database.php
│   │   ├── Logger.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   ├── Session.php
│   │   └── Storage/       # Storage drivers
│   ├── Controllers/        # HTTP controllers
│   │   ├── Api/           # API controllers
│   │   └── ...
│   ├── Middleware/         # Request middleware
│   ├── Providers/          # External service adapters
│   │   ├── Video/         # Video generation providers
│   │   └── Payments/      # Payment providers
│   ├── Telegram/          # Telegram integration
│   ├── routes/            # Route definitions
│   └── View/              # HTML templates
├── bin/                   # CLI tools
│   ├── migrate.php        # Database migrations
│   ├── seed.php           # Database seeder
│   └── worker.php         # Job processor
├── database/
│   └── migrations/        # SQL migrations
├── storage/              # File storage
│   ├── uploads/          # Uploaded files
│   ├── videos/           # Generated videos
│   └── logs/             # Application logs
├── vendor/               # Composer dependencies
├── .env.example          # Environment template
├── docker-compose.yml    # Docker configuration
└── README.md
```

## 🔄 Running the Worker

The video generation worker processes jobs asynchronously:

### Using Docker
```bash
# Run worker in daemon mode
docker-compose exec worker php /var/www/html/bin/worker.php --daemon

# Or as a separate container
docker run -d --name photo2video_worker \
  --network photo2video_photo2video_network \
  -v $(pwd):/var/www/html \
  photo2video_app \
  php /var/www/html/bin/worker.php --daemon
```

### Using Cron
```bash
# Run worker every minute via cron
* * * * * php /path/to/photo2video/bin/worker.php
```

### As Systemd Service
Create `/etc/systemd/system/photo2video-worker.service`:
```ini
[Unit]
Description=Photo2Video Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/photo2video
ExecStart=/usr/bin/php /var/www/photo2video/bin/worker.php --daemon
Restart=always

[Install]
WantedBy=multi-user.target
```

Then enable and start:
```bash
sudo systemctl enable photo2video-worker
sudo systemctl start photo2video-worker
```

## 🎨 Adding New Video Providers

Create a new provider adapter implementing `VideoProviderInterface`:

```php
namespace App\Providers\Video;

class NewProviderAdapter implements VideoProviderInterface
{
    public function getName(): string
    {
        return 'newprovider';
    }
    
    public function createTask(array $payload): array
    {
        // Implementation
    }
    
    // ... implement other interface methods
}
```

Register it in `VideoProviderFactory.php`.

## 💳 Adding New Payment Providers

Create a new payment adapter implementing `PaymentProviderInterface`:

```php
namespace App\Providers\Payments;

class NewPaymentAdapter implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'newpayment';
    }
    
    public function createPayment(array $params): array
    {
        // Implementation
    }
    
    // ... implement other interface methods
}
```

Register it in `PaymentProviderFactory.php`.

## 📡 API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/auth/register | Register new user |
| POST | /api/v1/auth/login | User login |
| POST | /api/v1/auth/logout | User logout |
| POST | /api/v1/auth/telegram | Telegram login |

### Users
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/user/me | Get current user |
| PUT | /api/v1/user/me | Update profile |
| GET | /api/v1/user/credits | Get credit balance |

### Videos
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/videos | List user videos |
| POST | /api/v1/videos | Create video record |
| POST | /api/v1/videos/{id}/generate | Start generation |
| GET | /api/v1/videos/{id}/status | Check status |

### Gallery
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/gallery | List public videos |
| GET | /api/v1/gallery/featured | Featured videos |
| GET | /api/v1/gallery/{id} | View video |

### Payments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/payments | List payments |
| POST | /api/v1/payments/create | Create payment |
| POST | /api/v1/payments/webhook | Payment webhook |

## 🤖 Telegram Bot Commands

- `/start` - Start the bot
- `/help` - Show help
- `/credits` - Check credit balance
- `/menu` - Open Mini App

## 🔒 Security

- CSRF protection on all forms
- Rate limiting on auth endpoints
- Secure password hashing (bcrypt)
- Telegram initData validation
- SQL injection prevention (prepared statements)
- XSS protection (output encoding)
- File upload validation

## 📊 Monitoring

Logs are written to `storage/logs/app.log`. Configure log level in `.env`:

```env
LOG_LEVEL="debug"  # debug, info, warning, error, critical
```

## 🚀 Deployment Checklist

1. [ ] Set `APP_ENV=production`
2. [ ] Set `APP_DEBUG=false`
3. [ ] Configure production database credentials
4. [ ] Set secure `APP_URL` (HTTPS recommended)
5. [ ] Configure Telegram bot token
6. [ ] Set Kling API keys (if enabled)
7. [ ] Configure payment provider
8. [ ] Set up worker process (cron or systemd)
9. [ ] Configure proper file permissions:
```bash
chmod 755 storage
chmod 644 storage/.htaccess
```
10. [ ] Test all endpoints

## 📝 License

MIT License

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📧 Support

For issues and questions, please open a GitHub issue.
