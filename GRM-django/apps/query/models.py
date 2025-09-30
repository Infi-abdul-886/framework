from django.db import models
from django.conf import settings
import json

class QueryFile(models.Model):
    """Represents a file that contains multiple query templates"""
    file_name = models.CharField(max_length=255, unique=True)
    description = models.TextField(blank=True, null=True)
    created_by = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE, related_name='query_files')
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ['file_name']

    def __str__(self):
        return self.file_name

    def get_query_count(self):
        return self.query_templates.count()

class QueryTemplate(models.Model):
    """Individual query templates within a file"""
    query_file = models.ForeignKey(QueryFile, on_delete=models.CASCADE, related_name='query_templates', null=True)
    name = models.CharField(max_length=255)
    description = models.TextField(blank=True, null=True)
    configuration = models.JSONField()
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        unique_together = ['query_file', 'name']
        ordering = ['name']

    def __str__(self):
        return f"{self.query_file.file_name if self.query_file else 'No File'} - {self.name}"

    def get_full_name(self):
        return f"{self.query_file.file_name if self.query_file else 'No File'}/{self.name}"

# NEW: Through model to define the sequence and data flow of queries in a task
class ScheduledTaskStep(models.Model):
    """Defines a single step in a scheduled query task chain."""
    scheduled_task = models.ForeignKey('ScheduledQueryTask', on_delete=models.CASCADE)
    query_template = models.ForeignKey(QueryTemplate, on_delete=models.CASCADE)
    order = models.PositiveIntegerField(help_text="Execution order of this step (e.g., 1, 2, 3).")

    # Fields for chaining data from the PREVIOUS step to this one
    input_source_column = models.CharField(
        max_length=255, 
        blank=True, 
        null=True, 
        help_text="Column name from the previous step's result to use as input (e.g., 'id')."
    )
    target_placeholder_name = models.CharField(
        max_length=255, 
        blank=True, 
        null=True, 
        help_text="Placeholder in this step's query config to be replaced (e.g., '__USER_IDS__')."
    )

    class Meta:
        ordering = ['order']
        unique_together = ['scheduled_task', 'order']

    def __str__(self):
        return f"Step {self.order}: {self.query_template.name}"


class ScheduledQueryTask(models.Model):
    """Links query templates to scheduled tasks in a specific sequence."""
    task_name = models.CharField(max_length=255, unique=True)
    description = models.TextField(blank=True, null=True)
    
    # MODIFIED: We now use the 'through' model to manage the sequence
    query_templates = models.ManyToManyField(
        QueryTemplate,
        through=ScheduledTaskStep,
        related_name='scheduled_tasks'
    )
    
    cron_expression = models.CharField(max_length=100, help_text="Cron expression (e.g., '0 9 * * *' for daily at 9 AM)")
    email_recipients = models.TextField(help_text="Comma-separated email addresses")
    email_subject = models.CharField(max_length=255, default="Scheduled Query Results")
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    last_run = models.DateTimeField(null=True, blank=True)
    
    OUTPUT_FORMATS = [('json', 'JSON'), ('csv', 'CSV'), ('html', 'HTML Table')]
    output_format = models.CharField(max_length=10, choices=OUTPUT_FORMATS, default='html')

    class Meta:
        ordering = ['task_name']

    def __str__(self):
        return self.task_name


class QueryExecutionLog(models.Model):
    """Log of query executions"""
    scheduled_task = models.ForeignKey(ScheduledQueryTask, on_delete=models.CASCADE, related_name='execution_logs', null=True, blank=True)
    query_template = models.ForeignKey(QueryTemplate, on_delete=models.CASCADE, related_name='execution_logs')
    executed_at = models.DateTimeField(auto_now_add=True)
    execution_time = models.FloatField(help_text="Execution time in seconds")
    row_count = models.IntegerField(default=0)
    status = models.CharField(max_length=20, choices=[('success', 'Success'), ('error', 'Error')], default='success')
    error_message = models.TextField(blank=True, null=True)
    result_data = models.JSONField(null=True, blank=True)

    class Meta:
        ordering = ['-executed_at']

    def __str__(self):
        return f"{self.query_template.get_full_name()} - {self.executed_at}"