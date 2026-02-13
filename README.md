# Laravel Marketing Mail

[![GitHub release](https://img.shields.io/github/v/release/JuniYadi/laravel-email-marketing?include_prereleases)](https://github.com/JuniYadi/laravel-email-marketing/releases)
[![Docker](https://img.shields.io/badge/ghcr.io-juniyadi%2Flaravel--marketing--mail-blue)](https://github.com/JuniYadi/laravel-email-marketing/pkgs/container/laravel-marketing-mail)
[![License](https://img.shields.io/github/license/JuniYadi/laravel-email-marketing)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.5-8892BF)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20)](https://laravel.com)

**Self-hosted marketing email platform with AWS SES integration**

A powerful, self-hosted email marketing solution built with Laravel 12. Manage contacts, design beautiful email templates with a visual builder, and send campaigns through AWS SES with real-time tracking via SNS webhooks.

---

## Features

### Authentication
- Flexible authentication with Laravel Fortify
- Google OAuth integration (optional)
- Two-factor authentication (2FA) support
- Email verification
- Password reset functionality

### Contact Management
- Organize contacts into groups/segments
- CSV import for bulk contact upload
- Automatic suppression for bounces and unsubscribes
- CAN-SPAM, GDPR, and CASL compliant

### Email Template Builder
- Visual drag-and-drop email builder
- Template variables: `{{ first_name }}`, `{{ email }}`, `{{ unsubscribe_url }}`
- Version history and template snapshots

### Broadcasts & Campaigns
- Schedule campaigns to contact groups
- Rate limiting (messages per minute)
- Real-time tracking: delivery, opens, clicks, bounces, complaints
- Duplicate broadcasts with template preservation

### Analytics Dashboard
- Daily trend charts
- Campaign performance metrics
- Per-recipient event tracking

### AWS Integration
- AWS SES for reliable email delivery
- AWS SNS webhooks for real-time event tracking
- Email tagging for campaign correlation

---

## Architecture

```mermaid
flowchart TB
    subgraph App["Laravel Marketing Mail"]
        Contacts["Contacts & Groups"]
        Templates["Template Builder"]
        Broadcasts["Broadcasts"]
        Queue["Queue Worker"]
        Webhook["SNS Webhook<br/>/webhooks/sns"]
    end

    subgraph AWS["AWS Cloud"]
        SES["AWS SES<br/>(Email Sending)"]
        SNS["AWS SNS Topic<br/>(Event Notifications)"]
    end

    Contacts --> Broadcasts
    Templates --> Broadcasts
    Broadcasts --> Queue
    Queue -->|"Send with tags<br/>(broadcast_id, contact_id)"| SES

    SES -->|"Delivery, Bounce,<br/>Complaint events"| SNS
    SNS -->|"HTTP POST"| Webhook
    Webhook -->|"Store events"| EventsDB[("Event Logs")]

    subgraph Tracking["Real-time Tracking"]
        Opens["Opens"]
        Clicks["Clicks"]
    end

    SES -->|"Open/Click tracking"| Tracking
    Tracking --> SNS
```

### Data Flow
1. **Sending**: Broadcast → Queue → AWS SES (with correlation tags)
2. **Tracking**: AWS SES/SNS → Webhook (`/webhooks/sns`) → Database
3. **Events**: Delivery, Bounce, Complaint, Open, Click

---

## Quick Start with Docker

The fastest way to get started is using our pre-built Docker image.

### Pull the Image

```bash
docker pull ghcr.io/juniyadi/laravel-marketing-mail:latest
```

### Run with Docker Compose

Create a `docker-compose.yml` file:

```yaml
version: '3.8'

networks:
  marketing-network:
    driver: bridge

services:
  app:
    image: ghcr.io/juniyadi/laravel-marketing-mail:latest
    container_name: marketing-mail-app
    ports:
      - "8080:80"
    environment:
      - APP_URL=http://localhost:8080
      - DB_HOST=mariadb
      - DB_DATABASE=marketing_mail
      - DB_USERNAME=marketing
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
      - AWS_DEFAULT_REGION=us-east-1
      - MAIL_FROM_ADDRESS=noreply@yourdomain.com
    networks:
      - marketing-network
    depends_on:
      mariadb:
        condition: service_healthy
      redis:
        condition: service_started

  mariadb:
    image: mariadb:11
    container_name: marketing-mail-db
    environment:
      - MYSQL_DATABASE=marketing_mail
      - MYSQL_USER=marketing
      - MYSQL_PASSWORD=secret
      - MYSQL_ROOT_PASSWORD=root_secret
    volumes:
      - mariadb_data:/var/lib/mysql
    networks:
      - marketing-network
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: marketing-mail-redis
    networks:
      - marketing-network

  phpmyadmin:
    image: phpmyadmin:latest
    container_name: marketing-mail-pma
    environment:
      - PMA_HOST=mariadb
      - PMA_USER=root
      - PMA_PASSWORD=root_secret
      - UPLOAD_LIMIT=64M
    ports:
      - "8081:80"
    networks:
      - marketing-network
    depends_on:
      mariadb:
        condition: service_healthy

volumes:
  mariadb_data:
```

### Start the Application

```bash
# Create .env file for AWS credentials
echo "AWS_ACCESS_KEY_ID=your_key" > .env
echo "AWS_SECRET_ACCESS_KEY=your_secret" >> .env

# Start services
docker-compose --env-file .env up -d
```

### Access Points

| Service | URL |
|---------|-----|
| Application | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |

---

## Google OAuth Setup (Optional)

This application supports Google OAuth authentication with three configurable modes:

### Authentication Modes

| Mode | Description |
|------|-------------|
| `both` | Users can authenticate via Google OAuth **or** email/password (default) |
| `google_only` | Only Google OAuth authentication is allowed |
| `manual_only` | Only email/password authentication is allowed |

### Setup Steps

#### 1. Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a new project or select an existing one
3. Navigate to **APIs & Services** → **Credentials**
4. Click **Create Credentials** → **OAuth 2.0 Client ID**
5. Configure the OAuth consent screen if you haven't already
6. Select **Web application** as the application type
7. Add authorized redirect URI:
   ```
   http://localhost:8080/auth/google/callback  # For local development
   https://yourdomain.com/auth/google/callback # For production
   ```
8. Save the **Client ID** and **Client Secret**

#### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# Authentication Mode (both|google_only|manual_only)
AUTH_MODE=both
```

#### 3. Test Google OAuth

1. Start your application
2. Navigate to `/login`
3. Click the **Continue with Google** button
4. Authorize with your Google account
5. You should be redirected back and logged in

### Features

- **Smart Account Linking**: Automatically links Google accounts to existing users with the same email address
- **Email Verification**: Trust Google's email verification (no need for separate email confirmation)
- **Encrypted Token Storage**: Google tokens are encrypted in the database
- **Avatar Sync**: Automatically syncs profile pictures from Google
- **Secure Password Handling**: Prevents password login for Google-only accounts

### Docker Configuration

If using Docker, add these environment variables to your `docker-compose.yml`:

```yaml
services:
  app:
    environment:
      - GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}
      - GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}
      - GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
      - AUTH_MODE=both
```

Then update your `.env` file:

```bash
echo "GOOGLE_CLIENT_ID=your-client-id" >> .env
echo "GOOGLE_CLIENT_SECRET=your-secret" >> .env
echo "AUTH_MODE=both" >> .env
```

---

## AWS Setup Guide

This application requires AWS SES for sending emails and AWS SNS for receiving delivery notifications (bounces, complaints, opens, clicks).

### Prerequisites

- AWS Account with SES access
- AWS CLI installed and configured (`aws configure`)
- Verified domain or email address in SES

---

### Part 1: AWS SES Setup

#### 1.1 Verify Your Sending Domain

```bash
# Verify domain ownership
aws ses verify-domain-identity \
  --domain yourdomain.com \
  --region us-east-1

# Output: {"VerificationToken": "..."}

# Add the verification token as a TXT record in your DNS:
# _amazonses.yourdomain.com TXT "verification-token-here"
```

#### 1.2 Configure DKIM (Recommended)

```bash
# Generate DKIM tokens
aws ses verify-domain-dkim \
  --domain yourdomain.com \
  --region us-east-1

# Output shows 3 CNAME records to add to your DNS
# Add them as:
# selector1._domainkey.yourdomain.com CNAME "token1.dkim.amazonses.com"
# selector2._domainkey.yourdomain.com CNAME "token2.dkim.amazonses.com"
# selector3._domainkey.yourdomain.com CNAME "token3.dkim.amazonses.com"
```

#### 1.3 Request Production Access (if in sandbox)

```bash
# Check current sending limits
aws ses get-send-quota --region us-east-1

# If in sandbox, request production access via AWS Console:
# SES → Account dashboard → Request production access
```

#### 1.4 Create IAM User for SES

```bash
# Create IAM user
aws iam create-user --user-name marketing-mail-ses

# Create access key
aws iam create-access-key --user-name marketing-mail-ses
# Save the AccessKeyId and SecretAccessKey for your .env

# Attach SES policy
cat > ses-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ses:SendEmail",
        "ses:SendRawEmail"
      ],
      "Resource": "*"
    }
  ]
}
EOF

