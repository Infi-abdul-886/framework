from django.contrib import admin
from django.utils.html import format_html
from django.urls import reverse
from django.utils.safestring import mark_safe
import json

from .models import AlertWorkflow, AlertExecution, AlertLog

class AlertLogInline(admin.TabularInline):
    model = AlertLog
    extra = 0
    readonly_fields = ['level', 'message', 'step', 'timestamp', 'context_data']
    fields = ['timestamp', 'level', 'step', 'message']
    
    def has_add_permission(self, request, obj=None):
        return False

@admin.register(AlertWorkflow)
class AlertWorkflowAdmin(admin.ModelAdmin):
    list_display = ['name', 'alert_type', 'status', 'is_scheduled', 'last_executed_at', 'created_at']
    list_filter = ['alert_type', 'status', 'is_scheduled', 'created_at']
    search_fields = ['name', 'description']
    readonly_fields = ['created_at', 'updated_at', 'last_executed_at']
    
    fieldsets = (
        ('Basic Information', {
            'fields': ('name', 'description', 'alert_type', 'status')
        }),
        ('Configuration', {
            'fields': ('query_templates', 'processor_config'),
            'classes': ('collapse',)
        }),
        ('Scheduling', {
            'fields': ('is_scheduled', 'cron_expression'),
            'classes': ('collapse',)
        }),
        ('Metadata', {
            'fields': ('created_by', 'created_at', 'updated_at', 'last_executed_at'),
            'classes': ('collapse',)
        })
    )
    
    actions = ['execute_selected_workflows']
    
    def execute_selected_workflows(self, request, queryset):
        from .tasks import execute_alert_workflow
        
        executed_count = 0
        for workflow in queryset.filter(status='active'):
            execute_alert_workflow.delay(str(workflow.id))
            executed_count += 1
        
        self.message_user(request, f"Started execution of {executed_count} alert workflows.")
    execute_selected_workflows.short_description = "Execute selected workflows"

@admin.register(AlertExecution)
class AlertExecutionAdmin(admin.ModelAdmin):
    list_display = ['workflow', 'status', 'records_processed', 'emails_sent', 'duration_display', 'started_at']
    list_filter = ['status', 'started_at', 'workflow__alert_type']
    search_fields = ['workflow__name']
    readonly_fields = ['started_at', 'finished_at', 'duration_seconds']
    inlines = [AlertLogInline]
    
    fieldsets = (
        ('Execution Details', {
            'fields': ('workflow', 'status', 'records_processed', 'emails_sent')
        }),
        ('Timing', {
            'fields': ('started_at', 'finished_at', 'duration_seconds')
        }),
        ('Data', {
            'fields': ('input_data', 'output_data'),
            'classes': ('collapse',)
        }),
        ('Errors', {
            'fields': ('error_message',),
            'classes': ('collapse',)
        })
    )
    
    def duration_display(self, obj):
        if obj.duration_seconds:
            return f"{obj.duration_seconds:.2f}s"
        return "-"
    duration_display.short_description = 'Duration'

@admin.register(AlertLog)
class AlertLogAdmin(admin.ModelAdmin):
    list_display = ['execution', 'level', 'step', 'message_preview', 'timestamp']
    list_filter = ['level', 'step', 'timestamp']
    search_fields = ['message', 'execution__workflow__name']
    readonly_fields = ['timestamp']
    
    def message_preview(self, obj):
        return obj.message[:100] + "..." if len(obj.message) > 100 else obj.message
    message_preview.short_description = 'Message'