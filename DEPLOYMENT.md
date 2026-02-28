# Deployment Notes (Hostinger Git)

Hostinger checks only the project root for `composer.json` / `composer.lock`.
This project has Composer dependencies in subfolders, so use this post-deploy command:

```bash
bash deploy_hostinger.sh
```

It installs dependencies in:
- `composer-email-handler`
- `PhpFiles/PhpOffice`

## Optional cleanup for runtime uploads
Git deployment may show untracked runtime files under `UnifiedFileAttachment/`.
These are normal uploads and not part of repository history.
