from celery import shared_task
from django.core.mail import send_mail
from django.template.loader import render_to_string
from django.conf import settings
import json
import csv
import io
import time
from datetime import datetime
from django.utils import timezone
from croniter import croniter
from .models import ScheduledQueryTask, QueryExecutionLog, QueryTemplate
from .views import query_builder_api

from django.test import RequestFactory
from django.http import JsonResponse


@shared_task
def run_scheduled_tasks():
    """
    This task should be run by Celery Beat every minute.
    It finds which ScheduledQueryTask objects are due and queues them for execution.
    """
    now = timezone.now()
    tasks_to_run = ScheduledQueryTask.objects.filter(is_active=True)

    for task in tasks_to_run:
        # Skip tasks that don't have a valid cron expression
        if not task.cron_expression or not croniter.is_valid(task.cron_expression):
            continue

        # Use the last_run time (or created_at if never run) as the base for the next calculation
        base_time = task.last_run or task.created_at
        
        # Get the next scheduled run time after the last run
        itr = croniter(task.cron_expression, base_time)
        next_run_datetime = itr.get_next(datetime)

        # If the next scheduled run time is in the past, the task is due.
        if next_run_datetime <= now:
            print(f"Scheduling task for execution: {task.task_name} (ID: {task.id})")
            execute_scheduled_query_task.delay(task.id)
            # The task itself will update its own `last_run` timestamp when it starts.


@shared_task(bind=True)
def execute_scheduled_query_task(self, task_id):
    """
    Execute a scheduled query task with multiple templates
    """
    try:
        task = ScheduledQueryTask.objects.get(id=task_id, is_active=True)
    except ScheduledQueryTask.DoesNotExist:
        return f"Scheduled task {task_id} not found or inactive"

    results = []
    factory = RequestFactory()
    
    # Update last run time at the beginning of execution to prevent re-queueing
    task.last_run = timezone.now()
    task.save()

    for template in task.query_templates.filter(is_active=True):
        start_time = time.time()
        
        try:
            # Execute the query using the template configuration
            request = factory.post('/query/api/query/', 
                                 data=json.dumps(template.configuration),
                                 content_type='application/json')
            
            # Execute the query
            response = query_builder_api(request)
            execution_time = time.time() - start_time
            
            if response.status_code == 200:
                response_data = json.loads(response.content)
                query_data = response_data.get('data', [])
                
                # Log successful execution
                log = QueryExecutionLog.objects.create(
                    scheduled_task=task,
                    query_template=template,
                    execution_time=execution_time,
                    row_count=len(query_data),
                    status='success',
                    result_data=query_data[:100]  # Store first 100 rows for reference
                )
                
                results.append({
                    'template': template.get_full_name(),
                    'status': 'success',
                    'row_count': len(query_data),
                    'execution_time': execution_time,
                    'data': query_data
                })
                
            else:
                error_data = json.loads(response.content)
                error_message = error_data.get('error', 'Unknown error')
                
                # Log failed execution
                QueryExecutionLog.objects.create(
                    scheduled_task=task,
                    query_template=template,
                    execution_time=execution_time,
                    row_count=0,
                    status='error',
                    error_message=error_message
                )
                
                results.append({
                    'template': template.get_full_name(),
                    'status': 'error',
                    'error': error_message,
                    'execution_time': execution_time
                })
                
        except Exception as e:
            execution_time = time.time() - start_time
            
            # Log exception
            QueryExecutionLog.objects.create(
                scheduled_task=task,
                query_template=template,
                execution_time=execution_time,
                row_count=0,
                status='error',
                error_message=str(e)
            )
            
            results.append({
                'template': template.get_full_name(),
                'status': 'error',
                'error': str(e),
                'execution_time': execution_time
            })

    # Send email with results if recipients are configured
    if task.email_recipients and results:
        send_query_results_email(task, results)

    return {
        'task_name': task.task_name,
        'executed_at': task.last_run.isoformat(),
        'total_queries': len(results),
        'successful_queries': len([r for r in results if r['status'] == 'success']),
        'failed_queries': len([r for r in results if r['status'] == 'error']),
        'results': results
    }

