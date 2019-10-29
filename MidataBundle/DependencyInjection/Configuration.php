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
                        ->scalarNode("done_view")->isRequired()->end()
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
                                ->scalarNode('Versandadressen')->defaultValue('Versand-Mailadressen')->end()
                                ->arrayNode('Rollen')->ignoreExtraKeys()->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('Gruppe')->defaultValue('Gruppe')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode("anhaengeFolder")->defaultValue("%kernel.root_dir%/Mails/anhaenge/")->end()
                    ->end()
                ->end()
                ->arrayNode("midata")
                    ->children()
                        ->scalarNode("url")->defaultValue("https://db.scout.ch")->end()
                        ->scalarNode("token")->defaultValue('')->end()
                        ->scalarNode("user")->defaultValue('')->end()
                        ->scalarNode("password")->defaultValue('')->end()
                        ->integerNode("groupId")->isRequired()->end()
                        ->arrayNode("cache")->addDefaultsIfNotSet()->children()
                            ->integerNode("ttl")->defaultValue(60 * 60 * 24 * 7)->end()->end()
                        ->end()
                        ->arrayNode("role_mapping")
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->arrayNode("tn_roles")
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
