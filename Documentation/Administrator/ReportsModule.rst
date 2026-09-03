.. include:: /Includes.rst.txt

.. _reports-module:

====================
TYPO3 Reports module
====================

.. _reports-module-status:

System status report
====================

The Temporal Cache extension contributes five status entries to TYPO3's built-in Reports module.

Accessing the report
--------------------

#. Log in to the TYPO3 backend as an administrator
#. Navigate to: **Admin Tools > Reports > Status Report**
#. Scroll to the **Temporal Cache** section

The report displays in the standard TYPO3 Reports interface with color-coded
status indicators:

- **Green (OK)**: Everything working properly, no action required
- **Blue (INFO)**: Informational, no action required
- **Yellow (WARNING)**: Non-critical issues or optimization recommendations
- **Red (ERROR)**: Critical issues requiring immediate attention

.. _reports-module-sections:

Report sections
===============

Extension Configuration
-----------------------

**What it shows:**

- Current scoping strategy (global, per-page, per-content)
- Current timing strategy (dynamic, scheduler, hybrid)
- Harmonization status (enabled/disabled)
- Reference index usage
- Recommendations when global scoping or dynamic timing is active

**Status levels:**

- **OK**: Both strategy values are valid; any recommendations appear in the message text
- **ERROR**: The scoping or timing strategy holds a value the extension does not know

**Actions:**

If you see an error or warning:

#. Navigate to: **Admin Tools > Settings > Extension Configuration**
#. Locate **temporal_cache** in the list
#. Review and adjust the settings based on recommendations
#. Save changes

Database Indexes
----------------

**What it shows:**

- Whether an index led by ``starttime`` and an index led by ``endtime`` exist on ``pages`` and ``tt_content``
- The names of the missing indexes, if any

**Status levels:**

- **OK**: All required indexes are present
- **ERROR**: Missing indexes detected, or the index list could not be read

**Actions:**

If indexes are missing:

#. Navigate to: **Admin Tools > Maintenance > Analyze Database Structure**
#. Review the proposed changes
#. Apply the schema updates to create missing indexes
#. Return to the Reports module to verify indexes are now present

The indexes ship with the extension in :file:`ext_tables.sql`; the schema update creates them.

**Performance impact:**

Without them, every temporal content lookup is a full table scan instead of an index lookup.

Temporal Content Statistics
---------------------------

**What it shows:**

- Total number of pages and content elements with temporal fields
- Distribution of starttime, endtime, and combined usage
- Next upcoming transition time and the time remaining until it

**Status levels:**

- **OK**: Temporal content found and being managed
- **WARNING**: No temporal content found (extension is active but unused)
- **ERROR**: Failed to retrieve statistics

**Understanding the data:**

- **Total Items**: All pages and content elements with starttime or endtime set
- **Pages**: Number of temporal pages (affects menu visibility)
- **Content Elements**: Number of temporal content elements
- **With Start Date Only**: Content that becomes visible at a specific time
- **With End Date Only**: Content that becomes hidden at a specific time
- **With Both Dates**: Content visible within a specific time window

Harmonization
-------------

**What it shows:**

When harmonization is **disabled**:

- Information about harmonization benefits
- Recommendation to enable it if you have more than 10 transitions per day

When harmonization is **enabled**:

- Current time slot configuration
- Tolerance setting
- Auto-round on save status
- The reduction harmonization would achieve on the current content, as a percentage

**Status levels:**

- **INFO**: Harmonization is disabled
- **OK**: Harmonization is enabled

The measured reduction changes the message, not the severity: above 30 percent it is called significant, above
10 percent moderate, and below that the message suggests adjusting slots or tolerance.

**Understanding cache reduction:**

Harmonization reduces cache churn by rounding transition times to predefined slots.
For example:

**Without harmonization** (3 separate cache invalidations):

- Content A: 00:05
- Content B: 00:15
- Content C: 00:45

**With harmonization** to 00:00 slot (1 cache invalidation):

- Content A: 00:00
- Content B: 00:00
- Content C: 00:00

**When to enable:**

