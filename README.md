![](tecart-logo-rgba_h120.png)
# Tecart Review Workflow Plugin

Der **Tecart Review Workflow** ist ein Plugin für das [Grav CMS](http://github.com/getgrav/grav). Angewendet wird es im Zusammenhang mit dem [TecArt® Skeleton Improval Workflow](https://github.com/TecArt/grav-skeleton-tecart-approval-workflow).

## TecArt® Skeleton Improval Workflow

TecArt® Approval Workflow ist ein Skeleton für das Flat-File CMS [Grav](http://github.com/getgrav/grav). Es beinhatet u.a. folgende Plugins:
- TecArt® Fork des Grav Plugin Admin
- TecArt® Fork des Grav Plugin GitSync
- Grav Plugin TecArt® Jira Connector
- **Grav Plugin TecArt® Review workflow**

## Installation

Laden Sie sich einfach die [ZIP-Datei des letzten Release](https://github.com/TecArt/grav-skeleton-tecart-approval-workflow/releases/download/1.0/grav-skeleton_tecart-approval-workflow_v1.0.zip) herunter, entpacken Sie sie in Ihrem web-root Verzeichnis und Sie können loslegen!

Webserver-Starten:
```bash
php -S localhost:8000 system/router.php
```

Hinweis: Bevor Sie den Content unter der Nutzung der TecArt Plugins bearbeiten können, müssen die Plugins *TecArt GitSync*, *TecArt Jira Connector* und *TecArt Review Workflow* konfiguriert werden. Außerdem sollten die Logins der eingerichteten Nutzer den jeweiligen Loginnamen Ihres Jira- bzw. Bitbucket-Systems entsprechen.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/tecart-review-workflow/tecart-review-workflow.yaml` to `user/config/plugins/tecart-review-workflow.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
start_progress: 4
stop_progress: 301
start_review: 711
stop_review: 761
start_qa: 741
publish: 5
editors: Editor
developers: Developer
```

Note that if you use the admin plugin, a file with your configuration, and named tecart-review-workflow.yaml will be saved in the `user/config/plugins/` folder once the configuration is saved in the admin.

**Kontakt**  
TecArt GmbH  
Sören Müller  
github@tecart.de

