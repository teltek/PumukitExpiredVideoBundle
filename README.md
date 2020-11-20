Installation Guide
==================

### Step 0: Introduce the repository in the root project composer.json

Open a command console, enter your project directory and execute the
following command to add this repo:

```bash
$ composer config repositories.pumukitexpiredvideobundle vcs https://github.com/teltek/PumukitExpiredVideoBundle
```

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require teltek/pumukit-expired-video-bundle dev-master
```

### Step 2: Install the Bundle

Install the bundle by executing the following line command. This command updates the Kernel to enable the bundle (app/AppKernel.php) and loads the routing (app/config/routing.yml) to add the bundle routes\
.

```bash
$ php app/console pumukit:install:bundle Pumukit/ExpiredVideoBundle/PumukitExpiredVideoBundle
```

### Step 3: Clear cache

Clear cache in development and production environments

```bash
$ php app/console cache:clear
$ php app/console cache:clear --env=prod
```
