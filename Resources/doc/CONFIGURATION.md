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

### Other configuration
```
pumukit_expired_video:
    notification_email_subject: "PuMuKIT - These videos will be expired coming soon."
    notification_email_template: "@PumukitExpiredVideo/Email/notification.html.twig"
    update_email_subject: "PuMuKIT - Remove owner of the following video."
    update_email_template: "@PumukitExpiredVideo/Email/update_admin_email.html.twig"
    administrator_emails: ['youremailaccount@pumukit.es']
    delete_email_subject: "PuMuKIT - Multimedia objects deleted"
    delete_email_template: "@PumukitExpiredVideo/Email/delete_admin_email.html.twig"
``` 

`notification_email_subject`: Subject of email send on notification command
`notification_email_template`: Twig template to use on notification command
`update_email_subject`: Subject of email send on update command
`update_email_template`: Twig template to use on update command
`administrator_emails`: Emails of admin that you want to receive all notifications 
`delete_email_subject`: Subject of email send on delete command
`delete_email_template`:  Twig template to use on delete command