- You have more than 10 transitions per day — the threshold the report itself recommends at
- You want to reduce cache invalidation frequency

**When to disable:**

- You have few temporal items and few transitions
- Exact timing of content visibility is critical
- The reduction reported for your content is low; the report calls it low below 10 percent

Upcoming Transitions
--------------------

**What it shows:**

- Total transitions scheduled in the next 7 days
- Number of days carrying transitions, and a breakdown of the first five of them
- A high-volume note when the 7-day average exceeds 20 transitions per day

**Status levels:**

- **OK**: Normal transition volume, and also when no transition is scheduled at all
- **WARNING**: More than 20 transitions per day on average across the next 7 days
- **ERROR**: The transitions could not be read

**Understanding transition impact:**

Each transition can trigger cache invalidation depending on your scoping strategy:

- **Global scoping**: All page caches invalidated on every transition
- **Per-page scoping**: Only affected pages invalidated
- **Per-content scoping**: Only pages containing affected content invalidated

**High volume recommendations:**

If you see a high transition volume warning (>20 per day):

#. Consider enabling harmonization to group transitions
#. Evaluate if scheduler-based timing would be more efficient
#. Review if per-content scoping can reduce invalidation scope

.. _reports-module-scenarios:

Common scenarios
================

Scenario 1: Extension Just Installed
-------------------------------------

**Expected status:**

- Extension Configuration: OK (default settings)
- Database Indexes: ERROR (before the database schema update is applied)
- Temporal Content: WARNING (no content found)
- Harmonization: INFO (disabled by default)
- Upcoming Transitions: OK (none scheduled)

**Actions:**

#. Run database schema update to create indexes
#. Start adding temporal content (pages/content with starttime/endtime)
#. Return to Reports module to verify system status

Scenario 2: Production Site with Temporal Content
--------------------------------------------------

**Expected status:**

- Extension Configuration: OK
- Database Indexes: OK
- Temporal Content: OK (showing statistics)
- Harmonization: OK or INFO (depending on configuration)
- Upcoming Transitions: OK (showing schedule)

**Actions:**

- Monitor the report periodically (weekly recommended)
- Review harmonization recommendations if transition volume is high
- Check for configuration optimization suggestions

Scenario 3: Performance Issues Detected
----------------------------------------

**Symptoms in report:**

- Database Indexes: ERROR (missing indexes)
- Upcoming Transitions: WARNING (high volume)
- Harmonization: INFO (disabled)

**Resolution steps:**

#. **Immediate**: Create missing database indexes
#. **Short-term**: Enable harmonization to reduce cache churn
#. **Long-term**: Consider per-content scoping if using global scoping

Scenario 4: No Temporal Content Found
--------------------------------------

**Status:**

- Temporal Content: WARNING
- Upcoming Transitions: OK

**Possible causes:**

#. No pages or content elements have starttime/endtime set
#. Content exists but is in a different workspace
#. Content is in a different language

**Actions:**

#. Verify temporal content exists in the backend
#. Check if you're viewing the correct workspace (report shows live workspace by default)
#. If no temporal content exists, consider if the extension is needed

.. _reports-module-automation:

Automation and monitoring
=========================

CLI command alternative
-----------------------

For automation and monitoring systems, use the CLI verify command:

.. code-block:: bash
   :caption: Verification on the command line

   # Quick verification (exit code 0 = OK, 1 = issues)
   vendor/bin/typo3 temporalcache:verify

   # Verbose output for logs
   vendor/bin/typo3 temporalcache:verify --verbose

The two overlap but are not identical.
Both check the database indexes and the strategy values.
Only ``temporalcache:verify`` validates the harmonization slot format, the tolerance range and the presence of
the required table columns.
Only the Reports module shows content statistics, the next transition and the upcoming transition volume.
See :ref:`cli-verify` for the full list of checks.

Integration with monitoring systems
-----------------------------------

The verify command can be integrated with monitoring tools:

**Nagios/Icinga:**

