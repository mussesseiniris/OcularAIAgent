# Ocular TYPO3 Chatbot Extension

AI RAG chatbot extension for the Ocular website, built on TYPO3 v13.

---

## Requirements

Make sure you have the following installed before starting:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Homebrew](https://brew.sh/) (Mac only)
- [DDEV](https://ddev.com/)
- Git

---

## Setup Instructions

### 1. Install Docker Desktop

Download and install from [docker.com](https://www.docker.com/products/docker-desktop/). Make sure it is running before continuing.

### 2. Install DDEV

```bash
brew install ddev/ddev/ddev
```

### 3. Clone the repository

```bash
git clone <YOUR_GITHUB_REPO_URL>
cd ocular-typo3-extension
```

### 4. Start the DDEV environment

```bash
ddev start
```

This will download and start all required Docker containers (web server, database, etc). First time may take a few minutes.

### 5. Install PHP dependencies

```bash
ddev composer install
```

This automatically installs TYPO3 v13, LLPhant, and all other dependencies listed in `composer.json`.

### 6. Set up TYPO3

```bash
ddev exec php vendor/bin/typo3 setup
```

Follow the prompts. Use these values for the database:

| Field    | Value   |
|----------|---------|
| Driver   | mysqli  |
| Host     | db      |
| Port     | 3306    |
| Username | db      |
| Password | db      |
| Database | db      |

Set your own admin username and password when prompted.

### 7. Activate the Extension

```bash
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
| LLM | Claude (Anthropic) |
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

**Docker not starting?**
```bash
sudo pkill -f docker
open /Applications/Docker.app
```

**DDEV not responding?**
```bash
ddev restart
```

**Changes not showing?**
```bash
ddev exec php vendor/bin/typo3 cache:flush
```

