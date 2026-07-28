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

`deploy_hostinger.sh` stores runtime uploads outside the Git checkout and creates
`UnifiedFileAttachment` as a symlink to that persistent directory. By default:

```text
<parent-of-project>/.barangaysanjose-data/UnifiedFileAttachment
```

On its first run, the script copies any existing files from
`UnifiedFileAttachment` into persistent storage before creating the link. Later
Git deployments can replace the project checkout without replacing uploaded
files.

To use a different absolute location, configure this environment variable in
Hostinger before running the post-deploy command:

```bash
export APP_PERSISTENT_UPLOAD_DIR=/home/your-account/private/barangaysanjose-uploads
bash deploy_hostinger.sh
```

The chosen directory must be outside the Git project directory. Ensure the PHP
process has read/write permission to it. Keep this directory in the hosting
backup plan because Git does not contain user uploads.
