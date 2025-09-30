"""
Utility functions for GRM alerts
"""
import importlib
from typing import Dict, Any, Optional
from .processors.expiry_processors import PROCESSOR_REGISTRY

def get_processor_registry() -> Dict[str, Dict[str, Any]]:
    """
    Get the complete processor registry for UI display
    
    Returns:
        Dict containing all available processors organized by category
    """
    return PROCESSOR_REGISTRY

def get_processor_categories() -> Dict[str, Dict[str, Any]]:
    """
    Get processors organized by category for UI dropdown
    
    Returns:
        Dict with category structure for nested dropdowns
    """
    categories = {}
    
    for key, processor_info in PROCESSOR_REGISTRY.items():
        parts = key.split('::')
        
        # Build nested structure
        current = categories
        for part in parts[:-1]:
            if part not in current:
                current[part] = {}
            current = current[part]
        
        # Add the processor
        current[parts[-1]] = {
            'key': key,
            'name': processor_info['name'],
            'description': processor_info['description'],
            'config_schema': processor_info['config_schema']
        }
    
    return categories

def get_processor_class(processor_key: str) -> Optional[type]:
    """
    Get processor class by key
    
    Args:
        processor_key: Processor registry key
        
    Returns:
        Processor class or None if not found
    """
    if processor_key not in PROCESSOR_REGISTRY:
        return None
    
    processor_info = PROCESSOR_REGISTRY[processor_key]
    class_path = processor_info['class']
    
    try:
        module_path, class_name = class_path.rsplit('.', 1)
        module = importlib.import_module(module_path)
        return getattr(module, class_name)
    except (ImportError, AttributeError):
        return None

def validate_processor_config(processor_key: str, config: Dict[str, Any]) -> Dict[str, Any]:
    """
    Validate processor configuration against schema
    
    Args:
        processor_key: Processor registry key
        config: Configuration to validate
        
    Returns:
        Dict with validation results
    """
    if processor_key not in PROCESSOR_REGISTRY:
        return {'valid': False, 'errors': ['Unknown processor key']}
    
    schema = PROCESSOR_REGISTRY[processor_key]['config_schema']
    errors = []
    
    # Basic validation - check required fields
    for field_name, field_schema in schema.items():
        if field_schema.get('required', False) and field_name not in config:
            errors.append(f"Required field '{field_name}' is missing")
    
    return {
        'valid': len(errors) == 0,
        'errors': errors
    }

def format_cron_description(cron_expression: str) -> str:
    """
    Convert cron expression to human-readable description
    
    Args:
        cron_expression: Cron expression string
        
    Returns:
        Human-readable description
    """
    try:
        from cron_descriptor import get_description
        return get_description(cron_expression)
    except:
        # Fallback descriptions
        common_patterns = {
            '*/5 * * * *': 'Every 5 minutes',
            '0 * * * *': 'Every hour',
            '0 */2 * * *': 'Every 2 hours',
            '0 */4 * * *': 'Every 4 hours',
            '0 */6 * * *': 'Every 6 hours',
            '0 0 * * *': 'Daily at midnight',
            '0 9 * * *': 'Daily at 9 AM',
        }
        return common_patterns.get(cron_expression, f'Custom: {cron_expression}')