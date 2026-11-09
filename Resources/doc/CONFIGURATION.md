Expired video configuration
===========================

Bundle
------

```yaml
pumukit_expired_video:
  enable: true
  expiration_date_days: 365
  range_warning_days: 90
  administrator_emails: ['admin@example.org']
```

| Option                        | Default                                                   | Description |
|-------------------------------|-----------------------------------------------------------|-------------|
| `enable`                      | `true`                                                    | If false, the routes give 404 and nothing else of the bundle is loaded: no commands, listener, voter, menus or permissions |
| `expiration_date_days`        | `365`                                                     | Days a new multimedia object lasts, days added on each renewal and days an expired video waits before it is deleted (minimum 1) |
| `range_warning_days`          | `90`                                                      | Days ahead shown in the back-office list |
| `administrator_emails`        | `['youremailaccount@pumukit.es']`                         | Receivers of the update and delete reports |
| `notification_email_subject`  | `PuMuKIT - These videos will be expired coming soon.`     | Subject of the email to the owners |
| `notification_email_template` | `@PumukitExpiredVideo/Email/notification.html.twig`       | Template of the email to the owners |
| `update_email_subject`        | `PuMuKIT - Remove owner of the following video.`          | Subject of the update report |
| `update_email_template`       | `@PumukitExpiredVideo/Email/update_admin_email.html.twig` | Template of the update report |
| `delete_email_subject`        | `PuMuKIT - Multimedia objects deleted`                    | Subject of the delete report |
| `delete_email_template`       | `@PumukitExpiredVideo/Email/delete_admin_email.html.twig` | Template of the delete report |

The emails are sent with the core `NotificationBundle`, so it must be enabled.

Permissions
-----------

- `ROLE_ACCESS_EXPIRED_VIDEO`: the back-office list, changing the expiration date of a video and renewing all the
  videos of a series. With it, a user can also renew any video from the email links.
- `ROLE_UNLIMITED_EXPIRED_VIDEO`: the videos created by the user and the series renewed without a date do not
  expire (year 9999).

Every user with access to the multimedia objects sees "Show expiration date" in the menu of a video.

Roles
-----

The "Old owner" role (`expired_owner`) receives the owners of an expired video. It is created, hidden in the
portal, the first time it is needed.

Renewals
--------

- From the email links, an owner extends the expiration date of the videos that have not expired yet. Once the
  update command has moved the owners to "Old owner" and unpublished a video, the links no longer renew it.
- From the back-office list ("Renew with the same owners"), an administrator extends the date, gives the old owners
  back the owner role and publishes the video again in the WebTV channel.

Commands
--------

```bash
# Email the owners of the videos that expire in exactly 30 days (or in the next 30 days with true)
php bin/console pumukit:expired:video:notification 30 false

# Move the owners of the expired videos to "Old owner" and remove the PUCHWEBTV tag
php bin/console pumukit:expired:video:update --force

# Delete the videos expired more than expiration_date_days ago (without --force it only lists them)
php bin/console pumukit:expired:video:delete --force

# List the expired videos
php bin/console pumukit:expired:video:list

# Give back the owners of renewed videos without owner (by default, all the users)
php bin/console pumukit:expired:video:renewed:without:owner --user=jdoe --renewDate=2027/12/31 --addPublishTag --force
```

A typical crontab:

```
0 2 * * *   /srv/pumukit/bin/console pumukit:expired:video:notification 90 false --env=prod
10 2 * * *  /srv/pumukit/bin/console pumukit:expired:video:notification 30 false --env=prod
20 2 * * *  /srv/pumukit/bin/console pumukit:expired:video:notification 7 false --env=prod
40 2 * * *  /srv/pumukit/bin/console pumukit:expired:video:update --force --env=prod
50 2 * * *  /srv/pumukit/bin/console pumukit:expired:video:delete --force --env=prod
```
