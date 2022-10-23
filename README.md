Installation
============

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
$ composer require pfadizytturm/midatabundle
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require pfadizytturm/midatabundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new PfadiZytturm\MidataBundle\PfadiZytturmMidataBundle(),
        );

        // ...
    }

    // ...
}
```

### Step 3: Enable the routes

This bundle comes with a /mail route. Set the prefix as you whish.

```yml
Midata:
    resource: "@PfadiZytturmMidataBundle/Resources/config/routing.yml"
    prefix:   <your prefix>
```

### Step 4: Configuration

The bundle needs some configuration in your app/config/config.yml

```yml
pfadi_zytturm_midata:
    mail:
        mail_domain: # set this to a domain, if you want to enforce your mails to come from <nickname>@your-domain.ch
        logger: # if you want to use the mailing, you need to specify a mailing service
                # this service must have a public method:
                # sendMail(string $content, string $subject, array $receivers,
                #    array [$sende_mail => $sender_name], string $attachement]
        mailer: # if you want to use the logger, you need to specify a logger service
                # this service must have a public method:
                # log(string $message)
        done_view: # set this if you want to set a custom view after the mail has been sent
        key_mapping: # you can overwrite the default set of mappings from the midata keys to the keys displayed for the
                     # mail replacement
    midata:
        user: # (required) user for the login to midata
        password: # (required) password for the login to midata
        groupId: # (required) set the main group id.
        role_mapping: # change this to overwrite the default midata group to symfony role mapping when you use the login 
                      # mechanism
        cache:
            ttl: # change this to change the TTL of the cached midata values 

```


