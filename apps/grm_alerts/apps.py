from django.apps import AppConfig

class GrmAlertsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.grm_alerts'
    verbose_name = 'GRM Alert System'

    def ready(self):
        # Import signal handlers
        try:
            import apps.grm_alerts.signals
        except ImportError:
            pass