def send_query_results_email(task, results):
    """
    Send email with query results
    """
    try:
        recipients = [email.strip() for email in task.email_recipients.split(',')]
        
        # Prepare email content based on output format
        if task.output_format == 'json':
            attachment_content = json.dumps(results, indent=2)
            attachment_name = f"{task.task_name}_results.json"
            content_type = 'application/json'
            
        elif task.output_format == 'csv':
            # Convert results to CSV
            output = io.StringIO()
            if results and results[0].get('data'):
                # Use first successful result for CSV structure
                successful_result = next((r for r in results if r['status'] == 'success'), None)
                if successful_result and successful_result['data']:
                    fieldnames = successful_result['data'][0].keys()
                    writer = csv.DictWriter(output, fieldnames=fieldnames)
                    writer.writeheader()
                    
                    for result in results:
                        if result['status'] == 'success' and result.get('data'):
                            writer.writerows(result['data'])
                            
            attachment_content = output.getvalue()
            attachment_name = f"{task.task_name}_results.csv"
            content_type = 'text/csv'
            
        else:  # HTML format
            html_content = render_query_results_html(task, results)
            attachment_content = html_content
            attachment_name = f"{task.task_name}_results.html"
            content_type = 'text/html'

        # Create email content
        email_body = f"""
        Scheduled Query Task: {task.task_name}
        Executed at: {task.last_run}
        
        Summary:
        - Total queries: {len(results)}
        - Successful: {len([r for r in results if r['status'] == 'success'])}
        - Failed: {len([r for r in results if r['status'] == 'error'])}
        
        Please find the detailed results in the attachment.
        """

        # Send email (you might want to use Django's EmailMessage for attachments)
        from django.core.mail import EmailMessage
        
        email = EmailMessage(
            subject=task.email_subject,
            body=email_body,
            from_email=settings.DEFAULT_FROM_EMAIL,
            to=recipients,
        )
        
        email.attach(attachment_name, attachment_content, content_type)
        email.send()
        
    except Exception as e:
        print(f"Failed to send email for task {task.task_name}: {e}")

def render_query_results_html(task, results):
    """
    Render query results as HTML
    """
    html = f"""
    <html>
    <head>
        <title>{task.task_name} - Query Results</title>
        <style>
            body {{ font-family: Arial, sans-serif; margin: 20px; }}
            table {{ border-collapse: collapse; width: 100%; margin: 20px 0; }}
            th, td {{ border: 1px solid #ddd; padding: 8px; text-align: left; }}
            th {{ background-color: #f2f2f2; }}
            .error {{ color: red; }}
            .success {{ color: green; }}
            .summary {{ background-color: #f9f9f9; padding: 15px; margin: 20px 0; }}
        </style>
    </head>
    <body>
        <h1>{task.task_name} - Query Results</h1>
        <div class="summary">
            <h3>Execution Summary</h3>
            <p><strong>Executed at:</strong> {task.last_run}</p>
            <p><strong>Total queries:</strong> {len(results)}</p>
            <p><strong>Successful:</strong> {len([r for r in results if r['status'] == 'success'])}</p>
            <p><strong>Failed:</strong> {len([r for r in results if r['status'] == 'error'])}</p>
        </div>
    """
    
    for result in results:
        html += f"<h2>{result['template']}</h2>"
        
        if result['status'] == 'success':
            html += f'<p class="success">✓ Success - {result["row_count"]} rows in {result["execution_time"]:.2f}s</p>'
            
            if result.get('data') and len(result['data']) > 0:
                html += "<table>"
                # Headers
                html += "<tr>"
                for key in result['data'][0].keys():
                    html += f"<th>{key}</th>"
                html += "</tr>"
                
                # Data rows (limit to first 50 for email)
                for row in result['data'][:50]:
                    html += "<tr>"
                    for value in row.values():
                        html += f"<td>{value}</td>"
                    html += "</tr>"
                html += "</table>"
                
                if len(result['data']) > 50:
                    html += f"<p><em>Showing first 50 rows of {len(result['data'])} total rows.</em></p>"
        else:
            html += f'<p class="error">✗ Error: {result["error"]}</p>'
    
    html += "</body></html>"
    return html

@shared_task
def execute_query_template(template_id):
    """
    Execute a single query template
    """
    try:
        template = QueryTemplate.objects.get(id=template_id, is_active=True)
    except QueryTemplate.DoesNotExist:
        return f"Query template {template_id} not found or inactive"

    factory = RequestFactory()
    start_time = time.time()
    
    try:
        # Execute the query using the template configuration
        request = factory.post('/query/api/query/', 
                             data=json.dumps(template.configuration),
                             content_type='application/json')
        
        response = query_builder_api(request)
        execution_time = time.time() - start_time
        
        if response.status_code == 200:
            response_data = json.loads(response.content)
            query_data = response_data.get('data', [])
            
            # Log successful execution
            QueryExecutionLog.objects.create(
                query_template=template,
                execution_time=execution_time,
                row_count=len(query_data),
                status='success',
                result_data=query_data[:100]  # Store first 100 rows
            )
            
            return {
                'template': template.get_full_name(),
                'status': 'success',
                'row_count': len(query_data),
                'execution_time': execution_time,
                'data': query_data
            }
        else:
            error_data = json.loads(response.content)
            error_message = error_data.get('error', 'Unknown error')
            
            # Log failed execution
            QueryExecutionLog.objects.create(
                query_template=template,
                execution_time=execution_time,
                row_count=0,
                status='error',
                error_message=error_message
            )
            
            return {
                'template': template.get_full_name(),
                'status': 'error',
                'error': error_message,
                'execution_time': execution_time
            }
            
    except Exception as e:
        execution_time = time.time() - start_time
        
        # Log exception
        QueryExecutionLog.objects.create(
            query_template=template,
            execution_time=execution_time,
            row_count=0,
            status='error',
            error_message=str(e)
        )
        
        return {
            'template': template.get_full_name(),
            'status': 'error',
            'error': str(e),
            'execution_time': execution_time
        }
