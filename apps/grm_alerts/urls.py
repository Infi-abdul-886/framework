from django.urls import path, include
from rest_framework.routers import DefaultRouter
from . import views

app_name = 'grm_alerts'

# API router
router = DefaultRouter()
# Add API viewsets here if needed

urlpatterns = [
    # Web views
    path('', views.dashboard_view, name='dashboard'),
    path('workflows/', views.workflow_list_view, name='workflow_list'),
    path('workflows/<uuid:workflow_id>/', views.workflow_detail_view, name='workflow_detail'),
    path('executions/', views.execution_list_view, name='execution_list'),
    path('executions/<uuid:execution_id>/', views.execution_detail_view, name='execution_detail'),
    
    # API endpoints
    path('api/', include(router.urls)),
    path('api/workflows/<uuid:workflow_id>/execute/', views.execute_workflow_api, name='execute_workflow'),
]