PuMuKIT Expired video Bundle
=======================

This bundle provides auto expired video feature.

```bash
composer require teltek/pumukit-expired-video-bundle
```

if not, add this to config/bundles.php

```
Pumukit\ExpiredVideoBundle\PumukitExpiredVideoBundle::class => ['all' => true]
```

Then execute the following commands

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console assets:install
```
