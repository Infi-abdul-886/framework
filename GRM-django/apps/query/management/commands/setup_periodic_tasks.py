from django.core.management.base import BaseCommand
from django_celery_beat.models import PeriodicTask, CrontabSchedule
from apps.query.models import ScheduledQueryTask
import json

class Command(BaseCommand):
    help = 'Setup periodic tasks for scheduled queries'

    def handle(self, *args, **options):
        # Get all active scheduled query tasks
        scheduled_tasks = ScheduledQueryTask.objects.filter(is_active=True)
        
        created_count = 0
        updated_count = 0
        
        for task in scheduled_tasks:
            # Parse cron expression (assuming format: minute hour day month day_of_week)
            cron_parts = task.cron_expression.split()
            if len(cron_parts) != 5:
                self.stdout.write(
                    self.style.WARNING(f'Invalid cron expression for task {task.task_name}: {task.cron_expression}')
                )
                continue
            
            minute, hour, day_of_month, month_of_year, day_of_week = cron_parts
            
            # Create or get crontab schedule
            schedule, created = CrontabSchedule.objects.get_or_create(
                minute=minute,
                hour=hour,
                day_of_week=day_of_week,
                day_of_month=day_of_month,
                month_of_year=month_of_year,
            )
            
            # Create or update periodic task
            periodic_task, task_created = PeriodicTask.objects.get_or_create(
                name=f'scheduled_query_{task.id}',
                defaults={
                    'task': 'apps.query.tasks.execute_scheduled_query_task',
                    'crontab': schedule,
                    'args': json.dumps([task.id]),
                    'enabled': task.is_active,
                }
            )
            
            if not task_created:
                # Update existing task
                periodic_task.crontab = schedule
                periodic_task.args = json.dumps([task.id])
                periodic_task.enabled = task.is_active
                periodic_task.save()
                updated_count += 1
            else:
                created_count += 1
        
        self.stdout.write(
            self.style.SUCCESS(
                f'Successfully setup periodic tasks: {created_count} created, {updated_count} updated'
            )
        )