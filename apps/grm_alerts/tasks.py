"""
Celery tasks for GRM alert system
"""
from celery import shared_task
from django.utils import timezone
from django.apps import apps
import importlib
import logging
import time

from .models import AlertWorkflow, AlertExecution, AlertLog
from .selectors.query_selectors import ExpiryQuerySelector

logger = logging.getLogger(__name__)

@shared_task(bind=True)
def execute_alert_workflow(self, workflow_id: str, input_data: Dict[str, Any] = None):
    """
    Execute an alert workflow
    
    Args:
        workflow_id: UUID of the AlertWorkflow to execute
        input_data: Optional input data
    """
    try:
        # Get the workflow
        workflow = AlertWorkflow.objects.get(id=workflow_id, status='active')
        
        # Create execution record
        execution = AlertExecution.objects.create(
            workflow=workflow,
            status='running',
            input_data=input_data or {}
        )
        
        logger.info(f"Starting execution of alert workflow: {workflow.name}")
        
        # Execute the workflow
        success = _execute_workflow_steps(execution)
        
        # Update execution status
        execution.status = 'success' if success else 'failed'
        execution.finished_at = timezone.now()
        execution.duration_seconds = (execution.finished_at - execution.started_at).total_seconds()
        execution.save()
        
        # Update workflow last executed time
        workflow.last_executed_at = timezone.now()
        workflow.save()
        
        logger.info(f"Alert workflow execution completed: {workflow.name} - {execution.status}")
        
        return {
            'workflow_id': workflow_id,
            'execution_id': str(execution.id),
            'status': execution.status,
            'duration_seconds': execution.duration_seconds,
            'records_processed': execution.records_processed,
            'emails_sent': execution.emails_sent
        }
        
    except Exception as e:
        logger.error(f"Alert workflow execution failed: {str(e)}")
        
        try:
            execution = AlertExecution.objects.get(workflow_id=workflow_id, status='running')
            execution.status = 'failed'
            execution.finished_at = timezone.now()
            execution.error_message = str(e)
            execution.save()
        except:
            pass
        
        raise

def _execute_workflow_steps(execution: AlertExecution) -> bool:
    """
    Execute the steps of an alert workflow
    
    Args:
        execution: AlertExecution instance
        
    Returns:
        True if successful, False if failed
    """
    try:
        workflow = execution.workflow
        selector = ExpiryQuerySelector()
        
        # Step 1: Execute queries
        all_data = []
        for template_id in workflow.query_templates:
            try:
                result = selector.execute_query_template(template_id)
                if result['success']:
                    all_data.extend(result['data'])
                    _log_execution_step(execution, 'info', f"Query template {template_id} returned {result['count']} records", 'query')
                else:
                    _log_execution_step(execution, 'error', f"Query template {template_id} failed: {result['error']}", 'query')
                    return False
            except Exception as e:
                _log_execution_step(execution, 'error', f"Query template {template_id} execution failed: {str(e)}", 'query')
                return False
        
        if not all_data:
            _log_execution_step(execution, 'info', 'No data returned from queries', 'query')
            return True
        
        # Step 2: Execute processor
        processor_config = workflow.processor_config
        processor_class_path = processor_config.get('processor_class')
        
        if not processor_class_path:
            _log_execution_step(execution, 'error', 'No processor class specified', 'processor')
            return False
        
        # Import and instantiate processor
        module_path, class_name = processor_class_path.rsplit('.', 1)
        module = importlib.import_module(module_path)
        processor_class = getattr(module, class_name)
        processor = processor_class(execution_id=str(execution.id))
        
        # Execute processor
        result = processor.process(all_data, processor_config)
        
        # Update execution statistics
        execution.records_processed = result.get('records_processed', 0)
        execution.emails_sent = result.get('emails_sent', 0)
        execution.output_data = result
        execution.save()
        
        _log_execution_step(execution, 'info', f"Processor completed: {execution.records_processed} records, {execution.emails_sent} emails", 'processor')
        
        return True
        
    except Exception as e:
        _log_execution_step(execution, 'error', f"Workflow execution failed: {str(e)}", 'execution')
        return False

def _log_execution_step(execution: AlertExecution, level: str, message: str, step: str):
    """Log an execution step"""
    try:
        AlertLog.objects.create(
            execution=execution,
            level=level,
            message=message,
            step=step
        )
    except Exception as e:
        logger.error(f"Failed to log execution step: {str(e)}")

@shared_task
def run_scheduled_alert_workflows():
    """
    Run all scheduled alert workflows that are due
    """
    from croniter import croniter
    
    now = timezone.now()
    scheduled_workflows = AlertWorkflow.objects.filter(
        status='active',
        is_scheduled=True,
        cron_expression__isnull=False
    ).exclude(cron_expression='')
    
    executed_count = 0
    
    for workflow in scheduled_workflows:
        try:
            # Check if workflow is due
            base_time = workflow.last_executed_at or workflow.created_at
            cron = croniter(workflow.cron_expression, base_time)
            next_run = cron.get_next(datetime)
            
            if next_run <= now:
                execute_alert_workflow.delay(str(workflow.id))
                executed_count += 1
                logger.info(f"Scheduled execution for workflow: {workflow.name}")
                
        except Exception as e:
            logger.error(f"Failed to schedule workflow {workflow.name}: {str(e)}")
    
    logger.info(f"Scheduled {executed_count} alert workflows for execution")
    return executed_count

@shared_task
def cleanup_old_alert_executions():
    """
    Clean up old alert executions to prevent database bloat
    """
    from datetime import timedelta
    
    # Delete executions older than 30 days
    cutoff_date = timezone.now() - timedelta(days=30)
    
    deleted_count = AlertExecution.objects.filter(
        finished_at__lt=cutoff_date
    ).delete()[0]
    
    logger.info(f"Cleaned up {deleted_count} old alert executions")
    return deleted_count