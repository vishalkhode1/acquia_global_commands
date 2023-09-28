## About
Provides a global drush pre-command hook for setting up multi-site out of the box if the user runs `site:install`
command with `--uri` params.

Example: `drush site:install --uri site1`

### Requirement
Add `installer-paths` in your root composer.json to place this plugin at `drush/Commands/contrib/acquia_global_commands`

```
"extra": {
  ...
  "installer-paths": {
    ...
    "drush/Commands/contrib/{$name}": ["type:drupal-drush"]
  }
}
```