.. code-block:: bash

   #!/bin/bash
   # /usr/local/nagios/libexec/check_typo3_temporal_cache.sh

   cd /var/www/html/typo3
   vendor/bin/typo3 temporalcache:verify >/dev/null 2>&1

   if [ $? -eq 0 ]; then
       echo "OK - Temporal Cache system healthy"
       exit 0
   else
       echo "CRITICAL - Temporal Cache system issues detected"
       exit 2
   fi

**Cron-based monitoring:**

.. code-block:: bash

   # Check daily and email on failure
   0 8 * * * cd /var/www/html/typo3 && vendor/bin/typo3 temporalcache:verify || mail -s "TYPO3 Temporal Cache Issues" admin@example.com < /dev/null

.. _reports-module-troubleshooting:

Troubleshooting
===============

Report Not Visible
------------------

**Symptom:** Temporal Cache section does not appear in Reports module

**Causes:**

#. Extension not installed or activated
#. Cache not cleared after installation
#. Missing service registration

**Solutions:**

#. Verify extension is installed: **Admin Tools > Extensions**
#. Clear all caches: **Admin Tools > Maintenance > Flush Cache**
#. Check if Services.yaml is properly loaded (check system log)

Database Index Check Fails
---------------------------

**Symptom:** Cannot verify indexes, error message displayed

**Causes:**

#. Database connection issues
#. Insufficient database permissions
#. Missing database tables

**Solutions:**

#. Check database connection in Install Tool
#. Verify database user has SELECT privileges on schema tables
#. Run database schema update to ensure tables exist

Statistics Show Zero Items
---------------------------

**Symptom:** Report shows 0 temporal items but content exists

**Causes:**

#. Content is in a workspace (report shows live workspace)
#. Content has been deleted but not purged
#. starttime/endtime fields are set to 0 (not set)

**Solutions:**

#. Use :ref:`temporalcache:list <cli-list>` to verify content: ``vendor/bin/typo3 temporalcache:list``
#. Check workspace settings in the backend
#. Verify starttime/endtime fields have actual timestamps (not 0)

.. _reports-module-best-practices:

Best practices
==============

Regular Monitoring
------------------

- **Weekly**: Review the Reports module status
- **Monthly**: Analyze transition patterns and harmonization impact
- **Quarterly**: Review configuration and optimize based on usage patterns

Before Major Events
-------------------

Before high-traffic periods or major content updates:

#. Verify all database indexes are present
#. Review upcoming transitions schedule
#. Confirm harmonization settings are optimal
#. Test cache invalidation is working correctly

After Configuration Changes
---------------------------

After modifying extension settings:

#. Check Reports module to verify configuration is valid
#. Review recommendations for new configuration
#. Test with sample temporal content
#. Monitor frontend performance

Database Maintenance
--------------------

After database updates or migrations:

#. Verify database indexes still exist
#. Run verify command to ensure schema is complete
#. Check for any database-related errors in Reports module

.. _reports-module-optimization:

Performance optimization
========================

Based on Reports Module Data
----------------------------

**High transition volume — the report warns above 20 per day:**

- Enable harmonization
- Consider scheduler-based timing strategy
- Use per-content scoping instead of global

**Low transition volume:**

- Harmonization may not provide significant benefit
- Dynamic timing strategy is efficient
- Global scoping is acceptable for small sites

**Many temporal items — the report suggests moving away from dynamic timing above 100:**

- Use per-content scoping for minimal cache invalidation
- Enable reference index usage
- Consider scheduler-based timing to avoid per-request calculations

**Mixed workload:**

- Use hybrid timing strategy (dynamic for pages, scheduler for content)
- Enable harmonization with appropriate time slots
- Monitor cache reduction percentage

.. _reports-module-related:

Related documentation
=====================

- :ref:`configuration`: Detailed configuration options
- :ref:`command-line`: The ``temporalcache:analyze``, ``temporalcache:harmonize``, ``temporalcache:list`` and ``temporalcache:verify`` commands
- :ref:`backend-module`: The same data in a backend module
- :ref:`performance-considerations`: Performance tuning guide
- :ref:`configuration-troubleshooting`: General troubleshooting guide
