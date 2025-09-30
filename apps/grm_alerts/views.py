from django.shortcuts import render, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.core.paginator import Paginator
from django.db.models import Count, Avg
from django.utils import timezone
import json

from .models import AlertWorkflow, AlertExecution, AlertLog
from .tasks import execute_alert_workflow

@login_required
def dashboard_view(request):
    """Dashboard for GRM alerts system"""
    
    # Get statistics
    total_workflows = AlertWorkflow.objects.count()
    active_workflows = AlertWorkflow.objects.filter(status='active').count()
    
    total_executions = AlertExecution.objects.count()
    successful_executions = AlertExecution.objects.filter(status='success').count()
    failed_executions = AlertExecution.objects.filter(status='failed').count()
    
    success_rate = round((successful_executions / total_executions * 100) if total_executions > 0 else 0, 1)
    
    # Recent activity
    recent_executions = AlertExecution.objects.select_related('workflow').order_by('-started_at')[:10]
    recent_workflows = AlertWorkflow.objects.order_by('-updated_at')[:10]
    
    context = {
        'total_workflows': total_workflows,
        'active_workflows': active_workflows,
        'total_executions': total_executions,
        'successful_executions': successful_executions,
        'failed_executions': failed_executions,
        'success_rate': success_rate,
        'recent_executions': recent_executions,
        'recent_workflows': recent_workflows,
    }
    
    return render(request, 'grm_alerts/dashboard.html', context)

@login_required
def workflow_list_view(request):
    """List all alert workflows"""
    workflows = AlertWorkflow.objects.all().order_by('-updated_at')
    
    # Apply filters
    status_filter = request.GET.get('status', '')
    alert_type_filter = request.GET.get('alert_type', '')
    
    if status_filter:
        workflows = workflows.filter(status=status_filter)
    
    if alert_type_filter:
        workflows = workflows.filter(alert_type=alert_type_filter)
    
    # Add execution counts
    workflows = workflows.annotate(execution_count=Count('executions'))
    
    # Pagination
    paginator = Paginator(workflows, 20)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    context = {
        'workflows': page_obj,
        'page_obj': page_obj,
        'status_filter': status_filter,
        'alert_type_filter': alert_type_filter,
    }
    
    return render(request, 'grm_alerts/workflow_list.html', context)

@login_required
def workflow_detail_view(request, workflow_id):
    """Detailed view of an alert workflow"""
    workflow = get_object_or_404(AlertWorkflow, id=workflow_id)
    
    # Execution statistics
    executions = workflow.executions.all()
    total_executions = executions.count()
    successful_executions = executions.filter(status='success').count()
    failed_executions = executions.filter(status='failed').count()
    
    success_rate = round((successful_executions / total_executions * 100) if total_executions > 0 else 0, 1)
    
    # Recent executions
    recent_executions = executions.order_by('-started_at')[:20]
    
    context = {
        'workflow': workflow,
        'total_executions': total_executions,
        'successful_executions': successful_executions,
        'failed_executions': failed_executions,
        'success_rate': success_rate,
        'recent_executions': recent_executions,
    }
    
    return render(request, 'grm_alerts/workflow_detail.html', context)

@login_required
def execution_list_view(request):
    """List all alert executions"""
    executions = AlertExecution.objects.select_related('workflow').order_by('-started_at')
    
    # Apply filters
    workflow_filter = request.GET.get('workflow', '')
    status_filter = request.GET.get('status', '')
    
    if workflow_filter:
        executions = executions.filter(workflow_id=workflow_filter)
    
    if status_filter:
        executions = executions.filter(status=status_filter)
    
    # Pagination
    paginator = Paginator(executions, 50)
    page_number = request.GET.get('page')
    page_obj = paginator.get_page(page_number)
    
    # Get workflows for filter dropdown
    workflows = AlertWorkflow.objects.all().order_by('name')
    
    context = {
        'executions': page_obj,
        'page_obj': page_obj,
        'workflows': workflows,
        'workflow_filter': workflow_filter,
        'status_filter': status_filter,
    }
    
    return render(request, 'grm_alerts/execution_list.html', context)

@login_required
def execution_detail_view(request, execution_id):
    """Detailed view of an alert execution"""
    execution = get_object_or_404(AlertExecution, id=execution_id)
    logs = execution.logs.all().order_by('timestamp')
    
    context = {
        'execution': execution,
        'logs': logs,
    }
    
    return render(request, 'grm_alerts/execution_detail.html', context)

@csrf_exempt
def execute_workflow_api(request, workflow_id):
    """API endpoint to execute a workflow"""
    if request.method != 'POST':
        return JsonResponse({'error': 'Method not allowed'}, status=405)
    
    try:
        workflow = AlertWorkflow.objects.get(id=workflow_id, status='active')
        
        # Parse input data
        input_data = {}
        if request.body:
            input_data = json.loads(request.body)
        
        # Execute workflow asynchronously
        task = execute_alert_workflow.delay(str(workflow.id), input_data)
        
        return JsonResponse({
            'success': True,
            'task_id': task.id,
            'message': 'Workflow execution started'
        })
        
    except AlertWorkflow.DoesNotExist:
        return JsonResponse({'error': 'Workflow not found'}, status=404)
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=500)