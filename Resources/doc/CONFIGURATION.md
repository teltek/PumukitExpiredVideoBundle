Configuration Guide
===================

The following parameters are necessary to configure the expired video bundle.

```bash
pumukit_expired_video:
    expiration_date_days: 365
    range_warning_days: 90
```

Expiration date days are the days that the Multimedia Object have to expired. If an Multimedia object is renewed the
expiration day will be increase this number of days.

If you set expiration_date_days at 0, the expired video bundle will not work and commands don't will be execute.

Range warning days is used on administration panel list expired video to see in an easy way the multimedia objects that
 will be expired in this range.