aws iam put-user-policy \
  --user-name marketing-mail-ses \
  --policy-name SESSendAccess \
  --policy-document file://ses-policy.json
```

---

### Part 2: AWS SNS Setup for Webhooks

#### 2.1 Create SNS Topic

```bash
# Create SNS topic for SES notifications
aws sns create-topic \
  --name ses-notifications \
  --region us-east-1

# Output: {"TopicArn": "arn:aws:sns:us-east-1:123456789:ses-notifications"}
# Save this TopicArn for configuration
```

#### 2.2 Configure SES to Send to SNS

```bash
# Set your webhook URL (replace with your domain)
WEBHOOK_URL="https://your-domain.com/webhooks/sns"
TOPIC_ARN="arn:aws:sns:us-east-1:123456789:ses-notifications"

# Configure SES to send notifications to SNS
aws ses set-identity-notification-topic \
  --identity yourdomain.com \
  --notification-type Bounce \
  --sns-topic $TOPIC_ARN \
  --region us-east-1

aws ses set-identity-notification-topic \
  --identity yourdomain.com \
  --notification-type Complaint \
  --sns-topic $TOPIC_ARN \
  --region us-east-1

aws ses set-identity-notification-topic \
  --identity yourdomain.com \
  --notification-type Delivery \
  --sns-topic $TOPIC_ARN \
  --region us-east-1
