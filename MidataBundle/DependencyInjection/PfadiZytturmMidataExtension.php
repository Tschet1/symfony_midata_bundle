<?php

namespace PfadiZytturm\MidataBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class PfadiZytturmMidataExtension extends Extension
{

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        // set config
        $container->setParameter('midata.url', $config['midata']['url']);
        $container->setParameter('midata.user', $config['midata']['user']);
        $container->setParameter('midata.password', $config['midata']['password']);
        $container->setParameter('midata.groupId', $config['midata']['groupId']);
        $container->setParameter('midata.cache.TTL', $config['midata']['cache']['ttl']);
        $container->setParameter('midata.mail.view.done', $config['mail']['done_view']);

        if (isset($config['mail']['mail_domain'])) {
            $container->setParameter('midata.mail.mail_domain', $config['mail']['mail_domain']);
        }
        if (isset($config['mail']['logger'])) {
            $container->setParameter('midata.mail.logger', $config['mail']['logger']);
        }
        if (isset($config['midata']['role_mapping'])) {
            $container->setParameter('midata.roleMapping', $config['midata']['role_mapping']);
        }

        if (isset($config['midata']['tn_roles']) && $config['midata']['tn_roles'] != []) {
            $container->setParameter('midata.tnRoles', $config['midata']['tn_roles']);
        } else {
            $container->setParameter('midata.tnRoles', [
                "Biber",
                "Wolf",
                "Leitwolf",
                "Leitpfadi",
                "Pfadi",
                "Pio"
            ]);
        }


        $container->setParameter('midata.mail.mapping', $config['mail']['key_mapping']);
        $container->setParameter('midata.mail.mailer', $config['mail']['mailer']);
    }
}
