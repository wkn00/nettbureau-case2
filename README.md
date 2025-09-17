# Pipedrive Integration for Nettbureau AS

This PHP integration automatically creates **organizations**, **persons**, and **deals (leads)** in Pipedrive from lead data.

---

## Features

- Create organizations in Pipedrive  
- Create persons and link to organizations  
- Create deals (leads) and link to organizations and persons  
- Handle custom fields with option mapping  
- Error handling and logging  
- Environment-based configuration via `.env`

---

## Requirements

- PHP 7.3 or higher  
- cURL extension  
- JSON extension  
- Composer (for dependencies)

---

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/case2-nettbureau.git
cd case2-nettbureau
```
2. Install dependencies:
```bash
composer install
```

3. Make sure to fill in your Pipedrive API token and domain in the `.env` file before running the script.


4. Add your Pipedrive credentials to .env:

```ini
PIPEDRIVE_API_TOKEN=your_api_token
PIPEDRIVE_DOMAIN=your_domain
```

## Usage

Run the integration from the command line:

```bash
php src/PipedriveIntegration.php
```