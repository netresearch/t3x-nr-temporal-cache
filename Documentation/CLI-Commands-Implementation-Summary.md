# CLI Commands Implementation Summary

This document provides a summary of the CLI commands implementation for the TYPO3 Temporal Cache extension.

## Files Created

### Command Classes

All commands are located in `/Classes/Command/`:

1. **AnalyzeCommand.php** - Temporal content analysis and statistics
2. **VerifyCommand.php** - System verification and health checks
3. **HarmonizeCommand.php** - Temporal field harmonization
4. **ListCommand.php** - Temporal content listing with multiple formats

### Configuration

**Services.yaml** - Updated with command service registrations:
- All commands registered with `console.command` tag
- Proper dependency injection configured
- Schedulable flag set appropriately

### Documentation

1. **CLI-Commands.md** - Comprehensive command documentation
2. **CLI-Commands-QuickReference.md** - Quick reference guide

## Command Overview

| Command | File | Description | Destructive | Schedulable |
|---------|------|-------------|-------------|-------------|
| `temporalcache:analyze` | AnalyzeCommand.php | Analyze temporal content and provide statistics | No | No |
| `temporalcache:verify` | VerifyCommand.php | Verify database indexes and configuration | No | No |
| `temporalcache:harmonize` | HarmonizeCommand.php | Harmonize temporal fields to time slots | Yes | Yes |
| `temporalcache:list` | ListCommand.php | List temporal content with transitions | No | No |

## Command Features

### 1. temporalcache:analyze

**Purpose:** Comprehensive analysis of temporal content and cache behavior

**Key Features:**
- Temporal content distribution statistics
- Upcoming transition analysis with peak detection
- Harmonization impact calculation
- Configurable analysis period (days)
- Workspace and language filtering
- Verbose mode with configuration summary

**Exit Codes:**
- 0: Success
- 1: Error

**Example Usage:**
```bash
vendor/bin/typo3 temporalcache:analyze --days=30 --verbose
vendor/bin/typo3 temporalcache:analyze --workspace=1 --language=0
```

---

### 2. temporalcache:verify

**Purpose:** System verification and health checks

**Key Features:**
- Database index verification (starttime/endtime on pages and tt_content)
- Extension configuration validation
- Harmonization configuration checks (if enabled)
- Database schema completeness verification
- Detailed error reporting with fix recommendations

**Exit Codes:**
- 0: All checks passed
- 1: One or more checks failed

**Example Usage:**
```bash
vendor/bin/typo3 temporalcache:verify
vendor/bin/typo3 temporalcache:verify --verbose
```

**Checks Performed:**
1. Database indexes exist and are optimal
2. Extension configuration is valid
3. Harmonization slots are properly formatted
4. Required database fields exist

---

### 3. temporalcache:harmonize

**Purpose:** Apply harmonization to temporal fields

**Key Features:**
- Dry-run mode for safe preview
- Interactive confirmation before applying changes
- Progress bar during updates
- Workspace and language filtering
- Table-specific filtering (pages or tt_content)
- Impact analysis (before/after comparison)
- Detailed change preview in verbose mode
- Error handling with reporting

**Safety Features:**
1. Dry-run mode (--dry-run)
2. Confirmation prompt
3. Selective processing (only changed records)
4. Progress tracking
5. Error reporting without stopping

**Exit Codes:**
- 0: Success or dry-run completed
- 1: Error (harmonization disabled, invalid params, etc.)

**Example Usage:**
```bash
# Always start with dry-run
vendor/bin/typo3 temporalcache:harmonize --dry-run --verbose

# Apply to all
vendor/bin/typo3 temporalcache:harmonize

# Apply to specific table
vendor/bin/typo3 temporalcache:harmonize --table=pages
```

**Workflow:**
1. Run with --dry-run to preview
2. Review changes and impact
3. Apply changes (with confirmation)
4. Verify results with analyze command

---

### 4. temporalcache:list

**Purpose:** List all temporal content with transition information

**Key Features:**
- Multiple output formats (table, JSON, CSV)
- Flexible sorting (uid, title, starttime, endtime, table)
- Filtering by table, workspace, language
- Upcoming-only filter
- Result limiting
- Next transition calculation with time-until display
- Export capabilities for reporting

