from rest_framework import serializers
from .models import QueryTemplate, QueryFile, ScheduledQueryTask

class QueryTemplateSerializer(serializers.ModelSerializer):
    query_file_name = serializers.CharField(source='query_file.file_name', read_only=True)
    full_name = serializers.CharField(source='get_full_name', read_only=True)
    
    class Meta:
        model = QueryTemplate
        fields = ['id', 'name', 'description', 'configuration', 'query_file', 'query_file_name', 'full_name', 'is_active']

class QueryFileSerializer(serializers.ModelSerializer):
    query_count = serializers.IntegerField(source='get_query_count', read_only=True)
    query_templates = QueryTemplateSerializer(many=True, read_only=True)
    
    class Meta:
        model = QueryFile
        fields = ['id', 'file_name', 'description', 'query_count', 'query_templates', 'is_active', 'created_at', 'updated_at']

class ScheduledQueryTaskSerializer(serializers.ModelSerializer):
    query_templates_detail = QueryTemplateSerializer(source='query_templates', many=True, read_only=True)
    query_templates_count = serializers.SerializerMethodField()
    
    class Meta:
        model = ScheduledQueryTask
        fields = ['id', 'task_name', 'description', 'query_templates', 'query_templates_detail', 
                 'query_templates_count', 'cron_expression', 'email_recipients', 'email_subject', 
                 'output_format', 'is_active', 'last_run']
    
    def get_query_templates_count(self, obj):
        return obj.query_templates.count()