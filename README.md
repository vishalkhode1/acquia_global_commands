# Acquia Global Commands
Setup the database credentials on pre-validate hook of drush command and help in setting up the multi-site
out of the box if the user runs `site:install` command with `--uri` params.

This command is used by the [Drupal Recommended Settings](https://github.com/acquia/drupal-recommended-settings) for multi-site database configuration setup.

Example: `drush site:install --uri site1`

### Requirement
Add `installer-paths` in your root composer.json to place this
plugin at `drush/Commands/contrib/acquia_global_commands`

```
"extra": {
  ...
  "installer-paths": {
    ...
    "drush/Commands/contrib/{$name}": ["type:drupal-drush"]
  }
}
```

# License

Copyright (C) 2023 Acquia, Inc.

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License version 2 as published by the
Free Software Foundation.
