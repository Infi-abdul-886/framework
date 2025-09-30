"""
Query Selectors - Responsible for calling the query_builder_api to fetch data
"""
import json
import logging
from django.test import RequestFactory
from django.http import JsonResponse
from typing import Dict, List, Any, Optional

logger = logging.getLogger(__name__)

class QuerySelector:
    """
    Base class for executing queries using the query_builder_api
    """
    
    def __init__(self):
        self.factory = RequestFactory()
    
    def execute_query_template(self, template_id: int, parameters: Dict[str, Any] = None) -> Dict[str, Any]:
        """
        Execute a query template using the query_builder_api
        
        Args:
            template_id: ID of the QueryTemplate to execute
            parameters: Optional parameters to substitute in the query
            
        Returns:
            Dict containing query results
        """
        try:
            from apps.query.models import QueryTemplate
            from apps.query.views import query_builder_api
            
            # Get the template
            template = QueryTemplate.objects.get(id=template_id, is_active=True)
            
            # Get the configuration
            config = template.configuration.copy()
            
            # Apply parameters if provided
            if parameters:
                config = self._apply_parameters(config, parameters)
            
            # Create mock request
            request = self.factory.post(
                '/query/api/query/',
                data=json.dumps(config),
                content_type='application/json'
            )
            
            # Execute the query
            response = query_builder_api(request)
            
            if response.status_code == 200:
                response_data = json.loads(response.content)
                return {
                    'success': True,
                    'data': response_data.get('data', []),
                    'count': len(response_data.get('data', []))
                }
            else:
                error_data = json.loads(response.content)
                return {
                    'success': False,
                    'error': error_data.get('error', 'Unknown error'),
                    'data': []
                }
                
        except Exception as e:
            logger.error(f"Query execution failed: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'data': []
            }
    
    def _apply_parameters(self, config: Dict[str, Any], parameters: Dict[str, Any]) -> Dict[str, Any]:
        """
        Apply parameters to query configuration
        
        Args:
            config: Query configuration
            parameters: Parameters to apply
            
        Returns:
            Updated configuration
        """
        # Convert config to JSON string, replace parameters, then parse back
        config_str = json.dumps(config)
        
        for key, value in parameters.items():
            placeholder = f"__{key.upper()}__"
            config_str = config_str.replace(placeholder, str(value))
        
        return json.loads(config_str)

class ExpiryQuerySelector(QuerySelector):
    """
    Specialized selector for expiry-related queries
    """
    
    def get_expiring_offers(self, hours_ahead: int = 24) -> Dict[str, Any]:
        """
        Get offers that are expiring within specified hours
        
        Args:
            hours_ahead: Number of hours to look ahead
            
        Returns:
            Dict containing expiring offers data
        """
        # This would use a pre-configured QueryTemplate
        # Template ID would be configured in the workflow
        return self.execute_query_template(
            template_id=1,  # This would be configured
            parameters={'HOURS_AHEAD': hours_ahead}
        )
    
    def get_expiring_payments(self, hours_ahead: int = 24) -> Dict[str, Any]:
        """
        Get payments that are expiring within specified hours
        """
        return self.execute_query_template(
            template_id=2,  # This would be configured
            parameters={'HOURS_AHEAD': hours_ahead}
        )
    
    def get_expiring_passengers(self, hours_ahead: int = 24) -> Dict[str, Any]:
        """
        Get passenger submissions that are expiring within specified hours
        """
        return self.execute_query_template(
            template_id=3,  # This would be configured
            parameters={'HOURS_AHEAD': hours_ahead}
        )
    
    def get_expiring_penalties(self, hours_ahead: int = 24) -> Dict[str, Any]:
        """
        Get penalties that are expiring within specified hours
        """
        return self.execute_query_template(
            template_id=4,  # This would be configured
            parameters={'HOURS_AHEAD': hours_ahead}
        )

class StatusUpdateSelector(QuerySelector):
    """
    Selector for status update operations
    """
    
    def get_expired_offers(self) -> Dict[str, Any]:
        """
        Get offers that have already expired and need status updates
        """
        return self.execute_query_template(template_id=5)
    
    def get_expired_penalties(self) -> Dict[str, Any]:
        """
        Get penalties that have expired and need status updates
        """
        return self.execute_query_template(template_id=6)