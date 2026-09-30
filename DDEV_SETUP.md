<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# DDEV Setup Guide for TYPO3 Testing

## Quick Start

Start the DDEV environment and install TYPO3:

```bash
ddev start
ddev install-v13  # or ddev install-v12
```

## TYPO3 13 Backend Access Issue

**Problem:** TYPO3 13 has strict security that requires HTTP Referer headers for backend requests. This causes `MissingReferrerException` errors in DDEV.

**Solution:** The `AdditionalConfiguration.php` file disables referrer enforcement for development:

```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['security.backend.enforceReferrer'] = false;
```

This is automatically applied during `ddev install-v13`.

If you still encounter referrer errors, run:

```bash
ddev fix-typo3-referrer
```

## Access URLs

### TYPO3 v13
- **Frontend:** https://v13.temporal-cache.ddev.site/
- **Backend:** https://v13.temporal-cache.ddev.site/typo3/
- **Credentials:** admin / Password:joh316

### TYPO3 v12
- **Frontend:** https://v12.temporal-cache.ddev.site/
- **Backend:** https://v12.temporal-cache.ddev.site/typo3/
- **Credentials:** admin / Password:joh316

## Available DDEV Commands

```bash
ddev install-v12        # Install TYPO3 v12.4 LTS with extension
ddev install-v13        # Install TYPO3 v13.4 LTS with extension
ddev fix-typo3-referrer # Fix TYPO3 13 referrer check issue
```

## Manual Testing

### Backend Module Testing

1. Log into backend: https://v13.temporal-cache.ddev.site/typo3/
2. Navigate to: **Web → Temporal Cache**
3. Test content filtering:
   - All content
   - Active content (currently visible)
   - Expired content (past endtime)
   - Scheduled content (future starttime)

4. Test harmonization:
   - Select harmonizable content
   - Click "Harmonize" button
   - Verify timestamps aligned to configured slots

### Cache Invalidation Testing

1. Create test content:
   ```bash
   ddev exec "cd /var/www/html/v13 && vendor/bin/typo3 content:create \
     --pid=1 \
     --header='Test Temporal Content' \
     --starttime='+1 hour'"
   ```

2. Visit frontend page and note content is NOT visible
3. Adjust system time OR wait 1 hour
4. Refresh page - content should appear automatically

### Scheduler Task Testing

1. Navigate to: **System → Scheduler**
2. Create task: `Temporal Cache Harmonization`
3. Configure frequency: Every hour
4. Execute manually to test batch processing

## Troubleshooting

### Backend Shows "Missing referrer" Error

**Run:** `ddev fix-typo3-referrer`

This disables TYPO3 13's strict referrer check for DDEV development.

### Extension Not Visible in Backend

**Check:** Extension is properly symlinked

```bash
ddev exec "ls -la /var/www/html/v13/public/typo3conf/ext/ | grep temporal"
```

Should show: `nr_temporal_cache -> /var/www/html/typo3conf/ext/nr_temporal_cache`

### Database Connection Errors

**Run:** `ddev restart`

DDEV database might not be fully started.

### 404 on Frontend

**Check:** TYPO3 site configuration exists

```bash
ddev exec "ls /var/www/html/v13/config/sites/"
```

Should show site configuration directories.

## Development Workflow

### Running Tests in DDEV

```bash
# Unit tests
ddev exec "cd /var/www/html/typo3conf/ext/nr_temporal_cache && composer test:unit"

# Functional tests (requires database)
ddev exec "cd /var/www/html/v13 && vendor/bin/phpunit \
  -c /var/www/html/typo3conf/ext/nr_temporal_cache/Build/phpunit/FunctionalTests.xml"
```

### Accessing Extension Files

Extension is mounted at: `/var/www/html/typo3conf/ext/nr_temporal_cache`

```bash
# Enter DDEV container
ddev ssh

# Navigate to extension
cd /var/www/html/typo3conf/ext/nr_temporal_cache

# Run commands
composer test:unit
```

### Viewing Logs

```bash
# TYPO3 logs
ddev exec "tail -f /var/www/html/v13/var/log/typo3_*.log"

# Apache logs
ddev logs -f
```

## Configuration Files

### AdditionalConfiguration.php

Location: `/var/www/html/v13/public/typo3conf/AdditionalConfiguration.php`

Disables strict security for DDEV development:
- Referrer enforcement
- SSL requirements
- Cookie security

**⚠️ WARNING:** These settings are for DDEV development ONLY. Never use in production!

### Extension Configuration

Location: Backend → Settings → Extension Configuration → nr_temporal_cache

Configure:
- Harmonization slots (time alignment)
- Timing strategy (dynamic/scheduler/hybrid)
- Scoping strategy (global/per-page/per-content)

## Production vs Development

### Development (DDEV)
- ✅ Referrer enforcement: **disabled**
- ✅ SSL requirement: **disabled**
- ✅ Cookie security: **disabled**

### Production
- 🔒 Referrer enforcement: **enabled** (TYPO3 default)
- 🔒 SSL requirement: **enabled** (recommended)
- 🔒 Cookie security: **enabled** (HTTPS required)

**Never deploy `AdditionalConfiguration.php` to production!**

## Support

If you encounter issues not covered here:

1. Check DDEV logs: `ddev logs`
2. Check TYPO3 logs: `ddev exec "ls /var/www/html/v13/var/log/"`
3. Restart DDEV: `ddev restart`
4. Rebuild DDEV: `ddev rebuild`
5. Reinstall TYPO3: `ddev install-v13`
