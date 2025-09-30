@@ .. @@
     path("query/", include("apps.query.urls")),
-    path("workflow/", include("apps.workflow_automation.urls")),
+    path("workflow/", include("apps.workflow_automation.urls")),
+    path("alerts/", include("apps.grm_alerts.urls")),