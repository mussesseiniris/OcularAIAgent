# Ocular TYPO3 Chatbot Extension

AI RAG chatbot extension for the Ocular website, built on TYPO3 v13.

---

## Requirements

Make sure you have the following installed before starting:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [DDEV](https://ddev.com/)
- [Git](https://git-scm.com/)
- [Homebrew](https://brew.sh/) — Mac only
- [Chocolatey](https://chocolatey.org/install) — Windows only

---

## Setup Instructions

### Mac

#### 1. Install Docker Desktop
Download and install from [docker.com](https://www.docker.com/products/docker-desktop/). Make sure it is running before continuing.

#### 2. Install DDEV
```bash
brew install ddev/ddev/ddev
```

#### 3. Clone the repository
```bash
git clone <YOUR_GITHUB_REPO_URL>
cd ocular-typo3-extension
```

#### 4. Start the DDEV environment
```bash
ddev start
```

#### 5. Install PHP dependencies
```bash
ddev composer install
```

#### 6. Set up TYPO3
```bash
ddev exec php vendor/bin/typo3 setup
```

Follow the prompts. Use these values for the database:

| Field    | Value  |
|----------|--------|
| Driver   | mysqli |
| Host     | db     |
| Port     | 3306   |
| Username | db     |
| Password | db     |
| Database | db     |

Set your own admin username and password when prompted.

#### 7. Activate the Extension
```bash
ddev exec php vendor/bin/typo3 extension:setup
```

---

### Windows

#### 1. Install Docker Desktop
Download and install from [docker.com](https://www.docker.com/products/docker-desktop/).

Make sure **WSL 2** is enabled. Docker Desktop will prompt you if it isn't. Once installed, make sure Docker Desktop is running before continuing.

#### 2. Install Chocolatey
Open **PowerShell as Administrator** and run:
```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))
```

#### 3. Install DDEV
In PowerShell as Administrator:
```powershell
choco install ddev
```

#### 4. Install Git
```powershell
choco install git
```

After installing Git, close and reopen PowerShell.

#### 5. Clone the repository
```powershell
git clone <YOUR_GITHUB_REPO_URL>
cd ocular-typo3-extension
```

#### 6. Start the DDEV environment
```powershell
ddev start
```

This will download and start all required Docker containers. First time may take a few minutes.

#### 7. Install PHP dependencies
```powershell
ddev composer install
```

#### 8. Set up TYPO3
```powershell
ddev exec php vendor/bin/typo3 setup
```

Follow the prompts. Use these values for the database:

| Field    | Value  |
|----------|--------|
| Driver   | mysqli |
| Host     | db     |
| Port     | 3306   |
| Username | db     |
| Password | db     |
| Database | db     |

Set your own admin username and password when prompted.

#### 9. Activate the Extension
```powershell
ddev exec php vendor/bin/typo3 extension:setup
```

---

## Accessing the site

| URL | Description |
|-----|-------------|
| https://ocular-typo3-extension.ddev.site | Frontend |
| https://ocular-typo3-extension.ddev.site/typo3 | TYPO3 Backend |

---

## Project Structure

```
ocular-typo3-extension/
├── packages/
│   └── chatbot/          ← Our Extension (write code here)
│       ├── Classes/       ← PHP controllers and services
│       ├── Configuration/ ← TYPO3 configuration files
│       └── composer.json
├── public/               ← Web root
├── vendor/               ← All dependencies (do not edit)
├── var/                  ← Cache and logs (auto-generated)
└── composer.json         ← Project dependencies
```

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| CMS | TYPO3 v13 |
| PHP | 8.2 |
| AI Framework | LLPhant 0.11 |
| LLM | Llama 70B  |
| Embeddings | Voyage AI |
| Vector Database | Qdrant |
| Local Dev | DDEV + Docker |

---

## Daily Development

Start the environment each day:
```bash
ddev start
```

Stop when done:
```bash
ddev stop
```

---

## Troubleshooting

**Docker not starting? (Mac)**
```bash
sudo pkill -f docker
open /Applications/Docker.app
```

**Docker not starting? (Windows)**

Restart Docker Desktop from the system tray.

**DDEV not responding?**
```bash
ddev restart
```

**Changes not showing?**
```bash
ddev exec php vendor/bin/typo3 cache:flush
```

## Qdrant and Voyage AI Setup

1. Create `.ddev/docker-compose.qdrant.yaml` with the following content:
   
services:
  qdrant:
    image: qdrant/qdrant:latest
    container_name: ddev-${DDEV_SITENAME}-qdrant
    restart: "no"
    ports:
      - "6333:6333"
    volumes:
      - qdrant_storage:/qdrant/storage
    networks:
      - ddev_default

volumes:
  qdrant_storage:

networks:
  ddev_default:
    external: true
    name: ddev_OcularAIAgent_default

2. Restart DDEV:
   ddev restart

3. Verify Qdrant is running:
   curl http://localhost:6333

4. Create the Qdrant collection:
   Invoke-WebRequest -Uri "http://localhost:6333/collections/ocular_chunks" -Method PUT -ContentType "application/json" -Body '{"vectors": {"openai": {"size": 1024, "distance": "Cosine"}}}' -UseBasicParsing

5. Verify the collection was created:
   Invoke-WebRequest -Uri "http://localhost:6333/collections/ocular_chunks" -UseBasicParsing

   OR 

   Check on "http://localhost:6333/dashboard"

  

6. Install Qdrant PHP client
ddev composer require hkulekci/qdrant -W 

OR 

ddev composer install

7. Set Voyage AI API key
ddev dotenv set .ddev/.env --voyage-ai-api-key=your_key_here

8. Restart DDEV
ddev restart

## Rate limit of questions setup

Every time user sends message, their IP is looked up in a MySQL database and their count is increased by 1 (if no record yet, new row with their IP is created with count = 1), and block them if they have exceeded the number of question limit. 

Record of number of questions per IP resets after 24 hours by checking timestamp of when counting started. 

Current rate limit = 10, and reset time window = 24 hours.

Setting up MySQl database that records IP of user, counts number of questions and records timestamp of when counting started:

Option 1: Through TYPO3 Backend 
  * Go to `https://ocular-typo3-extension.ddev.site/typo3`
  * Log in as admin
  * Go to Admin Tools -> Maitenance 
  * Click Analyse Database Structure
  * Apply the changes 

Option 2: Create table manually via MySQL
  * ```bash 
      ddev my sql
    ```
  * ```bash 
    USE db;
    CREATE TABLE tx_chatbot_rate_limit (
      ip_hash VARCHAR(64) NOT NULL DEFAULT '',
      question_count INT NOT NULL DEFAULT 0,
      started_at INT NOT NULL DEFAULT 0,
      PRIMARY KEY (ip_hash)
    );
  * Verify it worked;
    ```bash
    SHOW TABLES;
    ```

## Turnstile setup

Currently siteKey is test siteKey = 1x00000000000000000000AA located in constants.typoscript as private key is also a test private key.
Add in ddev .env file: 

```bash
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

Can change both test siteKey and private key to real ones when creating a widget for own domain in Cloudflare.

   