**Output Formats:**
1. **table**: Human-readable terminal output with formatting
2. **json**: Machine-readable JSON for automation
3. **csv**: Spreadsheet-compatible CSV export

**Exit Codes:**
- 0: Success
- 1: Error (invalid parameters)

**Example Usage:**
```bash
# List all
vendor/bin/typo3 temporalcache:list

# Filter and sort
vendor/bin/typo3 temporalcache:list --table=pages --sort=starttime

# Export formats
vendor/bin/typo3 temporalcache:list --format=json > data.json
vendor/bin/typo3 temporalcache:list --format=csv > report.csv

# Upcoming transitions only
vendor/bin/typo3 temporalcache:list --upcoming --limit=10
```

## Service Registration (Services.yaml)

All commands are properly registered in `Configuration/Services.yaml`:

```yaml
# Console Commands
Netresearch\TemporalCache\Command\AnalyzeCommand:
  public: false
  arguments:
    $repository: '@Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository'
    $configuration: '@Netresearch\TemporalCache\Configuration\ExtensionConfiguration'
    $harmonizationService: '@Netresearch\TemporalCache\Service\HarmonizationService'
  tags:
    - name: 'console.command'
      command: 'temporalcache:analyze'
      description: 'Analyze temporal content and provide cache statistics'
      schedulable: false

# ... (similar for other commands)
```

### Dependency Injection

All commands use constructor injection following TYPO3 v12/v13 best practices:

- **TemporalContentRepository**: Data access for temporal content
- **ExtensionConfiguration**: Access to extension settings
- **HarmonizationService**: Time slot harmonization logic
- **ConnectionPool**: Direct database access for updates
- **SchemaMigrator/SqlReader**: Schema verification (VerifyCommand)
- **DataHandler**: TYPO3 data handling (HarmonizeCommand)

## Technical Implementation Details

### Command Base Class

All commands extend `Symfony\Component\Console\Command\Command` following Symfony Console component patterns.

### Output Formatting

Commands use `Symfony\Component\Console\Style\SymfonyStyle` for:
- Consistent output formatting
- Tables, sections, and progress bars
- Color-coded status messages
- User-friendly error reporting

### Progress Tracking

HarmonizeCommand includes progress bar for long-running operations:
```php
$progressBar = new ProgressBar($io, count($changes));
$progressBar->setFormat('verbose');
$progressBar->start();
// ... processing
$progressBar->advance();
$progressBar->finish();
```

### Error Handling

All commands implement comprehensive error handling:
- Input validation with clear error messages
- Database error catching and reporting
- Graceful degradation (continue on non-critical errors)
- Proper exit codes for automation

### Output Modes

Commands support multiple verbosity levels:
- Normal: Essential information only
- Verbose (-v): Detailed information including samples
- Very Verbose (-vv): Debug-level information
- Quiet (-q): Suppress all output

## Testing Recommendations

### Manual Testing

```bash
# 1. Test help output
vendor/bin/typo3 temporalcache:analyze --help
vendor/bin/typo3 temporalcache:verify --help
vendor/bin/typo3 temporalcache:harmonize --help
vendor/bin/typo3 temporalcache:list --help

# 2. Test analyze command
vendor/bin/typo3 temporalcache:analyze
vendor/bin/typo3 temporalcache:analyze --days=7 -v
vendor/bin/typo3 temporalcache:analyze --workspace=0 --language=0

# 3. Test verify command
vendor/bin/typo3 temporalcache:verify
vendor/bin/typo3 temporalcache:verify -v

# 4. Test harmonize command (dry-run only)
vendor/bin/typo3 temporalcache:harmonize --dry-run
vendor/bin/typo3 temporalcache:harmonize --dry-run --table=pages -v

# 5. Test list command
vendor/bin/typo3 temporalcache:list
vendor/bin/typo3 temporalcache:list --upcoming
vendor/bin/typo3 temporalcache:list --format=json
vendor/bin/typo3 temporalcache:list --format=csv
vendor/bin/typo3 temporalcache:list --sort=starttime
```

### Automated Testing

Create functional tests for each command:

