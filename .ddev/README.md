# DDEV Development Environment for temporal_cache Extension

This directory contains DDEV configuration for developing and testing the temporal_cache TYPO3 extension across multiple TYPO3 versions.

## Prerequisites

- [DDEV](https://ddev.readthedocs.io/) installed and configured
- Docker running on your machine
- Composer available

## Quick Start

```bash
# Start DDEV environment
ddev start

# Install TYPO3 v12.4 LTS with extension
ddev install-v12

# OR install TYPO3 v13.4 LTS with extension
ddev install-v13

# OR install both versions
ddev install-all
```

## Available Commands

| Command | Description |
|---------|-------------|
| `ddev start` | Start the DDEV environment |
| `ddev install-v12` | Install TYPO3 v12.4 LTS with temporal_cache |
| `ddev install-v13` | Install TYPO3 v13.4 LTS with temporal_cache |
| `ddev install-all` | Install all TYPO3 versions |
| `ddev stop` | Stop the DDEV environment |
| `ddev restart` | Restart the DDEV environment |
| `ddev ssh` | SSH into the web container |
| `ddev composer` | Run composer commands |
| `ddev exec` | Execute commands in web container |

## Access URLs

### Overview Page
- https://temporal-cache.ddev.site/

### TYPO3 v12.4 LTS
- **Frontend**: https://v12.temporal-cache.ddev.site/
- **Backend**: https://v12.temporal-cache.ddev.site/typo3/

### TYPO3 v13.4 LTS
- **Frontend**: https://v13.temporal-cache.ddev.site/
- **Backend**: https://v13.temporal-cache.ddev.site/typo3/

## Backend Credentials

- **Username**: `admin`
- **Password**: `Password:joh316`

## Database Configuration

The default configuration uses **SQLite** for faster development. Each TYPO3 version has its own SQLite database file.

To switch to MariaDB for production-like testing:

```bash
# Edit .ddev/config.yaml
database:
  type: mariadb
  version: "10.11"

# Restart DDEV
ddev restart

# Reinstall TYPO3 versions
ddev install-all
```

## Testing the Extension

### Manual Testing

1. Navigate to the backend of any TYPO3 version
2. Go to **Tools → Temporal Cache** to access the backend module
3. Create test pages with starttime/endtime fields
4. Observe automatic cache invalidation behavior

### Run Extension Tests

```bash
# Run all tests
ddev composer test

# Run unit tests only
ddev composer test:unit

# Run functional tests
ddev composer test:functional
```

### CLI Commands Testing

```bash
# Enter web container
ddev ssh

# Navigate to TYPO3 installation
cd v12  # or v13

# Test CLI commands
vendor/bin/typo3 temporalcache:verify
vendor/bin/typo3 temporalcache:analyze --days=7
vendor/bin/typo3 temporalcache:list --format=table
vendor/bin/typo3 temporalcache:harmonize --dry-run
```

## Extension Configuration

The extension is automatically configured in each TYPO3 installation with default settings:

- **Scoping Strategy**: `global` (site-wide invalidation)
- **Timing Strategy**: `dynamic` (event-based)
- **Harmonization**: Disabled

To test different configurations, edit the LocalConfiguration.php in each TYPO3 version:

```bash
ddev ssh
nano v12/public/typo3conf/LocalConfiguration.php
# OR
nano v13/public/typo3conf/LocalConfiguration.php
```

Look for the `temporal_cache` section under `EXTENSIONS`.

## Development Workflow

### Making Changes to Extension Code

1. Edit files in the project root (your extension directory)
2. Changes are immediately visible in all TYPO3 installations via symlinks
3. Clear TYPO3 caches if needed:
   ```bash
   ddev ssh
   cd v12  # or v13
   vendor/bin/typo3 cache:flush
   ```

### Adding New Files

1. Add files to the project root
2. They automatically appear in all TYPO3 installations
3. No need to reinstall or recreate symlinks

## Troubleshooting

### Installation Fails

```bash
# Check logs
ddev logs

# Try restarting
ddev restart

# Reinstall specific version
ddev ssh
rm -rf v12  # or v13
exit
ddev install-v12  # or install-v13
```

### Extension Not Visible

```bash
ddev ssh
cd v12  # or v13
ls -la public/typo3conf/ext/
# Should see temporal_cache symlink

# If missing, recreate:
ln -sf /var/www/html/typo3conf/ext/temporal_cache public/typo3conf/ext/temporal_cache
vendor/bin/typo3 extension:activate temporal_cache
```

### Database Already Exists

```bash
ddev ssh
rm -f v12/var/sqlite/*.sqlite  # or v13
cd v12  # or v13
vendor/bin/typo3 setup --force ...
```

### Port Conflicts

Edit `.ddev/config.yaml` and change ports:

```yaml
router_http_port: "8080"
router_https_port: "8443"
```

Then restart: `ddev restart`

## XDebug

Enable/disable XDebug for debugging:

```bash
# Enable
ddev xdebug on

# Disable
ddev xdebug off
```

## Mailpit (Email Testing)

Access Mailpit to view emails sent by TYPO3:

- **URL**: https://temporal-cache.ddev.site:8026/
- All emails sent by TYPO3 are caught and displayed here

## Performance Tips

- Use SQLite for development (default) - faster and uses less resources
- Switch to MariaDB only for final production-parity testing
- Keep only the TYPO3 versions you're actively testing installed
- Stop DDEV when not in use: `ddev stop`

## Uninstalling

```bash
# Stop DDEV
ddev stop

# Remove TYPO3 installations
rm -rf v12 v13

# Remove DDEV configuration (optional)
rm -rf .ddev
```

## Resources

- [DDEV Documentation](https://ddev.readthedocs.io/)
- [TYPO3 Documentation](https://docs.typo3.org/)
- [Extension Documentation](../Documentation/)

## Support

For issues with:
- **DDEV setup**: Check DDEV documentation or project README
- **Extension functionality**: See extension documentation or create an issue
- **TYPO3**: Consult TYPO3 documentation or community resources