```

#### 2.3 Subscribe Webhook to SNS Topic

```bash
# Create HTTP subscription to your webhook
aws sns subscribe \
  --topic-arn $TOPIC_ARN \
  --protocol https \
  --notification-endpoint $WEBHOOK_URL \
  --region us-east-1

# Output: {"SubscriptionArn": "pending confirmation"}
```

#### 2.4 Confirm Subscription

SNS will send a confirmation request to your webhook. The application handles this automatically, but you can also manually confirm:

```bash
# List subscriptions to get the confirmation token
aws sns list-subscriptions-by-topic \
  --topic-arn $TOPIC_ARN \
  --region us-east-1

# Or check your application logs for the SubscribeURL and visit it
# The webhook controller auto-confirms subscriptions
```

---

### Part 3: Verification

#### 3.1 Test SES Sending

```bash
# Send test email (replace with verified email)
aws ses send-email \
  --from "noreply@yourdomain.com" \
  --destination "ToAddresses=test@example.com" \
  --message "Subject={Data=Test Email},Body={Text={Data=This is a test}}" \
  --region us-east-1
```

#### 3.2 Test Webhook Endpoint

```bash
# Verify webhook is accessible
curl -X POST https://your-domain.com/webhooks/sns \
  -H "Content-Type: application/json" \
  -d '{"Type": "Notification", "Message": "{}"}'
```

---

### Environment Variables Summary

```env
# AWS Configuration
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=us-east-1

# Mail Configuration
MAIL_MAILER=ses
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

---

## Manual Installation

### Requirements

- PHP 8.5+
- Composer 2
- Node.js 18+ & NPM
- MySQL/MariaDB 10.3+
- Redis (recommended for queues)

### Installation Steps

```bash
# Clone the repository
git clone https://github.com/JuniYadi/laravel-email-marketing.git
cd laravel-email-marketing

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
npm install && npm run build

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Create first user
php artisan tinker
>>> User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')])

# Start queue worker (in production, use Supervisor)
php artisan queue:work
```

---

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `APP_URL` | Application URL (used for webhooks) | Yes |
| `DB_HOST` | Database host | Yes |
| `DB_DATABASE` | Database name | Yes |
| `DB_USERNAME` | Database username | Yes |
| `DB_PASSWORD` | Database password | Yes |
| `AWS_ACCESS_KEY_ID` | AWS access key | Yes |
| `AWS_SECRET_ACCESS_KEY` | AWS secret key | Yes |
| `AWS_DEFAULT_REGION` | AWS region (e.g., us-east-1) | Yes |
| `MAIL_FROM_ADDRESS` | Sender email address | Yes |
| `MAIL_FROM_NAME` | Sender name | No |
| `QUEUE_CONNECTION` | Queue driver (database/redis) | No |
| `BROADCAST_ALLOWED_DOMAINS` | Allowed sender domains | No |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | No (OAuth) |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret | No (OAuth) |
| `GOOGLE_REDIRECT_URI` | Google OAuth redirect URI | No (OAuth) |
| `AUTH_MODE` | Authentication mode (both/google_only/manual_only) | No |

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12 (PHP 8.5) |
| Frontend | Livewire 4 + Flux UI 2 |
| CSS | Tailwind CSS |
| JavaScript | Alpine.js |
| Testing | Pest 4 |
| Email | AWS SES |
| Tracking | AWS SNS Webhooks |

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is open-sourced software licensed under the [MIT License](LICENSE).

---

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/JuniYadi/laravel-email-marketing/issues).