```php
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeCommandTest extends FunctionalTestCase
{
    public function testAnalyzeCommandExecutesSuccessfully(): void
    {
        $command = $this->get(AnalyzeCommand::class);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertEquals(0, $tester->getStatusCode());
        self::assertStringContainsString('Analysis complete', $tester->getDisplay());
    }
}
```

## Integration Points

### TYPO3 Scheduler

The `temporalcache:harmonize` command is marked as schedulable and can be integrated:

1. Backend → Scheduler
2. Create new task: "Execute console commands"
3. Select: temporalcache:harmonize
4. Configure frequency and options
5. Save and activate

### External Automation

Commands support automation via:
- Cron jobs
- CI/CD pipelines
- Monitoring systems
- Custom scripts

### Export Integration

The list command supports multiple formats for integration:
- **CSV**: Import into Excel, Google Sheets
- **JSON**: Process with scripts, APIs, monitoring tools
- **Table**: Human-readable reports

## Performance Considerations

### Database Queries

Commands are optimized for performance:
- AnalyzeCommand: Uses repository methods with caching
- VerifyCommand: Lightweight schema queries
- HarmonizeCommand: Batch updates with progress tracking
- ListCommand: Single query with in-memory filtering

### Memory Usage

Commands handle large datasets efficiently:
- Generator patterns for large result sets
- Chunked processing where appropriate
- Result limiting options

### Execution Time

Expected execution times (approximate):
- analyze: 1-5 seconds (depends on temporal content count)
- verify: < 1 second
- harmonize: 1-10 seconds (depends on changes)
- list: 1-3 seconds (depends on content count)

## Common Use Cases

### 1. Initial Setup Verification

```bash
vendor/bin/typo3 temporalcache:verify
vendor/bin/typo3 temporalcache:analyze -v
```

### 2. Regular Monitoring

```bash
vendor/bin/typo3 temporalcache:analyze --days=7
vendor/bin/typo3 temporalcache:list --upcoming --limit=20
```

### 3. Harmonization Workflow

```bash
vendor/bin/typo3 temporalcache:harmonize --dry-run -v
vendor/bin/typo3 temporalcache:harmonize
vendor/bin/typo3 temporalcache:analyze
```

### 4. Reporting & Export

```bash
vendor/bin/typo3 temporalcache:list --format=csv > report.csv
vendor/bin/typo3 temporalcache:analyze --days=90 > analysis.txt
```

## Troubleshooting

### Commands Not Found

```bash
# Clear DI cache
vendor/bin/typo3 cache:flush system
rm -rf var/cache/*
```

### Service Injection Errors

Check Services.yaml syntax and service references.

### Database Errors

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 temporalcache:verify
```

## Future Enhancements

Potential improvements for future versions:

1. **Additional Commands**:
   - `temporalcache:optimize` - Automated optimization suggestions
   - `temporalcache:report` - Generate comprehensive reports
   - `temporalcache:cleanup` - Clean up expired temporal content

2. **Enhanced Output**:
   - HTML report generation
   - Chart/graph generation for trends
   - Email notification integration

3. **Advanced Features**:
   - Diff mode for comparing workspaces
   - Batch operations across languages
   - Custom export templates

4. **Performance**:
   - Background processing for large datasets
   - Incremental harmonization
   - Parallel processing support

## Summary

The CLI command implementation provides:

- **4 comprehensive commands** covering all temporal cache operations
- **TYPO3 v12/v13 compliance** with proper service registration
- **Safety features** including dry-run mode and confirmations
- **Multiple output formats** for different use cases
- **Extensive documentation** with examples and best practices
- **Production-ready code** with error handling and validation
- **Automation support** via exit codes and output formats

All commands follow TYPO3 and Symfony best practices, use dependency injection, and provide user-friendly output with SymfonyStyle formatting.

---

**Implementation Status:** Complete ✓

**Files:**
- `/Classes/Command/AnalyzeCommand.php` ✓
- `/Classes/Command/VerifyCommand.php` ✓
- `/Classes/Command/HarmonizeCommand.php` ✓
- `/Classes/Command/ListCommand.php` ✓
- `/Configuration/Services.yaml` (updated) ✓
- `/Documentation/CLI-Commands.md` ✓
- `/Documentation/CLI-Commands-QuickReference.md` ✓

**Testing:** Manual testing recommended before production use
**Documentation:** Complete with examples and troubleshooting
