from django.db import models
from django.conf import settings
import uuid
import json

class AlertWorkflow(models.Model):
    """
    Represents an alert workflow that can be scheduled and executed
    """
    STATUS_CHOICES = [
        ('active', 'Active'),
        ('inactive', 'Inactive'),
        ('draft', 'Draft'),
    ]
    
    id = models.UUIDField(primary_key=True, default=uuid.uuid4)
    name = models.CharField(max_length=255)
    description = models.TextField(blank=True)
    alert_type = models.CharField(max_length=50)  # FARE, PAYMENT, PASSENGER, PENALTY
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='draft')
    
    # Workflow configuration
    query_templates = models.JSONField(default=list, help_text="List of query template IDs to execute")
    processor_config = models.JSONField(default=dict, help_text="Processor configuration")
    
    # Scheduling
    cron_expression = models.CharField(max_length=100, blank=True)
    is_scheduled = models.BooleanField(default=False)
    
    # Metadata
    created_by = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.CASCADE)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    last_executed_at = models.DateTimeField(null=True, blank=True)
    
    class Meta:
        ordering = ['-updated_at']
    
    def __str__(self):
        return f"{self.name} ({self.alert_type})"

class AlertExecution(models.Model):
    """
    Tracks individual executions of alert workflows
    """
    STATUS_CHOICES = [
        ('queued', 'Queued'),
        ('running', 'Running'),
        ('success', 'Success'),
        ('failed', 'Failed'),
        ('cancelled', 'Cancelled'),
    ]
    
    id = models.UUIDField(primary_key=True, default=uuid.uuid4)
    workflow = models.ForeignKey(AlertWorkflow, on_delete=models.CASCADE, related_name='executions')
    
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='queued')
    started_at = models.DateTimeField(auto_now_add=True)
    finished_at = models.DateTimeField(null=True, blank=True)
    duration_seconds = models.FloatField(null=True, blank=True)
    
    # Execution data
    input_data = models.JSONField(default=dict, blank=True)
    output_data = models.JSONField(default=dict, blank=True)
    error_message = models.TextField(blank=True)
    
    # Statistics
    records_processed = models.IntegerField(default=0)
    emails_sent = models.IntegerField(default=0)
    
    class Meta:
        ordering = ['-started_at']
    
    def __str__(self):
        return f"{self.workflow.name} - {self.status} ({self.started_at})"

class AlertLog(models.Model):
    """
    Detailed logs for alert executions
    """
    LEVEL_CHOICES = [
        ('info', 'Info'),
        ('warning', 'Warning'),
        ('error', 'Error'),
    ]
    
    id = models.UUIDField(primary_key=True, default=uuid.uuid4)
    execution = models.ForeignKey(AlertExecution, on_delete=models.CASCADE, related_name='logs')
    
    level = models.CharField(max_length=10, choices=LEVEL_CHOICES, default='info')
    message = models.TextField()
    step = models.CharField(max_length=100, blank=True)  # query, processor, email, etc.
    timestamp = models.DateTimeField(auto_now_add=True)
    
    # Additional context
    context_data = models.JSONField(default=dict, blank=True)
    
    class Meta:
        ordering = ['timestamp']
    
    def __str__(self):
        return f"{self.level.upper()}: {self.message[:50]}"