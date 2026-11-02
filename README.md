PuMuKIT Expired Video Bundle
============================

Bundle based on [Symfony](http://symfony.com/) to work with the [PuMuKIT Video Platform 6.0](https://github.com/pumukit/PuMuKIT/tree/6.0.x) or higher.

The PumukitExpiredVideoBundle gives every new multimedia object an expiration date and:

- emails the owners before their videos expire, with links to renew one video or all of them,
- moves the owners of an expired video to the "Old owner" role and unpublishes it from the WebTV channel,
- deletes the videos that stay expired for another period,
- lists the videos about to expire in the back-office (menu "Expired video list") and lets the administrators
  renew or delete them, change the expiration date of a video and renew all the videos of a series.

Installation
------------

```bash
composer require teltek/pumukit-expired-video-bundle
```

If Symfony Flex does not do it, add the bundle to `config/bundles.php`:

```php
Pumukit\ExpiredVideoBundle\PumukitExpiredVideoBundle::class => ['all' => true],
```

and its routes:

```yaml
pumukit_expired_video:
  resource: "@PumukitExpiredVideoBundle/Resources/config/routing.yml"
```

Then configure the bundle as explained in the [configuration guide](Resources/doc/CONFIGURATION.md) and clear
the cache:

```bash
php bin/console cache:clear
```

Upgrading from 5.0.x (PuMuKIT 5)
--------------------------------

- `expiration_date_days: 0` no longer disables the bundle and is rejected: use `enable: false`. Disabled, the
  commands and the listener do nothing, the routes return 404 and the menus and permissions disappear.
- `expiration_date_days` and `range_warning_days` must be integers.
- `pumukit:expired:video:delete` without `--force` only lists the videos; before, it deleted them anyway. The
  videos and the series left empty are deleted through the core: their files, jobs, playlists and annotations
  go with them and the delete events are dispatched.
- The "Old owner" role (`expired_owner`) is created when it is first needed: the line of `roles_i18n.csv` is no
  longer required. The existing role is kept.
- Deleting and renewing from the back-office list are POST requests with a CSRF token. The routes
  `pumukit_expired_video_delete` and `pumukit_expired_video_update` take the multimedia object id as `{id}`.
- The views use Bootstrap 5 and Stimulus: if a client overrides `@PumukitExpiredVideo` templates, it must adapt them.
- The link to renew all the videos of an owner works again, and its token can only be used once.
- Renewing all the videos of a series without a date uses `expiration_date_days` (before, about 10,000 years).
- `pumukit:expired:video:renewed:without:owner --force` requires `--renewDate YYYY/mm/dd`.
- The commands are the `pumukit:expired:video:*` ones; old crontabs with `video:expired:*` must be updated.
