"""
Base processor class for all alert processors
"""
import logging
from abc import ABC, abstractmethod
from typing import Dict, List, Any, Optional
from django.utils import timezone
from django.db import connection

logger = logging.getLogger(__name__)

class BaseProcessor(ABC):
    """
    Base class for all alert processors
    """
    
    def __init__(self, execution_id: str = None):
        self.execution_id = execution_id
        self.logger = logger
        self.start_time = timezone.now()
        self.stats = {
            'records_processed': 0,
            'emails_sent': 0,
            'errors': 0,
            'warnings': 0
        }
    
    @abstractmethod
    def process(self, data: List[Dict[str, Any]], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process the data according to the processor's logic
        
        Args:
            data: List of records to process
            config: Processor configuration
            
        Returns:
            Dict containing processing results
        """
        pass
    
    def log_info(self, message: str, step: str = '', context: Dict[str, Any] = None):
        """Log info message"""
        self._log('info', message, step, context)
    
    def log_warning(self, message: str, step: str = '', context: Dict[str, Any] = None):
        """Log warning message"""
        self._log('warning', message, step, context)
        self.stats['warnings'] += 1
    
    def log_error(self, message: str, step: str = '', context: Dict[str, Any] = None):
        """Log error message"""
        self._log('error', message, step, context)
        self.stats['errors'] += 1
    
    def _log(self, level: str, message: str, step: str = '', context: Dict[str, Any] = None):
        """Internal logging method"""
        log_method = getattr(self.logger, level, self.logger.info)
        log_method(f"[{self.__class__.__name__}] {message}")
        
        # Store log in database if execution_id is provided
        if self.execution_id:
            try:
                from ..models import AlertExecution, AlertLog
                execution = AlertExecution.objects.get(id=self.execution_id)
                AlertLog.objects.create(
                    execution=execution,
                    level=level,
                    message=message,
                    step=step,
                    context_data=context or {}
                )
            except Exception as e:
                logger.error(f"Failed to store log: {str(e)}")
    
    def execute_raw_sql(self, sql: str, params: List[Any] = None) -> List[Dict[str, Any]]:
        """
        Execute raw SQL query
        
        Args:
            sql: SQL query string
            params: Query parameters
            
        Returns:
            List of result dictionaries
        """
        try:
            with connection.cursor() as cursor:
                cursor.execute(sql, params or [])
                columns = [col[0] for col in cursor.description]
                return [dict(zip(columns, row)) for row in cursor.fetchall()]
        except Exception as e:
            self.log_error(f"SQL execution failed: {str(e)}", 'database')
            raise
    
    def update_records(self, table: str, updates: Dict[str, Any], conditions: Dict[str, Any]) -> int:
        """
        Update database records
        
        Args:
            table: Table name
            updates: Fields to update
            conditions: WHERE conditions
            
        Returns:
            Number of affected rows
        """
        try:
            # Build UPDATE query
            set_clause = ', '.join([f"`{k}` = %s" for k in updates.keys()])
            where_clause = ' AND '.join([f"`{k}` = %s" for k in conditions.keys()])
            
            sql = f"UPDATE `{table}` SET {set_clause} WHERE {where_clause}"
            params = list(updates.values()) + list(conditions.values())
            
            with connection.cursor() as cursor:
                cursor.execute(sql, params)
                affected_rows = cursor.rowcount
                
            self.log_info(f"Updated {affected_rows} records in {table}", 'database')
            return affected_rows
            
        except Exception as e:
            self.log_error(f"Update failed for table {table}: {str(e)}", 'database')
            raise
    
    def get_processing_stats(self) -> Dict[str, Any]:
        """Get processing statistics"""
        duration = (timezone.now() - self.start_time).total_seconds()
        return {
            **self.stats,
            'duration_seconds': duration,
            'started_at': self.start_time.isoformat(),
            'finished_at': timezone.now().isoformat()
        }