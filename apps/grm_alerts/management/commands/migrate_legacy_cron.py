"""
Management command to migrate legacy PHP cron jobs to the new system
"""
from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model
from apps.grm_alerts.models import AlertWorkflow

User = get_user_model()

class Command(BaseCommand):
    help = 'Migrate legacy PHP cron jobs to the new alert system'
    
    def add_arguments(self, parser):
        parser.add_argument(
            '--user',
            type=str,
            required=True,
            help='Username to assign migrated workflows to',
        )
        parser.add_argument(
            '--dry-run',
            action='store_true',
            help='Show what would be migrated without actually migrating',
        )
    
    def handle(self, *args, **options):
        username = options['user']
        dry_run = options['dry_run']
        
        try:
            user = User.objects.get(username=username)
        except User.DoesNotExist:
            self.stdout.write(
                self.style.ERROR(f'User "{username}" not found')
            )
            return
        
        # Legacy cron job mappings
        legacy_jobs = [
            {
                'legacy_name': 'fareExpiryV2.php',
                'new_name': 'Complete Fare Expiry Process',
                'description': 'Migrated from fareExpiryV2.php - handles fare, payment, passenger, and penalty expiry',
                'alert_type': 'COMBINED',
                'cron_expression': '*/5 * * * *',  # Every 5 minutes (original frequency)
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.ExpiryProcessor',
                    'processes': ['FARE', 'PAYMENT', 'PASSENGER', 'PENALTY', 'OFFEREXPIRY']
                }
            },
            # Add other legacy cron jobs here as they are migrated
        ]
        
        migrated_count = 0
        
        for job in legacy_jobs:
            try:
                if dry_run:
                    self.stdout.write(
                        f'Would migrate: {job["legacy_name"]} -> {job["new_name"]}'
                    )
                else:
                    workflow, created = AlertWorkflow.objects.get_or_create(
                        name=job['new_name'],
                        defaults={
                            'description': job['description'],
                            'alert_type': job['alert_type'],
                            'cron_expression': job['cron_expression'],
                            'processor_config': job['processor_config'],
                            'created_by': user,
                            'status': 'draft',
                            'is_scheduled': False
                        }
                    )
                    
                    if created:
                        self.stdout.write(
                            self.style.SUCCESS(f'Migrated: {job["legacy_name"]} -> {workflow.name}')
                        )
                    else:
                        self.stdout.write(
                            self.style.WARNING(f'Already exists: {workflow.name}')
                        )
                
                migrated_count += 1
                
            except Exception as e:
                self.stdout.write(
                    self.style.ERROR(f'Failed to migrate {job["legacy_name"]}: {str(e)}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'Migration completed: {migrated_count} jobs processed')
        )
        
        if not dry_run:
            self.stdout.write(
                self.style.WARNING(
                    'Next steps:\n'
                    '1. Create query templates for each workflow\n'
                    '2. Test workflows in draft mode\n'
                    '3. Activate and enable scheduling\n'
                    '4. Disable legacy PHP cron jobs'
                )
            )