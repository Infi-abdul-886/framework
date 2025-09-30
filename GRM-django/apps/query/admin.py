from django.contrib import admin
from .models import QueryFile, QueryTemplate, ScheduledQueryTask, QueryExecutionLog, ScheduledTaskStep

class QueryTemplateInline(admin.TabularInline):
    model = QueryTemplate
    extra = 0
    fields = ('name', 'description', 'is_active')
    readonly_fields = ('created_at', 'updated_at')

@admin.register(QueryFile)
class QueryFileAdmin(admin.ModelAdmin):
    list_display = ('file_name', 'description', 'get_query_count', 'created_by', 'is_active', 'created_at')
    inlines = [QueryTemplateInline]

@admin.register(QueryTemplate)
class QueryTemplateAdmin(admin.ModelAdmin):
    list_display = ('name', 'query_file', 'description', 'is_active', 'created_at')
    list_filter = ('is_active', 'created_at', 'query_file')
    search_fields = ('name', 'description', 'query_file__file_name')
    list_select_related = ('query_file',)

# NEW: Inline admin for managing the steps of a task
class ScheduledTaskStepInline(admin.TabularInline):
    model = ScheduledTaskStep
    extra = 1  # Show one empty slot for a new step
    autocomplete_fields = ['query_template'] # Makes selecting templates easier
    
@admin.register(ScheduledQueryTask)
class ScheduledQueryTaskAdmin(admin.ModelAdmin):
    list_display = ('task_name', 'cron_expression', 'output_format', 'is_active', 'last_run')
    list_filter = ('is_active', 'output_format', 'created_at')
    search_fields = ('task_name', 'description')
    readonly_fields = ('last_run', 'created_at', 'updated_at')
    
    # REPLACED filter_horizontal with the new inline manager
    inlines = [ScheduledTaskStepInline]
    
    fieldsets = (
        ('Basic Information', {'fields': ('task_name', 'description', 'is_active')}),
        ('Scheduling', {'fields': ('cron_expression',)}),
        ('Email Settings', {'fields': ('email_recipients', 'email_subject', 'output_format')}),
        ('Timestamps', {'fields': ('last_run', 'created_at', 'updated_at'), 'classes': ('collapse',)}),
    )
    
    actions = ['execute_selected_tasks']
    
    def execute_selected_tasks(self, request, queryset):
        from .tasks import execute_scheduled_query_task
        
        executed_count = 0
        for task in queryset.filter(is_active=True):
            execute_scheduled_query_task.delay(task.id)
            executed_count += 1
        
        self.message_user(request, f"Started execution of {executed_count} tasks.")
    execute_selected_tasks.short_description = "Execute selected tasks"

@admin.register(QueryExecutionLog)
class QueryExecutionLogAdmin(admin.ModelAdmin):
    list_display = ('query_template', 'scheduled_task', 'status', 'row_count', 'execution_time', 'executed_at')
    readonly_fields = [f.name for f in QueryExecutionLog._meta.fields]

    def has_add_permission(self, request):
        return False
    
    def has_change_permission(self, request, obj=None):
        return False