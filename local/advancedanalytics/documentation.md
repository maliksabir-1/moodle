# Advanced Analytics Plugin: 13. Deployment & Documentation

This document provides comprehensive guidance for installing, configuring, and managing the Advanced Analytics plugin for Moodle.

---

## 1. Installation Package
The plugin is structured as a standard Moodle `local` plugin.
- **Path:** `/local/advancedanalytics/`
- **Dependencies:** 
  - Moodle 4.x or higher
  - PHP 7.4 or higher
  - MySQL/MariaDB with JSON support (recommended)
  - `logstore_standard` enabled in Moodle

---

## 2. Installation Guide
1. **Upload:** Copy the `advancedanalytics` folder into your Moodle site's `/local/` directory.
2. **Upgrade:** Log in as an Administrator and navigate to **Site Administration > Notifications**. Follow the prompts to install the plugin and update the database schema.
3. **Task Rescheduling:** The plugin relies on a scheduled task (`\local_advancedanalytics\task\aggregate_analytics`) to process data. This task is set to run hourly by default.
4. **Purge Caches:** It is recommended to purge all caches after installation to ensure the new theme navigation links appear.

---

## 3. Admin Configuration Guide
The plugin settings can be found at **Site Administration > Plugins > Local Plugins > Advanced Analytics**.

### Key Configurations:
- **Data Retention:** Configure how many months of historical log data should be used for trend analysis.
- **Mandatory Courses:** Use the "Compliance Monitor" within the dashboard to mark specific courses as mandatory for organizational compliance tracking.
- **AI Engine API:** (Optional) If enabled, configure the OpenAI or similar endpoint for automated learner insights.

---

## 4. Plugin Documentation
### Features:
- **Executive Dashboard:** High-level KPIs for administrators and HR managers.
- **Learner Performance:** Detailed engagement scores and risk level tracking (Low/Medium/High).
- **Compliance Monitor:** Real-time tracking of mandatory training completion across the organization.
- **Department Analytics:** Benchmarking and comparison between organizational departments.
- **Automated Reporting:** Schedule daily, weekly, or monthly PDF/Excel reports to be sent via email.

### Technical Architecture:
- **Observers:** Listen to `user_loggedin` and `course_completed` events to trigger immediate score recalculations.
- **Analytics Engine:** A robust class for heavy SQL processing, optimized for performance using temporary tables and background tasks.
- **AJAX UI:** Single-page application feel using modern JavaScript for navigation and filtering.

---

## 5. Versioning & Release Notes
### v1.1.0 (2026-06-15)
- **Feature:** Integrated "Advanced Analytics" directly into the clean theme navbar.
- **Fix:** Resolved session mutation errors during login event processing.
- **Fix:** Fixed event observer callbacks for namespaced classes.
- **UI:** Implemented AJAX-based filtering and tab switching for zero-reload navigation.
- **UI:** Added detailed Compliance Audit Log view with search and department filtering.

### v1.0.0 (Initial Release)
- Core analytics engine and KPI dashboards.
- Scheduled task for background data aggregation.
- Basic CSV export functionality.
