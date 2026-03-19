# Qwizard Archive (qwarchive)

## Overview

`Qwizard Archive` is a Drupal 9/10 module that provides **archival and restore workflows for inactive users' data** (Qwizard results). It identifies users who have not logged in for a configurable number of days, archives their data to file storage, and allows users to request restoration of archived data via a queued process.

The module includes:
- Detection of inactive users
- Manual and automated data archival
- File-based archive storage
- Administrative listing of archived records
- User-initiated restore requests
- Queue-based restore processing
- Configuration for inactivity threshold and automated archival
- Configuration for json file storage

The module is designed to work with the **Qwizard** ecosystem and depends on the `qwizard` module.

---

## Key Features

- **Inactive user detection** based on last login time (N days configurable)
- **Manual archive creation** via admin UI
- **Archive listing** for administrators
- **User notification** when their data is archived
- **Restore request system** (queued)
- **Pluggable storage manager** for archive files
- **Separation of concerns** via manager and record classes

---

## Permissions

The module defines a single administrative permission:

- **Administer qwizard archive**
  - Required to:
    - View inactive users
    - View archived records
    - Create archives manually
    - View and process restore requests
    - Configure archive settings

Assign this permission only to trusted administrative roles.

---

## Administrative Routes & UI

All admin pages are grouped under **Qwizard > Archive**.

### 1. Inactive Users

**Path**: `/admin/qwizard/qw-archive/users`

- Lists users who have not logged in for the configured number of days
- Allows administrators to:
  - Review affected users
  - Manually trigger archive creation

---

### 2. Archived Records

**Path**: `/admin/qwizard/qw-archive/list`

- Displays all archived records
- Shows metadata such as:
  - User
  - Archive date
  - Archive file
  - Status

---

### 3. Restore Requests

**Path**: `/admin/qwizard/qw-archive/restore-requests`

- Lists restore requests submitted by users
- Administrators can:
  - Review requests
  - Trigger restore processing

---

## User-Facing Workflow

### Archive Notification

When a user logs in and their data is already archived:

- A message is shown informing them that:
  - Their data has been archived due to inactivity
  - They can request restoration from the UI

### Restore Request

- The user submits a restore request
- The request is:
  - Stored as a record
  - Added to a **Drupal Queue** for processing

---

## Archive & Restore Workflow

### 1. Automated Archival

When Automated Archival is enabled from configuration, the archival of inactive users will be scheduled via queue.
The bash script can be added to execute drush commands for archival.
Drush commands that can be used are:

- `drush cron` - To trigger the cron hook which schedules the archival of users based on configured cron batch size.
- `drush queue:run qwarchive_process` - Executes the queue which archives the user data.

### 2. Manual Archival

Archival can be triggered manually:

- By an administrator
- (Optionally) via scheduled or batch operations

---

### 3. Storage Management

Responsibilities:
- File path generation
- Writing archive files
- Reading archives during restore

Storage location is configurable via module settings.

---

### 4. Restore Processing

1. Restore request is dequeued
2. Archive file is read from storage
3. Data is mapped back to entities/tables
4. Restore status is updated

The restore process is **queue-based** to avoid long request execution times.

---

## Queues

The module uses Drupal Queue API for:

- User data archival
- Restore request processing

This ensures:
- Better performance
- Safe retry handling
- Scalability for large datasets

Queues can also be processed using:

```bash
drush queue:run
```

---

## Configuration

Configuration options typically include:

- **Inactivity threshold (N days)**
- **Enable Automated Archival**
- **Cron Batch Size**
- **Archive storage location**

Configuration is exposed via forms under the Qwizard Archive admin section.

---

## Safety & Best Practices

- **Do not enable on production without review**
- Always:
  - Test archive & restore on staging
  - Verify file permissions
  - Confirm data integrity after restore

- Keep regular backups
- Limit access using permissions

---

## Maintainer Notes

This module is tightly coupled with Qwizard data structures. Any schema or storage changes in Qwizard should be reviewed for archive/restore compatibility.
