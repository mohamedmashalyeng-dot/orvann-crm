# Copy this file to deploy.settings.ps1 and fill it in (deploy.settings.ps1 is not committed).
# The values are on hPanel > Advanced > SSH Access.

# SSH IP and port.
$SshHost = "0.0.0.0"
$SshPort = 65002

# SSH username, e.g. "u123456789".
$SshUser = "u123456789"

# The private key whose public half is added under SSH Keys in hPanel.
$SshKey = "$env:USERPROFILE\.ssh\orvann_crm_deploy"

# The CRM's folder on the server, relative to the hosting home folder.
$RemotePath = "domains/example.com/public_html/crm"

# The CRM's address, checked after every upload.
$SiteUrl = "https://crm.example.com"
