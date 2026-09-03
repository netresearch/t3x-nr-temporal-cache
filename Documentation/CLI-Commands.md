# Temporal Cache CLI Commands

This document provides comprehensive documentation for all CLI commands available in the temporal cache extension.

## Table of Contents

1. [Command Overview](#command-overview)
2. [temporalcache:analyze](#temporalcacheanalyze)
3. [temporalcache:verify](#temporalcacheverify)
4. [temporalcache:harmonize](#temporalcacheharmonize)
5. [temporalcache:list](#temporalcachelist)
6. [Common Use Cases](#common-use-cases)
7. [Automation & Scheduling](#automation--scheduling)

---

## Command Overview

| Command | Purpose | Destructive | Schedulable |
|---------|---------|-------------|-------------|
| `temporalcache:analyze` | Analyze temporal content and cache statistics | No | No |
| `temporalcache:verify` | Verify database indexes and configuration | No | No |
| `temporalcache:harmonize` | Apply harmonization to temporal fields | **Yes** | Yes |
| `temporalcache:list` | List all temporal content with transitions | No | No |

**Note:** All commands support the `--help` option for detailed usage information.

---

## temporalcache:analyze

Analyzes all temporal content in the TYPO3 system and provides comprehensive statistics about cache behavior, upcoming transitions, and harmonization impact.

### Syntax

```bash
vendor/bin/typo3 temporalcache:analyze [options]
```

### Options

| Option | Short | Type | Default | Description |
|--------|-------|------|---------|-------------|
| `--workspace` | `-w` | int | `0` | Workspace UID (0 = live) |
| `--language` | `-l` | int | `0` | Language UID (0 = default, -1 = all) |
| `--days` | `-d` | int | `30` | Number of days to analyze for transitions |
| `--verbose` | `-v` | flag | - | Show detailed output including configuration |

### Output

The command provides the following information:

1. **Analysis Context**: Workspace, language, time period
2. **Temporal Content Statistics**:
   - Total temporal items (pages + content elements)
   - Distribution by type
   - Distribution by temporal field (start only, end only, both)
3. **Upcoming Transitions**:
   - Total transition count in analysis period
   - Peak transition days with impact levels
   - Next 10 transitions (verbose mode)
4. **Harmonization Impact** (if enabled):
   - Original vs harmonized transition count
   - Cache invalidation reduction percentage
   - Configured time slots and tolerance
5. **Configuration Summary** (verbose mode):
   - Current scoping and timing strategies
   - Harmonization settings

### Examples

```bash
# Basic analysis (default workspace, 30 days)
vendor/bin/typo3 temporalcache:analyze

# Analyze specific workspace with verbose output
vendor/bin/typo3 temporalcache:analyze --workspace=1 --verbose

# Analyze next 60 days across all languages
vendor/bin/typo3 temporalcache:analyze --days=60 --language=-1

# Detailed analysis for production planning
vendor/bin/typo3 temporalcache:analyze --days=90 -v
```

### Exit Codes

- `0`: Analysis completed successfully
- `1`: Error occurred during analysis

### Sample Output

```
Temporal Cache Analysis
=======================

Analysis Context
----------------
+------------------+---------------+
| Parameter        | Value         |
+------------------+---------------+
| Workspace        | Live (0)      |
| Language         | Language 0    |
| Analysis Period  | 30 days       |
| Current Time     | 2025-10-29... |
+------------------+---------------+

Temporal Content Statistics
----------------------------
+---------------------------+-------+
| Metric                    | Count |
+---------------------------+-------+
| Total Temporal Items      | 156   |
|   Pages                   | 23    |
|   Content Elements        | 133   |
| With Start Time Only      | 45    |
| With End Time Only        | 32    |
| With Both Start & End     | 79    |
+---------------------------+-------+

Upcoming Transitions
--------------------
Found 89 transitions

Peak Transition Days:
+------------+--------------+--------+
| Date       | Transitions  | Impact |
+------------+--------------+--------+
| 2025-11-15 | 12          | HIGH   |
| 2025-11-01 | 8           | MEDIUM |
| 2025-11-22 | 6           | MEDIUM |
+------------+--------------+--------+

Harmonization Impact Analysis
------------------------------
+---------------------------+-------+
| Metric                    | Value |
+---------------------------+-------+
| Original Transitions      | 89    |
| After Harmonization       | 58    |
| Reduction                 | 34.8% |
| Cache Invalidations Saved | 31    |
+---------------------------+-------+

[OK] Harmonization reduces cache invalidations by 34.8%!
```

---

## temporalcache:verify

Performs comprehensive verification of the temporal cache system, checking database indexes, extension configuration, and system readiness.

### Syntax

```bash
vendor/bin/typo3 temporalcache:verify [options]
```

### Options

| Option | Short | Type | Description |
|--------|-------|------|-------------|
| `--verbose` | `-v` | flag | Show detailed index and schema information |

### Checks Performed

1. **Database Index Verification**
   - Checks for indexes on `pages.starttime` and `pages.endtime`
   - Checks for indexes on `tt_content.starttime` and `tt_content.endtime`
   - Reports missing indexes that will impact performance

2. **Extension Configuration Validation**
   - Validates scoping strategy (global, per-page, per-content)
   - Validates timing strategy (dynamic, scheduler, hybrid)
   - Reports configuration status

3. **Harmonization Configuration** (if enabled)
   - Validates time slot format (HH:MM or H:MM)
   - Validates tolerance range (0-86400 seconds)
   - Checks auto-round setting

4. **Database Schema Completeness**
   - Verifies all required fields exist
   - Checks workspace and language fields

### Examples

```bash
# Basic verification
vendor/bin/typo3 temporalcache:verify

# Detailed verification with full schema check
vendor/bin/typo3 temporalcache:verify --verbose
```

### Exit Codes

- `0`: All checks passed
- `1`: One or more checks failed

### Sample Output

```
Temporal Cache System Verification
===================================

Database Index Verification
---------------------------
+------------+-------------------+--------+
| Table      | Field(s)          | Status |
+------------+-------------------+--------+
| pages      | starttime         | OK     |
| pages      | endtime           | OK     |
| tt_content | starttime         | OK     |
| tt_content | endtime           | MISSING|
+------------+-------------------+--------+

[WARNING] Missing indexes detected! This will severely impact performance.
          Run "vendor/bin/typo3 extension:setup" to create missing indexes.

Extension Configuration Verification
-------------------------------------
+-------------------+------------+--------+
| Setting           | Value      | Status |
+-------------------+------------+--------+
| Scoping Strategy  | per-page   | VALID  |
| Timing Strategy   | dynamic    | VALID  |
| Harmonization     | Enabled    | OK     |
+-------------------+------------+--------+

Harmonization Configuration Verification
-----------------------------------------
+-------------------+------------------------+--------+
| Setting           | Value                  | Status |
+-------------------+------------------------+--------+
| Time Slots        | 00:00, 06:00, 12:00... | OK     |
| Tolerance         | 3600 seconds (60 min)  | OK     |
| Auto-round        | Enabled                | OK     |
+-------------------+------------------------+--------+

[ERROR] Some verification checks failed. Please review the issues above.
```

### Fixing Issues

**Missing Indexes:**
```bash
vendor/bin/typo3 extension:setup
```

**Invalid Configuration:**
- Go to TYPO3 Backend → Settings → Extension Configuration → temporal_cache
- Fix the reported configuration issues
- Re-run verification

---

## temporalcache:harmonize

Applies harmonization to temporal fields (starttime/endtime) by rounding them to configured time slots. This reduces cache churn and improves cache hit rates.

### Syntax

```bash
vendor/bin/typo3 temporalcache:harmonize [options]
```

### Options

| Option | Short | Type | Default | Description |
|--------|-------|------|---------|-------------|
| `--dry-run` | - | flag | - | Preview changes without modifying database |
| `--workspace` | `-w` | int | `0` | Workspace UID to harmonize |
| `--language` | `-l` | int | `0` | Language UID to harmonize |
| `--table` | `-t` | string | `null` | Limit to specific table (pages or tt_content) |
| `--verbose` | `-v` | flag | - | Show sample changes before applying |

### Safety Features

1. **Dry-run Mode**: Preview changes before applying
2. **Confirmation Prompt**: Asks for confirmation before modifying database
3. **Selective Processing**: Only updates records where harmonization differs
4. **Progress Tracking**: Shows progress bar during updates
5. **Error Handling**: Reports failed updates without stopping

### Examples

```bash
# ALWAYS run dry-run first to preview changes
vendor/bin/typo3 temporalcache:harmonize --dry-run

# Apply harmonization to all temporal content
vendor/bin/typo3 temporalcache:harmonize

# Harmonize only pages with verbose output
vendor/bin/typo3 temporalcache:harmonize --table=pages --verbose

# Harmonize specific workspace
vendor/bin/typo3 temporalcache:harmonize --workspace=1

# Preview harmonization for specific table
vendor/bin/typo3 temporalcache:harmonize --table=tt_content --dry-run -v
```

### Workflow

1. **Run dry-run first:**
   ```bash
   vendor/bin/typo3 temporalcache:harmonize --dry-run --verbose
   ```

2. **Review the output** to understand what will change

3. **Apply changes:**
   ```bash
   vendor/bin/typo3 temporalcache:harmonize
   ```

4. **Confirm when prompted**

### Exit Codes

- `0`: Harmonization completed successfully or dry-run finished
- `1`: Error (harmonization not enabled, invalid parameters, etc.)

### Sample Output

```
Temporal Field Harmonization
=============================

[WARNING] LIVE MODE: Database will be modified

Harmonization Context
---------------------
+-------------------+------------------------+
| Parameter         | Value                  |
+-------------------+------------------------+
| Mode              | Live                   |
| Workspace         | Live (0)               |
| Language          | Language 0             |
| Table Filter      | All tables             |
| Time Slots        | 00:00, 06:00, 12:00... |
| Tolerance         | 3600 seconds           |
+-------------------+------------------------+

Loading Temporal Content
-------------------------
Found 156 temporal records

Applying Harmonization
----------------------
+------------+---------+
| Table      | Changes |
+------------+---------+
| pages      | 12      |
| tt_content | 45      |
| Total      | 57      |
+------------+---------+

Sample Changes (first 10):
+------------+------+-------+------------------+------------------+--------+
| Table      | UID  | Field | Old Time         | New Time         | Shift  |
+------------+------+-------+------------------+------------------+--------+
| pages      | 123  | start | 2025-11-01 00:15 | 2025-11-01 00:00 | -15 min|
| pages      | 124  | end   | 2025-11-15 06:45 | 2025-11-15 06:00 | -45 min|
+------------+------+-------+------------------+------------------+--------+

Proceed with harmonization? (yes/no) [no]: yes

Applying Changes
----------------
57/57 [============================] 100%

+--------+-------+
| Result | Count |
+--------+-------+
| Updated| 57    |
| Failed | 0     |
+--------+-------+

Impact Analysis
---------------
+--------------------------------+-------+
| Metric                         | Value |
+--------------------------------+-------+
| Total Changes                  | 57    |
| Unique Timestamps (Before)     | 57    |
| Unique Timestamps (After)      | 38    |
| Timestamp Reduction            | 33.3% |
| Cache Invalidations Saved      | 19    |
+--------------------------------+-------+

[OK] Harmonization will reduce cache invalidations by 33.3%!
```

### Important Notes

- **ALWAYS backup your database before running**
- **Test on staging environment first**
- **Run with --dry-run before applying changes**
- **Harmonization must be enabled in extension configuration**
- Changes are permanent once applied

---

## temporalcache:list

Lists all temporal content (pages and content elements) with their transition information in various output formats.

### Syntax

```bash
vendor/bin/typo3 temporalcache:list [options]
```

### Options

| Option | Short | Type | Default | Description |
|--------|-------|------|---------|-------------|
| `--table` | `-t` | string | `null` | Filter by table (pages or tt_content) |
| `--workspace` | `-w` | int | `0` | Workspace UID to list |
| `--language` | `-l` | int | `0` | Language UID to list (-1 = all) |
| `--upcoming` | `-u` | flag | - | Show only content with upcoming transitions |
| `--sort` | `-s` | string | `uid` | Sort by field (uid, title, starttime, endtime, table) |
| `--format` | `-f` | string | `table` | Output format (table, json, csv) |
| `--limit` | - | int | `null` | Limit number of results |

### Output Formats

1. **table** (default): Human-readable table format for terminal
2. **json**: Machine-readable JSON for automation/scripting
3. **csv**: CSV format for import into Excel/spreadsheets

### Examples

```bash
# List all temporal content
vendor/bin/typo3 temporalcache:list

# List only pages
vendor/bin/typo3 temporalcache:list --table=pages

# List only upcoming transitions
vendor/bin/typo3 temporalcache:list --upcoming

# Sort by start time
vendor/bin/typo3 temporalcache:list --sort=starttime

# Export to JSON
vendor/bin/typo3 temporalcache:list --format=json > temporal-content.json

# Export to CSV
vendor/bin/typo3 temporalcache:list --format=csv > temporal-content.csv

# List next 10 upcoming transitions
vendor/bin/typo3 temporalcache:list --upcoming --sort=starttime --limit=10

# List pages in workspace 1
vendor/bin/typo3 temporalcache:list --table=pages --workspace=1
```

### Exit Codes

- `0`: Listing completed successfully
- `1`: Error (invalid parameters)

### Sample Output

**Table Format:**
```
Temporal Content List
=====================

Filters
-------
Workspace: 0 | Language: 0 | Total: 156 records

+------------+------+---------------------------+------------------+------------------+--------------------+
| Table      | UID  | Title                     | Start Time       | End Time         | Next Transition    |
+------------+------+---------------------------+------------------+------------------+--------------------+
| pages      | 123  | Summer Campaign           | 2025-06-01 00:00 | 2025-09-01 00:00 | Start in 214 days  |
| tt_content | 456  | Promotion Banner          | -                | 2025-12-31 23:59 | End in 428 days    |
| pages      | 124  | Holiday Special           | 2025-12-15 00:00 | 2026-01-05 00:00 | Start in 412 days  |
+------------+------+---------------------------+------------------+------------------+--------------------+

Total: 156 records
```

**JSON Format:**
```json
[
  {
    "table": "pages",
    "uid": 123,
    "pid": 0,
    "title": "Summer Campaign",
    "starttime": 1717200000,
    "starttime_formatted": "2025-06-01 00:00:00",
    "endtime": 1725148800,
    "endtime_formatted": "2025-09-01 00:00:00",
    "language_uid": 0,
    "workspace_uid": 0,
    "hidden": false,
    "deleted": false
  }
]
```

**CSV Format:**
```csv
Table,UID,PID,Title,StartTime,EndTime,Language,Workspace,Hidden,Deleted
pages,123,0,"Summer Campaign",2025-06-01 00:00:00,2025-09-01 00:00:00,0,0,0,0
tt_content,456,123,"Promotion Banner",,2025-12-31 23:59:00,0,0,0,0
```

---

## Common Use Cases

### 1. Initial System Assessment

When setting up temporal cache for the first time:

```bash
# Step 1: Verify system configuration
vendor/bin/typo3 temporalcache:verify

# Step 2: Analyze current content
vendor/bin/typo3 temporalcache:analyze --verbose

# Step 3: List all temporal content
vendor/bin/typo3 temporalcache:list --sort=starttime
```

### 2. Production Monitoring

Regular production checks:

```bash
# Weekly analysis of upcoming transitions
vendor/bin/typo3 temporalcache:analyze --days=7

# Monthly upcoming transitions report
vendor/bin/typo3 temporalcache:list --upcoming --sort=starttime --limit=20
```

### 3. Harmonization Workflow

Safe harmonization process:

```bash
# Step 1: Preview changes
vendor/bin/typo3 temporalcache:harmonize --dry-run --verbose

# Step 2: Review impact
# (Check output carefully)

# Step 3: Apply to test table first
vendor/bin/typo3 temporalcache:harmonize --table=pages --dry-run

# Step 4: Apply changes
vendor/bin/typo3 temporalcache:harmonize

# Step 5: Verify results
vendor/bin/typo3 temporalcache:analyze
```

### 4. Export for Reporting

Create reports for stakeholders:

```bash
# Export all temporal content to CSV
vendor/bin/typo3 temporalcache:list --format=csv > temporal-content.csv

# Export upcoming transitions to JSON
vendor/bin/typo3 temporalcache:list --upcoming --format=json > upcoming.json

# Generate analysis report
vendor/bin/typo3 temporalcache:analyze --days=90 --verbose > analysis-report.txt
```

### 5. Multi-Workspace Management

Working with multiple workspaces:

```bash
# Analyze live workspace
vendor/bin/typo3 temporalcache:analyze --workspace=0

# Analyze staging workspace
vendor/bin/typo3 temporalcache:analyze --workspace=1

# Compare temporal content
vendor/bin/typo3 temporalcache:list --workspace=0 --format=csv > live.csv
vendor/bin/typo3 temporalcache:list --workspace=1 --format=csv > staging.csv
```

---

## Automation & Scheduling

### Cron Jobs

Example crontab entries for automated monitoring:

```cron
# Daily analysis (runs at 2 AM)
0 2 * * * cd /var/www/html && vendor/bin/typo3 temporalcache:analyze --days=7 > /var/log/temporal-cache-analysis.log 2>&1

# Weekly harmonization (runs Sunday at 3 AM)
0 3 * * 0 cd /var/www/html && vendor/bin/typo3 temporalcache:harmonize > /var/log/temporal-cache-harmonize.log 2>&1

# Monthly verification (runs 1st of month at 1 AM)
0 1 1 * * cd /var/www/html && vendor/bin/typo3 temporalcache:verify > /var/log/temporal-cache-verify.log 2>&1
```

### TYPO3 Scheduler Integration

The `temporalcache:harmonize` command is marked as schedulable and can be integrated into TYPO3's scheduler:

1. Go to **Admin Tools > Scheduler**
2. Create new task: **Execute console commands (scheduler)**
3. Select command: **temporalcache:harmonize**
4. Configure frequency and parameters
5. Save and activate

### Shell Scripts

Example monitoring script:

```bash
#!/bin/bash
# temporal-cache-monitor.sh

LOG_DIR="/var/log/temporal-cache"
DATE=$(date +%Y-%m-%d)

mkdir -p $LOG_DIR

# Run verification
echo "Running verification..."
vendor/bin/typo3 temporalcache:verify > "$LOG_DIR/verify-$DATE.log" 2>&1

if [ $? -ne 0 ]; then
    echo "Verification failed! Check $LOG_DIR/verify-$DATE.log"
    exit 1
fi

# Run analysis
echo "Running analysis..."
vendor/bin/typo3 temporalcache:analyze --days=30 --verbose > "$LOG_DIR/analysis-$DATE.log" 2>&1

# Export upcoming transitions
echo "Exporting upcoming transitions..."
vendor/bin/typo3 temporalcache:list --upcoming --format=csv > "$LOG_DIR/upcoming-$DATE.csv"

echo "Monitoring complete! Logs in $LOG_DIR"
```

---

## Troubleshooting

### Command Not Found

If commands are not available:

```bash
# Clear cache
vendor/bin/typo3 cache:flush

# Rebuild DI container
rm -rf var/cache/*
vendor/bin/typo3 cache:warmup
```

### Permission Errors

Ensure proper file permissions:

```bash
# Set correct ownership
chown -R www-data:www-data .

# Fix permissions
chmod -R 755 vendor/bin/typo3
```

### Database Connection Errors

Verify database credentials and connectivity:

```bash
# Test database connection
vendor/bin/typo3 database:export --help
```

### Harmonization Not Enabled

If harmonization commands fail:

1. Enable harmonization in extension configuration
2. Configure time slots (e.g., "00:00,06:00,12:00,18:00")
3. Set tolerance (e.g., 3600 seconds)
4. Run verify command to check configuration

---

## Best Practices

1. **Always verify before harmonizing**: Run `temporalcache:verify` first
2. **Use dry-run mode**: Preview changes with `--dry-run` before applying
3. **Backup database**: Before running harmonization on production
4. **Monitor regularly**: Schedule weekly analysis
5. **Export reports**: Use JSON/CSV formats for tracking over time
6. **Test on staging**: Apply harmonization to staging first
7. **Document changes**: Keep logs of all harmonization operations
8. **Review peak days**: Plan capacity around high-transition days

---

## Further Reading

- [Extension Configuration](Configuration.md)
- [Harmonization Guide](Harmonization.md)
- [Architecture Documentation](Architecture.md)
- [API Reference](API-Reference.md)
