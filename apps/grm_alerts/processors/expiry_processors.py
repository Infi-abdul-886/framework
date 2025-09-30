"""
Expiry Processors - Business logic for handling various expiry alerts
"""
import json
from typing import Dict, List, Any, Optional
from django.utils import timezone
from django.core.mail import send_mail
from django.template.loader import render_to_string
from django.conf import settings
from datetime import datetime, timedelta

from .base_processor import BaseProcessor

class ExpiryProcessor(BaseProcessor):
    """
    Main processor for handling expiry alerts and status updates
    """
    
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process expiry data based on configuration
        
        Args:
            data: List of records from query
            config: Processor configuration
            
        Returns:
            Processing results
        """
        processor_type = config.get('processor_type', 'send_notifications')
        
        if processor_type == 'send_notifications':
            return self.send_expiry_notifications(data, config)
        elif processor_type == 'update_expired_status':
            return self.update_expired_status(data, config)
        elif processor_type == 'update_penalty_status':
            return self.update_penalty_status(data, config)
        else:
            raise ValueError(f"Unknown processor type: {processor_type}")
    
    def send_expiry_notifications(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Send expiry notification emails
        
        Args:
            data: Expiry records
            config: Email configuration
            
        Returns:
            Processing results
        """
        alert_type = config.get('alert_type', 'FARE')
        email_template = config.get('email_template', 'default_expiry_alert.html')
        
        self.log_info(f"Starting {alert_type} expiry notifications for {len(data)} records", 'email')
        
        # Group records by user for consolidated emails
        user_groups = self._group_by_user(data)
        
        for user_id, user_records in user_groups.items():
            try:
                # Get user details
                user_info = self._get_user_details(user_id)
                if not user_info or not user_info.get('email_id'):
                    self.log_warning(f"No email found for user {user_id}", 'email')
                    continue
                
                # Check if user wants to receive this type of alert
                if not self._should_send_alert(user_id, alert_type):
                    self.log_info(f"User {user_id} has disabled {alert_type} alerts", 'email')
                    continue
                
                # Prepare email data
                email_data = self._prepare_email_data(user_info, user_records, alert_type, config)
                
                # Send email
                if self._send_notification_email(email_data, email_template):
                    self.stats['emails_sent'] += 1
                    self.log_info(f"Sent {alert_type} alert to {user_info['email_id']}", 'email')
                    
                    # Log email in cron_email_details table
                    self._log_cron_email(user_records[0], user_info['email_id'], alert_type)
                else:
                    self.log_error(f"Failed to send {alert_type} alert to {user_info['email_id']}", 'email')
                
                self.stats['records_processed'] += len(user_records)
                
            except Exception as e:
                self.log_error(f"Error processing user {user_id}: {str(e)}", 'email')
        
        return self.get_processing_stats()
    
    def update_expired_status(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Update status for expired offers
        
        Args:
            data: Expired records
            config: Update configuration
            
        Returns:
            Processing results
        """
        target_table = config.get('target_table', 'airlines_request_mapping')
        status_field = config.get('status_field', 'current_status')
        expired_status = config.get('expired_status', 7)  # OE status
        
        self.log_info(f"Updating expired status for {len(data)} records", 'status_update')
        
        updated_count = 0
        for record in data:
            try:
                # Update the record status
                affected_rows = self.update_records(
                    table=target_table,
                    updates={status_field: expired_status},
                    conditions={'request_master_id': record['request_master_id']}
                )
                
                if affected_rows > 0:
                    updated_count += affected_rows
                    self.log_info(f"Updated status for request {record['request_master_id']}", 'status_update')
                
            except Exception as e:
                self.log_error(f"Failed to update status for request {record['request_master_id']}: {str(e)}", 'status_update')
        
        self.stats['records_processed'] = updated_count
        self.log_info(f"Updated status for {updated_count} records", 'status_update')
        
        return self.get_processing_stats()
    
    def update_penalty_status(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Update penalty status for expired penalties
        
        Args:
            data: Expired penalty records
            config: Update configuration
            
        Returns:
            Processing results
        """
        self.log_info(f"Processing penalty status updates for {len(data)} records", 'penalty_update')
        
        updated_count = 0
        for record in data:
            try:
                # Update request_timeline_details status
                affected_rows = self.update_records(
                    table='request_timeline_details',
                    updates={'status': 'EXPIRED'},
                    conditions={
                        'transaction_id': record['transaction_id'],
                        'timeline_type': 'PENALTY'
                    }
                )
                
                if affected_rows > 0:
                    updated_count += affected_rows
                    self.log_info(f"Updated penalty status for transaction {record['transaction_id']}", 'penalty_update')
                
            except Exception as e:
                self.log_error(f"Failed to update penalty for transaction {record['transaction_id']}: {str(e)}", 'penalty_update')
        
        self.stats['records_processed'] = updated_count
        return self.get_processing_stats()
    
    def _group_by_user(self, data: List[Dict[str, Any]]) -> Dict[int, List[Dict[str, Any]]]:
        """Group records by user_id"""
        user_groups = {}
        for record in data:
            user_id = record.get('user_id')
            if user_id:
                if user_id not in user_groups:
                    user_groups[user_id] = []
                user_groups[user_id].append(record)
        return user_groups
    
    def _get_user_details(self, user_id: int) -> Optional[Dict[str, Any]]:
        """Get user details from database"""
        try:
            sql = """
                SELECT ud.user_id, ud.first_name, ud.last_name, ud.email_id, ud.title,
                       ud.time_zone_interval, ud.corporate_id, cd.corporate_name
                FROM user_details ud
                LEFT JOIN corporate_details cd ON ud.corporate_id = cd.corporate_id
                WHERE ud.user_id = %s
            """
            results = self.execute_raw_sql(sql, [user_id])
            return results[0] if results else None
        except Exception as e:
            self.log_error(f"Failed to get user details for user {user_id}: {str(e)}", 'user_lookup')
            return None
    
    def _should_send_alert(self, user_id: int, alert_type: str) -> bool:
        """Check if user wants to receive this type of alert"""
        try:
            # Check user email preferences
            # This would integrate with the existing email preference system
            return True  # For now, assume all users want alerts
        except Exception as e:
            self.log_warning(f"Could not check email preferences for user {user_id}: {str(e)}", 'preferences')
            return True
    
    def _prepare_email_data(self, user_info: Dict[str, Any], records: List[Dict[str, Any]], 
                           alert_type: str, config: Dict[str, Any]) -> Dict[str, Any]:
        """Prepare data for email template"""
        
        # Calculate expiry hours for first record
        first_record = records[0]
        expiry_field = self._get_expiry_field(alert_type)
        expiry_date = first_record.get(expiry_field)
        
        expiry_hours = 0
        if expiry_date:
            try:
                expiry_dt = datetime.fromisoformat(str(expiry_date).replace('Z', '+00:00'))
                expiry_hours = max(0, (expiry_dt - timezone.now()).total_seconds() / 3600)
            except:
                pass
        
        return {
            'user_name': f"{user_info.get('title', '')} {user_info.get('first_name', '')} {user_info.get('last_name', '')}".strip(),
            'email_id': user_info['email_id'],
            'alert_type': alert_type,
            'records': records,
            'record_count': len(records),
            'expiry_hours': round(expiry_hours, 1),
            'corporate_name': user_info.get('corporate_name', ''),
            'current_date': timezone.now().strftime('%Y-%m-%d %H:%M:%S'),
            'subject': self._get_email_subject(alert_type, len(records))
        }
    
    def _get_expiry_field(self, alert_type: str) -> str:
        """Get the expiry date field name for alert type"""
        field_mapping = {
            'FARE': 'fare_expiry_date',
            'PAYMENT': 'payment_validity_date',
            'PASSENGER': 'passenger_validity_date',
            'PENALTY': 'expiry_date'
        }
        return field_mapping.get(alert_type, 'expiry_date')
    
    def _get_email_subject(self, alert_type: str, record_count: int) -> str:
        """Generate email subject based on alert type"""
        subjects = {
            'FARE': f'Fare Expiry Alert - {record_count} Request(s)',
            'PAYMENT': f'Payment Expiry Alert - {record_count} PNR(s)',
            'PASSENGER': f'Passenger Details Expiry Alert - {record_count} PNR(s)',
            'PENALTY': f'Penalty Expiry Alert - {record_count} Request(s)'
        }
        return subjects.get(alert_type, f'Expiry Alert - {record_count} Record(s)')
    
    def _send_notification_email(self, email_data: Dict[str, Any], template: str) -> bool:
        """Send notification email"""
        try:
            # Render email content
            html_content = render_to_string(f'grm_alerts/emails/{template}', email_data)
            
            # Send email
            send_mail(
                subject=email_data['subject'],
                message='',  # Plain text version
                html_message=html_content,
                from_email=getattr(settings, 'DEFAULT_FROM_EMAIL', 'noreply@example.com'),
                recipient_list=[email_data['email_id']],
                fail_silently=False
            )
            
            return True
            
        except Exception as e:
            self.log_error(f"Email sending failed: {str(e)}", 'email')
            return False
    
    def _log_cron_email(self, record: Dict[str, Any], email_id: str, alert_type: str):
        """Log email in cron_email_details table"""
        try:
            sql = """
                INSERT INTO cron_email_details 
                (request_master_id, email_type, email_subject, sent_to, expiry_date, sent_date, pnr)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            
            email_type = self._get_email_type_id(alert_type)
            expiry_field = self._get_expiry_field(alert_type)
            
            params = [
                record.get('request_master_id', 0),
                email_type,
                self._get_email_subject(alert_type, 1),
                email_id,
                record.get(expiry_field, ''),
                timezone.now().strftime('%Y-%m-%d %H:%M:%S'),
                record.get('pnr', '')
            ]
            
            with connection.cursor() as cursor:
                cursor.execute(sql, params)
                
        except Exception as e:
            self.log_warning(f"Failed to log cron email: {str(e)}", 'email_log')
    
    def _get_email_type_id(self, alert_type: str) -> int:
        """Get email type ID for cron_email_details"""
        type_mapping = {
            'FARE': 1,
            'PAYMENT': 2,
            'PASSENGER': 3,
            'PENALTY': 4
        }
        return type_mapping.get(alert_type, 0)

class FareExpiryProcessor(ExpiryProcessor):
    """
    Specialized processor for fare expiry alerts
    """
    
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Process fare expiry notifications"""
        config['alert_type'] = 'FARE'
        config['email_template'] = 'fare_expiry_alert.html'
        return self.send_expiry_notifications(data, config)

class PaymentExpiryProcessor(ExpiryProcessor):
    """
    Specialized processor for payment expiry alerts
    """
    
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Process payment expiry notifications"""
        config['alert_type'] = 'PAYMENT'
        config['email_template'] = 'payment_expiry_alert.html'
        return self.send_expiry_notifications(data, config)

class PassengerExpiryProcessor(ExpiryProcessor):
    """
    Specialized processor for passenger submission expiry alerts
    """
    
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Process passenger expiry notifications"""
        config['alert_type'] = 'PASSENGER'
        config['email_template'] = 'passenger_expiry_alert.html'
        return self.send_expiry_notifications(data, config)

class PenaltyExpiryProcessor(ExpiryProcessor):
    """
    Specialized processor for penalty expiry alerts
    """
    
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Process penalty expiry notifications"""
        config['alert_type'] = 'PENALTY'
        config['email_template'] = 'penalty_expiry_alert.html'
        return self.send_expiry_notifications(data, config)

class StatusUpdateProcessor(ExpiryProcessor):
    """
    Processor for updating expired record statuses
    """
    
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Update expired record statuses"""
        update_type = config.get('update_type', 'offer_expired')
        
        if update_type == 'offer_expired':
            return self._update_offer_expired_status(data, config)
        elif update_type == 'penalty_expired':
            return self._update_penalty_expired_status(data, config)
        else:
            raise ValueError(f"Unknown update type: {update_type}")
    
    def _update_offer_expired_status(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Update offers to expired status (OE - status 7)"""
        self.log_info(f"Updating {len(data)} offers to expired status", 'status_update')
        
        updated_count = 0
        for record in data:
            try:
                # Update airlines_request_mapping status to 7 (OE)
                affected_rows = self.update_records(
                    table='airlines_request_mapping',
                    updates={'current_status': 7},
                    conditions={'request_master_id': record['request_master_id']}
                )
                
                if affected_rows > 0:
                    updated_count += affected_rows
                    
                    # Also update request_master view_status if needed
                    self.update_records(
                        table='request_master',
                        updates={'view_status': 'OE'},
                        conditions={'request_master_id': record['request_master_id']}
                    )
                
            except Exception as e:
                self.log_error(f"Failed to update offer status for request {record['request_master_id']}: {str(e)}", 'status_update')
        
        self.stats['records_processed'] = updated_count
        self.log_info(f"Updated {updated_count} offers to expired status", 'status_update')
        
        return self.get_processing_stats()
    
    def _update_penalty_expired_status(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """Update penalty records to expired status"""
        self.log_info(f"Updating {len(data)} penalties to expired status", 'penalty_update')
        
        updated_count = 0
        for record in data:
            try:
                # Update request_timeline_details status
                affected_rows = self.update_records(
                    table='request_timeline_details',
                    updates={'status': 'EXPIRED'},
                    conditions={
                        'transaction_id': record['transaction_id'],
                        'timeline_type': 'PENALTY'
                    }
                )
                
                if affected_rows > 0:
                    updated_count += affected_rows
                
            except Exception as e:
                self.log_error(f"Failed to update penalty status for transaction {record['transaction_id']}: {str(e)}", 'penalty_update')
        
        self.stats['records_processed'] = updated_count
        return self.get_processing_stats()

# Processor Registry for UI Discovery
PROCESSOR_REGISTRY = {
    # Expiry Notifications
    'alerts::expiry::fare_notifications': {
        'class': 'apps.grm_alerts.processors.expiry_processors.FareExpiryProcessor',
        'name': 'Fare Expiry Notifications',
        'description': 'Send fare expiry alert emails to users',
        'config_schema': {
            'alert_type': {'type': 'string', 'default': 'FARE'},
            'email_template': {'type': 'string', 'default': 'fare_expiry_alert.html'},
            'hours_ahead': {'type': 'integer', 'default': 24}
        }
    },
    'alerts::expiry::payment_notifications': {
        'class': 'apps.grm_alerts.processors.expiry_processors.PaymentExpiryProcessor',
        'name': 'Payment Expiry Notifications',
        'description': 'Send payment expiry alert emails to users',
        'config_schema': {
            'alert_type': {'type': 'string', 'default': 'PAYMENT'},
            'email_template': {'type': 'string', 'default': 'payment_expiry_alert.html'},
            'hours_ahead': {'type': 'integer', 'default': 24}
        }
    },
    'alerts::expiry::passenger_notifications': {
        'class': 'apps.grm_alerts.processors.expiry_processors.PassengerExpiryProcessor',
        'name': 'Passenger Expiry Notifications',
        'description': 'Send passenger submission expiry alert emails',
        'config_schema': {
            'alert_type': {'type': 'string', 'default': 'PASSENGER'},
            'email_template': {'type': 'string', 'default': 'passenger_expiry_alert.html'},
            'hours_ahead': {'type': 'integer', 'default': 24}
        }
    },
    'alerts::expiry::penalty_notifications': {
        'class': 'apps.grm_alerts.processors.expiry_processors.PenaltyExpiryProcessor',
        'name': 'Penalty Expiry Notifications',
        'description': 'Send penalty expiry alert emails',
        'config_schema': {
            'alert_type': {'type': 'string', 'default': 'PENALTY'},
            'email_template': {'type': 'string', 'default': 'penalty_expiry_alert.html'},
            'hours_ahead': {'type': 'integer', 'default': 24}
        }
    },
    
    # Status Updates
    'services::expiry::update_offers': {
        'class': 'apps.grm_alerts.processors.expiry_processors.StatusUpdateProcessor',
        'name': 'Update Expired Offers',
        'description': 'Update offer status to expired (OE)',
        'config_schema': {
            'update_type': {'type': 'string', 'default': 'offer_expired'},
            'target_table': {'type': 'string', 'default': 'airlines_request_mapping'},
            'status_field': {'type': 'string', 'default': 'current_status'},
            'expired_status': {'type': 'integer', 'default': 7}
        }
    },
    'services::expiry::update_penalties': {
        'class': 'apps.grm_alerts.processors.expiry_processors.StatusUpdateProcessor',
        'name': 'Update Expired Penalties',
        'description': 'Update penalty status to expired',
        'config_schema': {
            'update_type': {'type': 'string', 'default': 'penalty_expired'}
        }
    },
    
    # Combined Processors
    'workflows::expiry::complete_fare_process': {
        'class': 'apps.grm_alerts.processors.expiry_processors.ExpiryProcessor',
        'name': 'Complete Fare Expiry Process',
        'description': 'Handle both fare expiry notifications and status updates',
        'config_schema': {
            'processor_type': {'type': 'string', 'default': 'send_notifications'},
            'alert_type': {'type': 'string', 'default': 'FARE'},
            'include_status_update': {'type': 'boolean', 'default': True}
        }
    }
}