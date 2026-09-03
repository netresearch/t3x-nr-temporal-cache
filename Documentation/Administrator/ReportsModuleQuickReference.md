# Reports Module Quick Reference

## Accessing the Report

1. Backend: **Admin Tools > Reports > Status Report**
2. Scroll to: **Temporal Cache** section
3. CLI: `vendor/bin/typo3 temporalcache:verify`

## Status Indicators

- **🟢 Green (OK)**: Everything working properly
- **🟡 Yellow (WARNING)**: Minor issues or recommendations
- **🔴 Red (ERROR)**: Critical issues requiring action

## Quick Actions by Status

### ❌ Database Indexes: ERROR

**Problem:** Missing indexes on starttime/endtime fields

**Impact:** 10-100× slower frontend performance

**Fix:**
1. Navigate to: **Admin Tools > Maintenance > Analyze Database Structure**
2. Apply schema updates
3. Verify in Reports module

### ⚠️ High Transition Volume WARNING

**Problem:** >20 transitions per day causing excessive cache invalidation

**Solutions:**
- Enable harmonization (Extension Configuration)
- Switch to scheduler-based timing strategy
- Use per-content scoping instead of global

### ℹ️ No Temporal Content

**Status:** Extension active but no temporal content found

**Actions:**
- Add starttime/endtime to pages or content elements
- Verify correct workspace (live workspace by default)
- Consider if extension is needed

## Common Checks

### After Installation

```bash
# 1. Verify setup
vendor/bin/typo3 temporalcache:verify

# 2. Create missing indexes
# Backend: Admin Tools > Maintenance > Analyze Database Structure

# 3. Check configuration
# Backend: Admin Tools > Settings > Extension Configuration > temporal_cache
```

### Before Production

- ✅ All database indexes: OK
- ✅ Extension configuration: Valid
- ✅ Temporal content: Statistics available
- ✅ No ERROR status in any section

### Weekly Monitoring

1. Check Reports module for any warnings/errors
2. Review upcoming transitions (next 7 days)
3. Verify harmonization impact (if enabled)

## Performance Optimization Guide

| Scenario | Recommendation |
|----------|---------------|
| High transition volume (>20/day) | Enable harmonization |
| Low transition volume (<5/day) | Harmonization optional |
| Large site (>1000 temporal items) | Use per-content scoping |
| Small site (<100 temporal items) | Global scoping acceptable |
| Mixed workload | Hybrid timing strategy |

## Report Sections Summary

1. **Extension Configuration**
   - Shows: Current strategies, settings
   - Checks: Valid configuration
   - Provides: Optimization recommendations

2. **Database Indexes**
   - Shows: Index status for pages/tt_content
   - Checks: Required indexes exist
   - Critical: Missing indexes = severe performance impact

3. **Temporal Content Statistics**
   - Shows: Count of temporal items, next transition
   - Checks: Content being managed
   - Info: Distribution across pages/content

4. **Harmonization Status**
   - Shows: Configuration, cache reduction %
   - Checks: Impact analysis
   - Recommends: Enable if >10% reduction possible

5. **Upcoming Transitions**
   - Shows: Next 7 days schedule
   - Checks: Transition volume
   - Warns: If >20 transitions/day average

## Troubleshooting

### Report Not Visible

```bash
# Clear all caches
vendor/bin/typo3 cache:flush

# Verify extension is loaded
vendor/bin/typo3 extension:list | grep temporal
```

### Statistics Show Zero

```bash
# List actual temporal content
vendor/bin/typo3 temporalcache:list

# Analyze with details
vendor/bin/typo3 temporalcache:analyze
```

### Index Check Fails

- Check database connection in Install Tool
- Verify user has schema privileges
- Run: `vendor/bin/typo3 extension:setup`

## Integration with Monitoring

### Nagios/Icinga Check

```bash
#!/bin/bash
cd /var/www/html/typo3
vendor/bin/typo3 temporalcache:verify >/dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "OK - Temporal Cache healthy"
    exit 0
else
    echo "CRITICAL - Issues detected"
    exit 2
fi
```

### Cron Monitoring

```bash
# Daily health check with email on failure
0 8 * * * cd /path/to/typo3 && vendor/bin/typo3 temporalcache:verify || \
    mail -s "TYPO3 Temporal Cache Alert" admin@example.com
```

## Related Commands

```bash
# Comprehensive analysis
vendor/bin/typo3 temporalcache:analyze

# List all temporal content
vendor/bin/typo3 temporalcache:list

# Verify configuration and indexes
vendor/bin/typo3 temporalcache:verify --verbose

# Apply harmonization
vendor/bin/typo3 temporalcache:harmonize --dry-run
```

## Support Resources

- Full Documentation: See `ReportsModule.rst`
- Configuration Guide: `Configuration.rst`
- Performance Tuning: `Performance-Considerations.rst`
- CLI Commands: `CLI-Commands.md`
