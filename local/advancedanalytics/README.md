# Advanced Analytics Dashboard for Moodle

A comprehensive, high-performance analytics suite for Moodle that provides deep insights into user engagement, department performance, and compliance metrics.

## 🚀 Features (All-in-One Dashboard)
1. **Executive Overview**: High-level KPIs and organizational trends.
2. **Departmental Benchmarking**: Compare performance across different business units.
3. **Learner Watch**: Detailed performance scoring and risk analysis for individual users.
4. **Compliance Monitor**: Automated tracking of mandatory training and certification status.
5. **Manager View**: Role-based access filtering data to specific teams/departments.
6. **AI Insights**: Automated intelligence layer identifying trends and anomalies.
7. **Export System**: Direct generation of CSV and Excel reports.

## 🛠️ Components
- **Unified Dashboard**: `index.php` (Single-file entry for all modules)
- **Analytics Engine**: `classes/analytics_engine.php`
- **Data Sync**: `classes/cron/data_sync.php` (Auto-aggregation)
- **Design System**: `styles/styles.css` (Premium Glassmorphism Design)

## 📦 Installation
1. Upload the `local/advancedanalytics` folder to your Moodle's `local/` directory.
2. Visit Site Administration -> Notifications to trigger the database installation.
3. The following tables will be created:
   - `local_aa_summary`: Historical daily snapshots.
   - `local_aa_user_perf`: Individual user performance cache.
   - `local_aa_dept_stats`: Department-level metrics.
   - `local_aa_compliance`: Mandatory course tracking.
4. Set up a cron job to run the data sync:
   `php admin/cli/cron.php`
   Or run the manual population script:
   `php local/advancedanalytics/cli/populate_tables.php`

## ⚙️ Configuration
Access settings via Site Administration -> Plugins -> Local Plugins -> Advanced Analytics.
- **Data Retention**: Configure how many days of historical data to keep.
- **Sync Frequency**: Cron tasks are recommended to run daily at midnight.

## 🔒 Permissions
- `local/advancedanalytics:viewadmin`: Full access to organizational data.
- `local/advancedanalytics:viewhr`: Access to HR and department data.
- `local/advancedanalytics:viewmanager`: Access to departmental/team data only.

---
**Version**: 1.0.0
**Author**: Sabir Hussain
