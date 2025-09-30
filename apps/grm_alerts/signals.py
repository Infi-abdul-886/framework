from django.db.models.signals import post_save, post_delete
from django.dispatch import receiver
from django.core.cache import cache
from .models import AlertWorkflow, AlertExecution
import logging

logger = logging.getLogger(__name__)

@receiver(post_save, sender=AlertWorkflow)
def alert_workflow_saved(sender, instance, created, **kwargs):
    """Clear cache when alert workflow is saved"""
    cache_key = f"alert_workflow_{instance.id}"
    cache.delete(cache_key)
    
    if created:
        logger.info(f"New alert workflow created: {instance.name} (ID: {instance.id})")
    else:
        logger.info(f"Alert workflow updated: {instance.name} (ID: {instance.id})")

@receiver(post_save, sender=AlertExecution)
def alert_execution_saved(sender, instance, created, **kwargs):
    """Log execution status changes"""
    if created:
        logger.info(f"Alert execution started for workflow {instance.workflow.name}: {instance.id}")
    elif instance.status in ['success', 'failed', 'cancelled']:
        logger.info(f"Alert execution {instance.status} for workflow {instance.workflow.name}: {instance.id}")

@receiver(post_delete, sender=AlertWorkflow)
def alert_workflow_deleted(sender, instance, **kwargs):
    """Clean up when alert workflow is deleted"""
    cache_key = f"alert_workflow_{instance.id}"
    cache.delete(cache_key)
    logger.info(f"Alert workflow deleted: {instance.name} (ID: {instance.id})")