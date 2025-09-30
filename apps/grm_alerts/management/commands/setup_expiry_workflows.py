"""
Management command to set up default expiry alert workflows
"""
from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model
from apps.grm_alerts.models import AlertWorkflow

User = get_user_model()

class Command(BaseCommand):
    help = 'Set up default expiry alert workflows'
    
    def add_arguments(self, parser):
        parser.add_argument(
            '--user',
            type=str,
            required=True,
            help='Username to assign workflows to',
        )
    
    def handle(self, *args, **options):
        username = options['user']
        
        try:
            user = User.objects.get(username=username)
        except User.DoesNotExist:
            self.stdout.write(
                self.style.ERROR(f'User "{username}" not found')
            )
            return
        
        workflows = [
            {
                'name': 'Fare Expiry Alerts',
                'description': 'Send fare expiry notifications to users',
                'alert_type': 'FARE',
                'cron_expression': '0 */6 * * *',  # Every 6 hours
                'query_templates': [1],  # Template IDs to be configured
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.FareExpiryProcessor',
                    'alert_type': 'FARE',
                    'email_template': 'fare_expiry_alert.html',
                    'hours_ahead': 24
                }
            },
            {
                'name': 'Payment Expiry Alerts',
                'description': 'Send payment expiry notifications to users',
                'alert_type': 'PAYMENT',
                'cron_expression': '0 */4 * * *',  # Every 4 hours
                'query_templates': [2],
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.PaymentExpiryProcessor',
                    'alert_type': 'PAYMENT',
                    'email_template': 'payment_expiry_alert.html',
                    'hours_ahead': 24
                }
            },
            {
                'name': 'Passenger Expiry Alerts',
                'description': 'Send passenger submission expiry notifications',
                'alert_type': 'PASSENGER',
                'cron_expression': '0 */6 * * *',  # Every 6 hours
                'query_templates': [3],
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.PassengerExpiryProcessor',
                    'alert_type': 'PASSENGER',
                    'email_template': 'passenger_expiry_alert.html',
                    'hours_ahead': 24
                }
            },
            {
                'name': 'Penalty Expiry Alerts',
                'description': 'Send penalty expiry notifications',
                'alert_type': 'PENALTY',
                'cron_expression': '0 */8 * * *',  # Every 8 hours
                'query_templates': [4],
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.PenaltyExpiryProcessor',
                    'alert_type': 'PENALTY',
                    'email_template': 'penalty_expiry_alert.html',
                    'hours_ahead': 24
                }
            },
            {
                'name': 'Expired Offers Status Update',
                'description': 'Update status for expired offers',
                'alert_type': 'STATUS_UPDATE',
                'cron_expression': '0 */2 * * *',  # Every 2 hours
                'query_templates': [5],
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.StatusUpdateProcessor',
                    'update_type': 'offer_expired',
                    'target_table': 'airlines_request_mapping',
                    'status_field': 'current_status',
                    'expired_status': 7
                }
            },
            {
                'name': 'Expired Penalties Status Update',
                'description': 'Update status for expired penalties',
                'alert_type': 'STATUS_UPDATE',
                'cron_expression': '0 */4 * * *',  # Every 4 hours
                'query_templates': [6],
                'processor_config': {
                    'processor_class': 'apps.grm_alerts.processors.expiry_processors.StatusUpdateProcessor',
                    'update_type': 'penalty_expired'
                }
            }
        ]
        
        created_count = 0
        updated_count = 0
        
        for workflow_data in workflows:
            workflow, created = AlertWorkflow.objects.get_or_create(
                name=workflow_data['name'],
                defaults={
                    **workflow_data,
                    'created_by': user,
                    'status': 'draft',  # Start as draft for review
                    'is_scheduled': False  # Enable manually after configuration
                }
            )
            
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'Created workflow: {workflow.name}')
                )
            else:
                # Update existing workflow
                for key, value in workflow_data.items():
                    if key not in ['name']:
                        setattr(workflow, key, value)
                workflow.save()
                updated_count += 1
                self.stdout.write(
                    self.style.WARNING(f'Updated workflow: {workflow.name}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'Successfully set up workflows: {created_count} created, {updated_count} updated'
            )
        )
        
        self.stdout.write(
            self.style.WARNING(
                'Remember to:\n'
                '1. Configure query templates in the Query Builder\n'
                '2. Test each workflow before activating\n'
                '3. Enable scheduling when ready'
            )
        )