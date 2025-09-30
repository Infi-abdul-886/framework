# GRM Alerts Framework

A comprehensive Django-based framework for modernizing legacy PHP cron jobs into a UI-driven, schedulable alert system.

## Architecture Overview

The framework follows a **Query-Processor-Workflow** architecture:

1. **Query Layer**: Uses the existing `query_app` to fetch data via `query_builder_api`
2. **Processor Layer**: Contains business logic classes for processing data
3. **Workflow Layer**: Orchestrates queries and processors via the `workflow_app`

## Key Features

- **No Django ORM for Data Fetching**: All SELECT operations use the query builder
- **UI-Driven Configuration**: Everything configurable through Django Admin
- **Reusable Framework**: Designed to handle 80+ legacy cron jobs
- **Categorized Processors**: Organized for easy discovery in UI
- **Comprehensive Logging**: Detailed execution logs and statistics
- **Email Templates**: Professional HTML email templates
- **Scheduling Integration**: Uses django-celery-beat for scheduling

## Implementation Guide

### Step 1: Set Up Query Templates

1. Go to Django Admin → Query → Query Files
2. Create a new file called "Expiry Alerts"
3. Use the JSON configurations from `query_templates.json` to create templates:

#### Fare Expiry Template
- **Name**: "Fare Expiry Query"
- **Configuration**: Copy from `fare_expiry_query` in `query_templates.json`
- **Note**: Replace `__CURRENT_TIME__` and `__EXPIRY_TIME__` with actual datetime values

#### Payment Expiry Template
- **Name**: "Payment Expiry Query" 
- **Configuration**: Copy from `payment_expiry_query` in `query_templates.json`

#### Passenger Expiry Template
- **Name**: "Passenger Expiry Query"
- **Configuration**: Copy from `passenger_expiry_query` in `query_templates.json`

#### Penalty Expiry Template
- **Name**: "Penalty Expiry Query"
- **Configuration**: Copy from `penalty_expiry_query` in `query_templates.json`

### Step 2: Set Up Alert Workflows

1. Go to Django Admin → Grm Alerts → Alert Workflows
2. Create workflows for each alert type:

#### Fare Expiry Workflow
- **Name**: "Fare Expiry Alerts"
- **Alert Type**: "FARE"
- **Query Templates**: `[1]` (ID of fare expiry template)
- **Processor Config**:
```json
{
  "processor_class": "apps.grm_alerts.processors.expiry_processors.FareExpiryProcessor",
  "alert_type": "FARE",
  "email_template": "fare_expiry_alert.html",
  "hours_ahead": 24
}
```
- **Cron Expression**: `0 */6 * * *` (every 6 hours)

### Step 3: Test and Activate

1. Set workflow status to "Active"
2. Enable scheduling: `is_scheduled = True`
3. Test execution using the admin action "Execute selected workflows"
4. Monitor execution logs in Alert Executions

### Step 4: Disable Legacy Cron

Once the new system is working:
1. Comment out the old PHP cron job in crontab
2. Monitor the new system for a few days
3. Remove the legacy PHP files

## Processor Registry

Processors are organized in categories for UI discovery:

- `alerts::expiry::*` - Notification processors
- `services::expiry::*` - Status update processors  
- `workflows::expiry::*` - Combined processors

## Email Templates

Professional HTML email templates are provided:
- `fare_expiry_alert.html`
- `payment_expiry_alert.html`
- `passenger_expiry_alert.html`
- `penalty_expiry_alert.html`

## Management Commands

- `setup_expiry_workflows` - Set up default workflows
- `migrate_legacy_cron` - Migrate from legacy PHP cron jobs

## Monitoring

- Django Admin interface for workflow management
- Detailed execution logs and statistics
- Email delivery tracking
- Performance metrics

## Extending the Framework

To add new alert types:

1. Create processor class in `processors/`
2. Add to `PROCESSOR_REGISTRY`
3. Create email template
4. Set up query templates
5. Configure workflow in admin

This framework provides a solid foundation for modernizing all legacy cron jobs while maintaining the existing database structure and business logic.