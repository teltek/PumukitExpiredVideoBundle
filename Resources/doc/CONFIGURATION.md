Configuration Guide
===================

### Step 1: Add or import the role

1.- Add the following line to include the role "expired owner" into your Resources/data/roles/roles_i18n.csv

```
x;"expired_owner";"expired_owner";1;"Antiguo propietario";"Antiguo propietario";"Old owner"
```

where X is the number of last role to be added.


### Step 2: Add configuration

The following parameters are necessary to configure the expired video bundle.

```bash
pumukit_expired_video:
    expiration_date_days: 365
    range_warning_days: 90
```

`expiration_date_days`: number of days in which the Multimedia Object is active from the day of creation. When reaching these number of date days, the Multimedia Object will expire. If a Multimedia Object is renewed, the expiration day will be incresead in this number of days. If you set `expiration_date_days` to `0`, the Expired Video Bundle won't work and commands won't be executed.

`range_warning_days`: used on the administration panel where the list of expired videos to see in an easy way the Multimedia Objects that will be expired in this range of days.
