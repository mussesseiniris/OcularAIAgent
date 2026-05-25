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

## Qdrant Setup

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
   Invoke-WebRequest -Uri "http://localhost:6333/collections/ocular_chunks" -Method PUT -ContentType "application/json" -Body '{"vectors": {"size": 1024, "distance": "Cosine"}}' -UseBasicParsing

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
   


