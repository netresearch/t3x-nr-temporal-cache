# CLI Commands - Quick Reference

Fast reference guide for temporal cache CLI commands.

## Command Overview

```bash
# Analyze temporal content and cache statistics
vendor/bin/typo3 temporalcache:analyze [--workspace=0] [--language=0] [--days=30] [-v]

# Verify database indexes and configuration
vendor/bin/typo3 temporalcache:verify [-v]

# Harmonize temporal fields to time slots
vendor/bin/typo3 temporalcache:harmonize [--dry-run] [--table=pages|tt_content] [-v]

# List all temporal content
vendor/bin/typo3 temporalcache:list [--table=pages|tt_content] [--format=table|json|csv] [--sort=uid|title|starttime]
```

## Common Workflows

### First Time Setup

```bash
# 1. Verify system
vendor/bin/typo3 temporalcache:verify

# 2. Analyze current state
vendor/bin/typo3 temporalcache:analyze -v

# 3. Review temporal content
vendor/bin/typo3 temporalcache:list --sort=starttime
```

### Daily Operations

```bash
# Check upcoming transitions
vendor/bin/typo3 temporalcache:analyze --days=7

# View next transitions
vendor/bin/typo3 temporalcache:list --upcoming --limit=10
```

### Harmonization Workflow

```bash
# 1. Preview changes
vendor/bin/typo3 temporalcache:harmonize --dry-run -v

# 2. Apply harmonization
vendor/bin/typo3 temporalcache:harmonize

# 3. Verify impact
vendor/bin/typo3 temporalcache:analyze
```

### Reporting & Export

```bash
# Export to CSV
vendor/bin/typo3 temporalcache:list --format=csv > report.csv

# Export to JSON
vendor/bin/typo3 temporalcache:list --format=json > data.json

# Analysis report
vendor/bin/typo3 temporalcache:analyze --days=90 -v > analysis.txt
```

## Option Reference

### Global Options (all commands)

| Option | Description |
|--------|-------------|
| `-h, --help` | Display help information |
| `-v, --verbose` | Increase verbosity |
| `-q, --quiet` | Suppress output |

### temporalcache:analyze

| Option | Default | Description |
|--------|---------|-------------|
| `-w, --workspace=N` | 0 | Workspace UID |
| `-l, --language=N` | 0 | Language UID (-1 = all) |
| `-d, --days=N` | 30 | Analysis period in days |

### temporalcache:harmonize

| Option | Default | Description |
|--------|---------|-------------|
| `--dry-run` | - | Preview without changes |
| `-w, --workspace=N` | 0 | Workspace UID |
| `-l, --language=N` | 0 | Language UID |
| `-t, --table=NAME` | all | Filter by table (pages/tt_content) |

### temporalcache:list

| Option | Default | Description |
|--------|---------|-------------|
| `-t, --table=NAME` | all | Filter by table |
| `-w, --workspace=N` | 0 | Workspace UID |
| `-l, --language=N` | 0 | Language UID |
| `-u, --upcoming` | - | Show only upcoming |
| `-s, --sort=FIELD` | uid | Sort field |
| `-f, --format=FORMAT` | table | Output format (table/json/csv) |
| `--limit=N` | - | Limit results |

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error or validation failure |

## Examples by Use Case

### Performance Optimization

```bash
# Identify missing indexes
vendor/bin/typo3 temporalcache:verify

# Find peak transition periods
vendor/bin/typo3 temporalcache:analyze --days=90 -v

# Apply harmonization to reduce cache churn
vendor/bin/typo3 temporalcache:harmonize --dry-run
vendor/bin/typo3 temporalcache:harmonize
```

### Content Planning

```bash
# See next month's scheduled content
vendor/bin/typo3 temporalcache:list --upcoming --sort=starttime --limit=50

# Export campaign schedule
vendor/bin/typo3 temporalcache:list --upcoming --format=csv > campaigns.csv

# Analyze high-activity periods
vendor/bin/typo3 temporalcache:analyze --days=365
```

### Multi-Site Management

```bash
# Compare workspaces
vendor/bin/typo3 temporalcache:list -w 0 --format=csv > live.csv
vendor/bin/typo3 temporalcache:list -w 1 --format=csv > staging.csv

# Analyze per language
vendor/bin/typo3 temporalcache:analyze -l 0  # Default language
vendor/bin/typo3 temporalcache:analyze -l 1  # Language 1
```

### Maintenance

```bash
# Weekly verification
vendor/bin/typo3 temporalcache:verify

# Monthly harmonization
vendor/bin/typo3 temporalcache:harmonize --dry-run
vendor/bin/typo3 temporalcache:harmonize

# Quarterly analysis
vendor/bin/typo3 temporalcache:analyze --days=90 -v
```

## Automation Examples

### Cron Jobs

```bash
# Daily analysis at 2 AM
0 2 * * * /path/to/vendor/bin/typo3 temporalcache:analyze --days=7

# Weekly harmonization (Sunday 3 AM)
0 3 * * 0 /path/to/vendor/bin/typo3 temporalcache:harmonize

# Monthly verification (1st at 1 AM)
0 1 1 * * /path/to/vendor/bin/typo3 temporalcache:verify
```

### Shell Script

```bash
#!/bin/bash
# Daily temporal cache monitoring

LOG_DIR="/var/log/temporal-cache"
DATE=$(date +%Y-%m-%d)
mkdir -p $LOG_DIR

# Verify
vendor/bin/typo3 temporalcache:verify > "$LOG_DIR/verify-$DATE.log" 2>&1

# Analyze
vendor/bin/typo3 temporalcache:analyze --days=30 > "$LOG_DIR/analysis-$DATE.log" 2>&1

# Export upcoming
vendor/bin/typo3 temporalcache:list --upcoming --format=csv > "$LOG_DIR/upcoming-$DATE.csv"
```

## Troubleshooting

### Commands not available

```bash
vendor/bin/typo3 cache:flush
rm -rf var/cache/*
```

### Harmonization disabled

```bash
# Check configuration
vendor/bin/typo3 temporalcache:verify

# Enable in: Settings > Extension Configuration > temporal_cache
```

### Database errors

```bash
# Update schema
vendor/bin/typo3 extension:setup

# Verify indexes
vendor/bin/typo3 temporalcache:verify
```

## Best Practices

1. **Always use --dry-run first** when harmonizing
2. **Backup database** before harmonization
3. **Run verify** after configuration changes
4. **Monitor regularly** with analyze command
5. **Export reports** for tracking trends
6. **Test on staging** before production

## Quick Tips

- Use `-v` for detailed output and debugging
- Combine filters for precise results
- Use JSON format for automated processing
- Schedule regular verification checks
- Monitor peak transition days for capacity planning
- Export to CSV for stakeholder reports

## Getting Help

```bash
# Command-specific help
vendor/bin/typo3 temporalcache:analyze --help
vendor/bin/typo3 temporalcache:verify --help
vendor/bin/typo3 temporalcache:harmonize --help
vendor/bin/typo3 temporalcache:list --help

# List all available commands
vendor/bin/typo3 list temporalcache
```

---

For detailed documentation, see [CLI-Commands.md](CLI-Commands.md)
