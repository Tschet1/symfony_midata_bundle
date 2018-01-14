<?php

namespace PfadiZytturm\MidataBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pfadi_zytturm_midata');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        $rootNode
            ->children()
                ->arrayNode("mail")->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode("mail_domain")->end()
                        ->scalarNode("logger")->end()
                        ->scalarNode("mailer")->end()
                        ->arrayNode("key_mapping")->ignoreExtraKeys()->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('first_name')->defaultValue('Vorname')->end()
                                ->scalarNode('last_name')->defaultValue('Nachname')->end()
                                ->scalarNode('nickname')->defaultValue('Pfadiname')->end()
                                ->scalarNode('email')->defaultValue('Mail')->end()
                                ->scalarNode('eMutter')->defaultValue('Mail Mutter')->end()
                                ->scalarNode('eVater')->defaultValue('Mail Vater')->end()
                                ->scalarNode('address')->defaultValue('Strasse')->end()
                                ->scalarNode('zip_code')->defaultValue('Postleitzahl')->end()
                                ->scalarNode('town')->defaultValue('Ort')->end()
                                ->scalarNode('tPrivat')->defaultValue('Telefon Privat')->end()
                                ->scalarNode('tMobile')->defaultValue('Telefon Mobile')->end()
                                ->scalarNode('tVater')->defaultValue('Telefon Vater')->end()
                                ->scalarNode('tMutter')->defaultValue('Telefon Mutter')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode("midata")
                    ->children()
                        ->scalarNode("url")->defaultValue("https://db.scout.ch")->end()
                        ->scalarNode("user")->isRequired()->end()
                        ->scalarNode("password")->isRequired()->end()
                        ->integerNode("groupId")->isRequired()->end()
                        ->arrayNode("cache")->addDefaultsIfNotSet()->children()
                            ->integerNode("ttl")->defaultValue(60 * 60 * 24 * 7)->end()->end()
                        ->end()
                        ->arrayNode("role_mapping")
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        return $treeBuilder;
    }
}

