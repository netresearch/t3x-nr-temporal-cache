<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Test Fixtures

This directory contains CSV fixtures for functional tests.

## Format

CSV files follow TYPO3 testing framework conventions:
- First row: Column names (database field names)
- Subsequent rows: Test data
- Special values:
  - `FUTURE_TIME`: Replaced with future timestamp during test
  - `PAST_TIME`: Replaced with past timestamp during test

## Files

### pages.csv
Test pages with various temporal configurations:
- Root page (no restrictions)
- Public page (always visible)
- Scheduled page (future starttime)
- Expiring page (future endtime)
- Hidden page (hidden flag)

### tt_content.csv
Test content elements with temporal settings:
- Regular content (always visible)
- Scheduled content (future starttime)
- Expiring content (future endtime)
- Hidden content (hidden flag)

## Usage

Import fixtures in test setup:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
    $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
}
```

## Dynamic Data

For tests requiring specific timestamps, insert data programmatically:

```php
$connection->insert('pages', [
    'starttime' => time() + 3600, // 1 hour from now
    // ... other fields
]);
```
